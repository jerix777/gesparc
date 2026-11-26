<?php

namespace App\Services\Personnel;

use App\Enums\Position as P;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\User;
use App\Services\Notifier;
use App\Services\Numbering;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autorisations d'absence, validation électronique : dépôt en ligne, décision en un clic par l'autorité
 * compétente, décision numérotée avec code de vérification. Plus d'impression ni de scan.
 */
class LeaveService
{
    public function __construct(private Notifier $notifier) {}

    public function submit(Staff $staff, array $data, User $author): LeaveRequest
    {
        if (! $staff->isActive()) {
            throw ValidationException::withMessages(['staff_id' => "Cet agent n'est plus dans les effectifs."]);
        }
        if (Approvals::isExempt($staff, 'absence')) {
            throw ValidationException::withMessages(['staff_id' => 'Le Président du Tribunal et le Greffier en Chef n’ont pas à solliciter d’autorisation d’absence.']);
        }
        $debut = Carbon::parse($data['debut'])->startOfDay();
        $fin = Carbon::parse($data['fin'])->startOfDay();
        $overlap = LeaveRequest::withoutGlobalScopes()->where('staff_id', $staff->id)
            ->whereIn('statut', ['en_attente', 'transmise', 'accordee'])
            ->where('debut', '<=', $fin->toDateString())->where('fin', '>=', $debut->toDateString())->first();
        if ($overlap) {
            throw ValidationException::withMessages(['debut' => "Cette période chevauche la demande {$overlap->numero} ({$overlap->statutLabel()})."]);
        }
        $ouvres = WorkingDays::count($debut, $fin);
        if ($ouvres === 0) {
            throw ValidationException::withMessages(['debut' => 'La période ne contient aucun jour ouvré (week-end ou jour férié).']);
        }

        $leave = DB::transaction(function () use ($staff, $data, $author, $debut, $fin, $ouvres) {
            $leave = LeaveRequest::create([
                'institution_id' => $staff->institution_id,
                'numero' => Numbering::next(LeaveRequest::class, 'AA', $staff->institution_id),
                'staff_id' => $staff->id,
                'created_by' => $author->id,
                'motif' => $data['motif'],
                'precision' => $data['precision'] ?? null,
                'debut' => $debut, 'fin' => $fin,
                'jours_ouvres' => $ouvres,
                'jours_calendaires' => $debut->diffInDays($fin) + 1,
                'statut' => Approvals::initialStatus($staff, 'absence'),
            ]);
            $leave->logEvent('depot', $author->staff?->id === $staff->id ? null : 'Saisie pour le compte de l’agent');

            return $leave;
        });
        $this->notifyDeciders($leave);

        return $leave;
    }

    public function decide(LeaveRequest $leave, User $user, bool $accept, ?string $motifRefus = null): void
    {
        $staff = $leave->staff;
        if (! Approvals::canDecide($user, $staff, 'absence', $leave->statut)) {
            abort(403, 'Vous n’avez pas compétence pour décider sur cette demande.');
        }
        $me = $user->staff;
        $now = now();

        DB::transaction(function () use ($leave, $staff, $me, $accept, $motifRefus, $now) {
            if (! $accept) {
                $leave->update(['statut' => 'refusee', 'decider_id' => $me->id, 'decider_fonction' => $this->fonction($me), 'decided_at' => $now, 'motif_refus' => $motifRefus]);
                $leave->logEvent('refus', $motifRefus);

                return;
            }
            if ($leave->statut === 'en_attente' && ! Approvals::firstLevelIsFinal($staff, 'absence')) {
                $leave->update(['statut' => 'transmise', 'first_decider_id' => $me->id, 'first_decided_at' => $now]);
                $leave->logEvent('transmission', 'Avis favorable, transmise pour décision définitive');

                return;
            }
            $leave->update([
                'statut' => 'accordee', 'decider_id' => $me->id, 'decider_fonction' => $this->fonction($me), 'decided_at' => $now,
                'signe_po' => $me->holds(P::AdjointAdministratif) && ! $me->holds(P::ChefAdministratif),
                'verification_code' => Str::upper(Str::random(12)),
            ]);
            $leave->logEvent('accord');
        });

        $leave->refresh();
        if ($leave->statut === 'transmise') {
            $this->notifyDeciders($leave);
        } else {
            $this->notifyOutcome($leave);
        }
    }

    public function cancel(LeaveRequest $leave, User $user): void
    {
        abort_unless($leave->isOpen() && $user->staff?->id === $leave->staff_id, 403);
        $leave->update(['statut' => 'annulee']);
        $leave->logEvent('annulation');
    }

    /** Jours ouvrés accordés à l'agent sur l'année civile. */
    public static function grantedDays(Staff $staff, int $year): int
    {
        return (int) LeaveRequest::withoutGlobalScopes()->where('staff_id', $staff->id)->where('statut', 'accordee')
            ->whereYear('debut', $year)->sum('jours_ouvres');
    }

    private function fonction(Staff $me): string
    {
        return $me->positions->sortBy(fn ($p) => array_search($p->position, P::cases(), true))->first()?->position->label() ?? (string) $me->fonction;
    }

    private function notifyDeciders(LeaveRequest $leave): void
    {
        $positions = Approvals::positionsFor($leave->staff, 'absence', $leave->statut);
        $this->notifier->personnel(
            Approvals::holders($leave->institution_id, $positions)->reject(fn ($u) => $u->id === $leave->staff->user_id),
            "Absence à décider : {$leave->staff->full_name} ({$leave->numero})",
            [
                "{$leave->staff->full_name} demande une autorisation d'absence du {$leave->debut->format('d/m/Y')} au {$leave->fin->format('d/m/Y')} ({$leave->jours_ouvres} jour(s) ouvré(s)).",
                'Motif : '.$leave->motif.($leave->precision ? ' — '.$leave->precision : ''),
                $leave->statut === 'transmise' && $leave->firstDecider ? "Avis favorable de {$leave->firstDecider->full_name}." : null,
            ],
            'Décider', route('personnel.absences.show', $leave),
        );
    }

    private function notifyOutcome(LeaveRequest $leave): void
    {
        $ok = $leave->statut === 'accordee';
        $this->notifier->personnel(
            collect([$leave->staff->user]),
            "Votre demande d'absence {$leave->numero} est ".($ok ? 'accordée' : 'refusée'),
            [
                "Votre demande d'absence du {$leave->debut->format('d/m/Y')} au {$leave->fin->format('d/m/Y')} a été ".($ok ? 'accordée' : 'refusée')." par {$leave->decider_fonction}.",
                $ok ? 'La décision est disponible dans GesParc ; vous pouvez l’imprimer.' : 'Motif : '.$leave->motif_refus,
            ],
            'Voir la demande', route('personnel.absences.show', $leave),
        );
        if ($ok) {
            $rule = $leave->staff->category?->rules->firstWhere('kind', 'absence');
            $copy = Approvals::holders($leave->institution_id, $rule?->copyPositions() ?? []);
            $this->notifier->personnel($copy, "Pour information : absence accordée à {$leave->staff->full_name}",
                ["{$leave->decider_fonction} a accordé à {$leave->staff->full_name} une absence du {$leave->debut->format('d/m/Y')} au {$leave->fin->format('d/m/Y')}."],
                'Voir la décision', route('personnel.absences.show', $leave));
        }
    }
}
