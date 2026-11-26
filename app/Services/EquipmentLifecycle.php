<?php

namespace App\Services;

use App\Enums\EquipmentStatus as S;
use App\Enums\IncidentStatus;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seul point d'entrée pour changer le statut ou l'affectation d'un équipement.
 * Chaque transition est vérifiée puis inscrite dans l'historique (equipment_movements).
 *
 * Transitions autorisées (voir docs/ARCHITECTURE.md) :
 *   en_stock  → affecte (affectation) | reforme
 *   affecte   → affecte (transfert) | en_stock (restitution) | en_panne | reforme
 *   en_panne  → en_maintenance | en_stock (réparé)
 *   en_maintenance → en_stock (réparé)
 *
 * Réforme en deux temps : l'informaticien propose (ou une panne est déclarée irréparable),
 * le responsable valide (→ reforme) ou rejette. Le statut ne change qu'à la validation.
 */
class EquipmentLifecycle
{
    public function assign(Equipment $e, ?User $user, ?int $serviceId, ?string $bureau, ?string $motif = null): void
    {
        $this->guard($e, [S::EnStock, S::Affecte]);
        if (! $user && ! $serviceId && ! $bureau) {
            throw ValidationException::withMessages(['to_user_id' => 'Indiquez un agent, un service ou un bureau.']);
        }
        if ($user && $user->institution_id !== $e->institution_id) {
            throw ValidationException::withMessages(['to_user_id' => "Cet agent n'appartient pas à l'institution de l'équipement."]);
        }
        $type = $e->statut === S::Affecte ? 'transfert' : 'affectation';
        $this->apply($e, $type, S::Affecte, [
            'assigned_user_id' => $user?->id,
            'service_id' => $serviceId ?? $user?->service_id,
            'bureau' => $bureau ?? $user?->bureau,
        ], $motif);
    }

    public function release(Equipment $e, ?string $motif = null): void
    {
        $this->guard($e, [S::Affecte]);
        $this->apply($e, 'restitution', S::EnStock, ['assigned_user_id' => null], $motif);
    }

    public function reportFailure(Equipment $e, string $description, ?User $reporter = null): Incident
    {
        $this->guard($e, [S::Affecte, S::EnStock]);

        return DB::transaction(function () use ($e, $description, $reporter) {
            $this->apply($e, 'panne', S::EnPanne, [], $description);

            return Incident::create([
                'institution_id' => $e->institution_id,
                'numero' => Numbering::next(Incident::class, 'PAN', $e->institution_id),
                'equipment_id' => $e->id,
                'reported_by' => ($reporter ?? Auth::user())?->id,
                'description' => $description,
                'statut' => IncidentStatus::Ouvert,
            ]);
        });
    }

    public function startMaintenance(Incident $i, ?string $prestataire): void
    {
        $e = $i->equipment;
        $this->guard($e, [S::EnPanne]);
        DB::transaction(function () use ($i, $e, $prestataire) {
            $i->update(['statut' => IncidentStatus::EnCours, 'prestataire' => $prestataire]);
            $this->apply($e, 'maintenance', S::EnMaintenance, [], $prestataire ? 'Prestataire : '.$prestataire : null);
        });
    }

    public function closeIncident(Incident $i, bool $repaired, ?string $resolution, ?int $cout): void
    {
        $e = $i->equipment;
        $this->guard($e, [S::EnPanne, S::EnMaintenance]);
        DB::transaction(function () use ($i, $e, $repaired, $resolution, $cout) {
            $i->update([
                'statut' => $repaired ? IncidentStatus::Resolu : IncidentStatus::Irreparable,
                'resolution' => $resolution,
                'cout' => $cout,
                'resolved_at' => now(),
            ]);
            if ($repaired) {
                // Réparé : il retourne à son affectataire s'il en avait un, sinon en stock.
                $this->apply($e, 'reparation', $e->assigned_user_id ? S::Affecte : S::EnStock, [], $resolution);
            } else {
                $this->proposeRetirement($e, 'Irréparable (panne '.$i->numero.')'.($resolution ? ' : '.$resolution : ''));
            }
        });
    }

    public function proposeRetirement(Equipment $e, string $motif): void
    {
        $this->guard($e, [S::EnStock, S::Affecte, S::EnPanne, S::EnMaintenance]);
        if ($e->hasPendingRetirement()) {
            throw ValidationException::withMessages(['statut' => 'Une proposition de réforme est déjà en attente de validation.']);
        }
        $this->apply($e, 'proposition_reforme', $e->statut, [
            'reforme_motif' => $motif,
            'reforme_proposee_par' => Auth::id(),
            'reforme_proposee_le' => now(),
        ], $motif);
    }

    public function approveRetirement(Equipment $e, ?string $commentaire = null): void
    {
        $this->guardPending($e);
        $motif = $e->reforme_motif.($commentaire ? ' — validation : '.$commentaire : '');
        $this->apply($e, 'reforme', S::Reforme, ['assigned_user_id' => null], $motif);
    }

    public function rejectRetirement(Equipment $e, string $motif): void
    {
        $this->guardPending($e);
        $this->apply($e, 'rejet_reforme', $e->statut, [
            'reforme_motif' => null,
            'reforme_proposee_par' => null,
            'reforme_proposee_le' => null,
        ], $motif);
    }

    private function guardPending(Equipment $e): void
    {
        if (! $e->hasPendingRetirement()) {
            throw ValidationException::withMessages(['statut' => 'Aucune proposition de réforme en attente pour cet équipement.']);
        }
    }

    public function recordReception(Equipment $e): void
    {
        EquipmentMovement::create([
            'institution_id' => $e->institution_id,
            'equipment_id' => $e->id,
            'type' => 'reception',
            'statut_apres' => $e->statut,
            'author_id' => Auth::id(),
        ]);
    }

    private function guard(Equipment $e, array $allowed): void
    {
        if (! in_array($e->statut, $allowed, true)) {
            throw ValidationException::withMessages([
                'statut' => "Action impossible : l'équipement est « {$e->statut->label()} ».",
            ]);
        }
    }

    private function apply(Equipment $e, string $type, S $to, array $changes, ?string $motif): void
    {
        DB::transaction(function () use ($e, $type, $to, $changes, $motif) {
            $before = $e->statut;
            $fromUser = $e->assigned_user_id;
            $e->fill($changes + ['statut' => $to])->save();

            EquipmentMovement::create([
                'institution_id' => $e->institution_id,
                'equipment_id' => $e->id,
                'type' => $type,
                'statut_avant' => $before,
                'statut_apres' => $to,
                'from_user_id' => $fromUser,
                'to_user_id' => $e->assigned_user_id,
                'to_service_id' => $e->service_id,
                'to_bureau' => $e->bureau,
                'motif' => $motif,
                'author_id' => Auth::id(),
            ]);
            AuditLog::record('equipement.'.$type, $e, $motif);
        });
    }
}
