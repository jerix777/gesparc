<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Consumable;
use App\Models\Equipment;
use App\Models\Incident;
use App\Models\MaterialRequest;
use App\Models\User;
use App\Notifications\SigaciMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Envoie les courriels de SIGACI. Un échec d'envoi (serveur de messagerie absent…) est journalisé
 * mais ne bloque jamais l'action de l'utilisateur. Désactivable avec SIGACI_NOTIFICATIONS=false.
 */
class Notifier
{
    public function newRequest(MaterialRequest $r): void
    {
        $roles = $r->categorie === 'cartouche' ? [Role::Informaticien, Role::GestionnaireStock] : [Role::Informaticien];
        $this->send($this->staff($r->institution_id, $roles), new SigaciMessage(
            "Nouvelle demande {$r->numero} : {$r->materiel} × {$r->quantite}",
            [
                "{$r->requester->name} demande {$r->quantite} × {$r->materiel}.",
                'Bureau : '.($r->bureau ?: 'non renseigné').'.',
                $r->caracteristiques ? 'Précisions : '.$r->caracteristiques : null,
            ],
            'Traiter la demande',
            route('requests.index'),
        ));
    }

    public function requestDecided(MaterialRequest $r): void
    {
        $ok = $r->statut->value === 'accordee';
        $this->send(collect([$r->requester]), new SigaciMessage(
            "Votre demande {$r->numero} est ".($ok ? 'accordée' : 'refusée'),
            [
                "Votre demande de {$r->quantite} × {$r->materiel} a été ".($ok ? 'accordée' : 'refusée')." par {$r->decider?->name}.",
                $ok ? 'Le Service informatique vous remettra le matériel.' : 'Motif : '.$r->motif_refus,
            ],
            'Voir mes demandes',
            route('requests.index'),
        ));
    }

    public function incidentReported(Incident $i): void
    {
        $e = $i->equipment;
        $this->send($this->staff($i->institution_id, [Role::Informaticien]), new SigaciMessage(
            "Panne {$i->numero} : {$e->designation} ({$e->numero_inventaire})",
            [
                ($i->reporter?->name ?? 'Un agent')." signale une panne sur {$e->designation}, {$e->location()}.",
                'Description : '.$i->description,
            ],
            "Ouvrir la fiche de l'équipement",
            route('equipments.show', $e),
        ));
    }

    public function retirementProposed(Equipment $e): void
    {
        $this->send($this->staff($e->institution_id, [Role::Responsable]), new SigaciMessage(
            "Réforme à valider : {$e->designation} ({$e->numero_inventaire})",
            [
                ($e->retirementProposer?->name ?? 'Le Service informatique')." propose de réformer {$e->designation}.",
                'Motif : '.$e->reforme_motif,
            ],
            'Valider ou rejeter',
            route('equipments.show', $e),
        ));
    }

    public function lowStock(Consumable $c): void
    {
        $this->send($this->staff($c->institution_id, [Role::Informaticien, Role::GestionnaireStock]), new SigaciMessage(
            "Stock bas : {$c->reference} ({$c->stock} restant(s))",
            [
                "Le stock de {$c->designation} est passé à {$c->stock}, sous le seuil d'alerte de {$c->seuil_alerte}.",
                'Pensez à réapprovisionner.',
            ],
            'Voir les consommables',
            route('consumables.index', ['alerte' => 1]),
        ));
    }

    /** Courriel du module Personnel aux comptes donnés (sauf l'auteur de l'action). */
    public function personnel(Collection $users, string $subject, array $lines, string $actionText, string $url): void
    {
        $this->send($users->filter(fn ($u) => $u && $u->id !== Auth::id()), new SigaciMessage($subject, $lines, $actionText, $url));
    }

    /** Destinataires actifs de l'institution ayant l'un des rôles, sauf l'auteur de l'action. */
    private function staff(int $institutionId, array $roles): Collection
    {
        return User::where('institution_id', $institutionId)
            ->whereIn('role', array_map(fn (Role $r) => $r->value, $roles))
            ->where('active', true)
            ->when(Auth::id(), fn ($q, $id) => $q->where('id', '!=', $id))
            ->get();
    }

    private function send(Collection $users, SigaciMessage $message): void
    {
        $message->lines = array_values(array_filter($message->lines));
        $users = $users->filter(fn ($u) => $u && $u->active && $u->email);
        if (! config('sigaci.notifications') || $users->isEmpty()) {
            return;
        }
        try {
            Notification::send($users, $message);
        } catch (\Throwable $e) {
            Log::warning('SIGACI : courriel non envoyé ('.$message->subject.') : '.$e->getMessage());
        }
    }
}
