<?php

namespace App\Services\Personnel;

use App\Enums\Position as P;
use App\Models\Staff;
use App\Models\User;
use App\Models\VacationCampaign;
use App\Models\VacationList;
use App\Models\VacationRequest;
use App\Services\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vacances judiciaires : campagne annuelle, quota par service (au plus la moitié de l'effectif, arrondie
 * au-dessus, part le même mois), validation provisoire au 1er niveau puis définitive par le Président,
 * clôture qui fige les listes définitives (signées à la main, puis numérisées).
 */
class VacationService
{
    public function __construct(private Notifier $notifier) {}

    public static function maxDepartures(int $effectif): int
    {
        return $effectif <= 1 ? 1 : (int) ceil($effectif / 2);
    }

    /** Membres du service soumis à la programmation (actifs, non dispensés). */
    public static function serviceMembers(int $serviceId): Collection
    {
        return Staff::withoutGlobalScopes()->with('positions')->active()->where('service_id', $serviceId)->get()
            ->reject(fn ($s) => Approvals::isExempt($s, 'vacances'))->values();
    }

    /**
     * Contrôle du quota pour $staff sur $mois. Renvoie null si le choix est possible, sinon le message.
     */
    public static function quotaError(Staff $staff, VacationCampaign $campaign, string $mois): ?string
    {
        if (! $staff->service_id) {
            return 'Le service d’affectation de '.$staff->full_name.' n’est pas renseigné : la fiche doit être régularisée avant toute programmation.';
        }
        $members = self::serviceMembers($staff->service_id);
        $n = max($members->count(), 1);
        $max = self::maxDepartures($n);
        $taken = VacationRequest::withoutGlobalScopes()->where('vacation_campaign_id', $campaign->id)
            ->whereIn('staff_id', $members->pluck('id'))->where('staff_id', '!=', $staff->id)
            ->where('statut', '!=', 'refusee')->get()->filter(fn ($r) => $r->moisEffectif() === $mois);
        if ($taken->count() + 1 <= $max) {
            return null;
        }
        $autre = collect($campaign->mois)->first(fn ($m) => $m !== $mois);

        return "{$mois} est complet pour le service {$staff->service->nom} : {$taken->count()} agent(s) sur {$n} partent déjà ce mois-là, "
            ."et au plus {$max} peuvent partir en même temps pour assurer la continuité du service."
            .($autre ? " Choisissez {$autre}." : '');
    }

    public function request(Staff $staff, VacationCampaign $campaign, string $mois, User $author): VacationRequest
    {
        $this->ensureOpen($campaign);
        if (Approvals::isExempt($staff, 'vacances')) {
            throw ValidationException::withMessages(['mois' => 'Le Président du Tribunal n’a pas à solliciter de vacances judiciaires.']);
        }
        if (! in_array($mois, $campaign->mois, true)) {
            throw ValidationException::withMessages(['mois' => 'Mois non proposé par la campagne.']);
        }
        if (VacationRequest::withoutGlobalScopes()->where('vacation_campaign_id', $campaign->id)->where('staff_id', $staff->id)->where('statut', '!=', 'refusee')->exists()) {
            throw ValidationException::withMessages(['mois' => 'Une demande est déjà en cours pour cette campagne.']);
        }
        if ($error = self::quotaError($staff, $campaign, $mois)) {
            throw ValidationException::withMessages(['mois' => $error]);
        }

        $req = DB::transaction(function () use ($staff, $campaign, $mois) {
            $req = VacationRequest::create([
                'institution_id' => $staff->institution_id, 'vacation_campaign_id' => $campaign->id, 'staff_id' => $staff->id,
                'mois_demande' => $mois, 'statut' => Approvals::initialStatus($staff, 'vacances'),
            ]);
            $req->logEvent('depot', "Mois demandé : {$mois}");

            return $req;
        });
        $this->notifyDeciders($req);

        return $req;
    }

    /**
     * Décision : au 1er niveau, « retenir » un mois transmet la demande au Président ; au niveau définitif,
     * le Président valide (en gardant ou en changeant le mois) ou rejette.
     */
    public function decide(VacationRequest $req, User $user, bool $accept, ?string $mois, ?string $observation): void
    {
        $this->ensureOpen($req->campaign);
        $staff = $req->staff;
        abort_unless(Approvals::canDecide($user, $staff, 'vacances', $req->statut), 403, 'Vous n’avez pas compétence pour décider sur cette demande.');
        $me = $user->staff;
        $final = $req->statut === 'transmise' || Approvals::firstLevelIsFinal($staff, 'vacances');

        if (! $accept) {
            if (! $observation) {
                throw ValidationException::withMessages(['observation' => 'Le motif du refus est obligatoire.']);
            }
            DB::transaction(function () use ($req, $me, $observation) {
                $req->update(['statut' => 'refusee', 'decider_id' => $me->id, 'decided_at' => now(), 'motif_refus' => $observation]);
                $req->logEvent('refus', $observation);
            });
            $this->notifyOutcome($req->refresh());

            return;
        }

        $mois ??= $req->moisEffectif();
        if (! in_array($mois, $req->campaign->mois, true)) {
            throw ValidationException::withMessages(['mois' => 'Mois non proposé par la campagne.']);
        }
        if ($final && $req->mois_retenu && $mois !== $req->mois_retenu && ! $observation) {
            throw ValidationException::withMessages(['observation' => 'Une observation est obligatoire lorsque le mois accordé diffère du mois retenu au 1er niveau.']);
        }
        if ($error = self::quotaError($staff, $req->campaign, $mois)) {
            throw ValidationException::withMessages(['mois' => $error]);
        }

        DB::transaction(function () use ($req, $me, $mois, $observation, $final) {
            if ($final) {
                $req->update(['statut' => 'validee', 'mois_accorde' => $mois, 'decider_id' => $me->id, 'decided_at' => now(), 'observation' => $observation]);
                $req->logEvent('validation', "Mois accordé : {$mois}".($observation ? " — {$observation}" : ''));
            } else {
                $req->update(['statut' => 'transmise', 'mois_retenu' => $mois, 'first_decider_id' => $me->id, 'first_decided_at' => now()]);
                $req->logEvent('transmission', "Mois retenu à titre provisoire : {$mois}");
            }
        });
        $req->refresh();
        $req->statut === 'transmise' ? $this->notifyDeciders($req) : $this->notifyOutcome($req);
    }

    /** Clôture par le Président : fige les listes d'août, de septembre et la liste générale. */
    public function close(VacationCampaign $campaign, User $user): void
    {
        $this->ensureOpen($campaign);
        $me = $user->staff;
        abort_unless($me && $me->holds(P::Chef) && $me->institution_id === $campaign->institution_id, 403, 'La clôture relève du Président du Tribunal.');
        $pending = $campaign->requests()->where('statut', 'transmise')->count();
        if ($pending) {
            throw ValidationException::withMessages(['campagne' => "Clôture impossible : {$pending} demande(s) attendent encore votre décision."]);
        }

        DB::transaction(function () use ($campaign, $me) {
            $version = $campaign->lists()->whereNull('mois')->count() + 1;
            $rows = $this->finalRows($campaign);
            foreach (array_merge($campaign->mois, [null]) as $mois) {
                $code = $mois ? mb_strtoupper(mb_substr(str_replace('û', 'u', $mois), 0, 4)) : 'GEN';
                VacationList::create([
                    'institution_id' => $campaign->institution_id, 'vacation_campaign_id' => $campaign->id,
                    'numero' => "LD-{$campaign->annee}-{$code}".($version > 1 ? "-V{$version}" : ''),
                    'mois' => $mois,
                    'lignes' => $mois ? array_values(array_filter($rows, fn ($r) => $r['mois'] === $mois)) : $rows,
                    'edited_by' => $me->id,
                ]);
            }
            $campaign->update(['statut' => 'cloturee', 'closed_by' => $me->id, 'closed_at' => now()]);
            $campaign->logEvent('cloture', 'Listes définitives archivées');
        });
    }

    public function reopen(VacationCampaign $campaign, User $user, string $motif): void
    {
        $me = $user->staff;
        abort_unless($me && $me->holds(P::Chef) && ! $campaign->isOpen(), 403);
        $history = trim(($campaign->reouvertures ?? '')."\n".now()->format('d/m/Y H:i')." — {$me->full_name} : {$motif}");
        $campaign->update(['statut' => 'ouverte', 'reouvertures' => $history]);
        $campaign->logEvent('reouverture', $motif);
    }

    /** Lignes des listes définitives : agents non magistrats validés, par mois, service et nom. */
    public function finalRows(VacationCampaign $campaign): array
    {
        return $campaign->requests()->where('statut', 'validee')->with('staff.category', 'staff.service')->get()
            ->reject(fn ($r) => $r->staff->category?->magistrat)
            ->map(fn ($r) => [
                'nom' => $r->staff->full_name, 'matricule' => $r->staff->matricule, 'categorie' => $r->staff->category?->nom,
                'service' => $r->staff->service?->nom ?? 'Non renseigné', 'fonction' => $r->staff->fonction, 'mois' => $r->mois_accorde,
            ])
            ->sortBy([fn ($a, $b) => array_search($a['mois'], $campaign->mois) <=> array_search($b['mois'], $campaign->mois), ['service', 'asc'], ['nom', 'asc']])
            ->values()->all();
    }

    /** Répartition par service pour le contrôle des effectifs. */
    public function serviceStats(VacationCampaign $campaign): Collection
    {
        $requests = $campaign->requests()->where('statut', '!=', 'refusee')->get()->keyBy('staff_id');

        return Staff::with(['service', 'positions'])->active()->get()
            ->reject(fn ($s) => Approvals::isExempt($s, 'vacances'))
            ->groupBy(fn ($s) => $s->service?->nom ?? '')
            ->map(function ($members, $service) use ($requests, $campaign) {
                $n = $members->count();
                $max = self::maxDepartures($n);
                $byMonth = collect($campaign->mois)->mapWithKeys(fn ($m) => [$m => $members->filter(fn ($s) => $requests->get($s->id)?->moisEffectif() === $m)->count()]);

                return (object) [
                    'service' => $service ?: null, 'effectif' => $n, 'max' => $max, 'mois' => $byMonth,
                    'sans_choix' => $members->reject(fn ($s) => $requests->has($s->id))->count(),
                    'conforme' => $byMonth->every(fn ($c) => $c <= $max),
                ];
            })->sortBy('service')->values();
    }

    private function ensureOpen(VacationCampaign $campaign): void
    {
        if (! $campaign->isOpen()) {
            throw ValidationException::withMessages(['campagne' => "La programmation {$campaign->annee} est clôturée : aucune modification n’est possible sans réouverture par le Président."]);
        }
    }

    private function notifyDeciders(VacationRequest $req): void
    {
        $positions = Approvals::positionsFor($req->staff, 'vacances', $req->statut);
        $this->notifier->personnel(
            Approvals::holders($req->institution_id, $positions),
            "Vacances judiciaires {$req->campaign->annee} : demande de {$req->staff->full_name}",
            [
                "{$req->staff->full_name} demande ses vacances judiciaires en {$req->mois_demande}.",
                $req->mois_retenu ? "Mois retenu à titre provisoire : {$req->mois_retenu} ({$req->firstDecider?->full_name})." : null,
            ],
            'Décider', route('personnel.vacations.index', ['annee' => $req->campaign->annee]),
        );
    }

    private function notifyOutcome(VacationRequest $req): void
    {
        $ok = $req->statut === 'validee';
        $users = collect([$req->staff->user, $req->firstDecider?->user]);
        $this->notifier->personnel($users,
            "Vacances judiciaires {$req->campaign->annee} : demande de {$req->staff->full_name} ".($ok ? 'validée' : 'refusée'),
            [
                $ok ? "Mois accordé : {$req->mois_accorde} ({$req->campaign::periode($req->mois_accorde, $req->campaign->annee)})." : "Motif : {$req->motif_refus}",
                $ok && $req->mois_accorde !== $req->mois_demande ? "Mois demandé : {$req->mois_demande}." : null,
                $ok && $req->observation ? "Observation : {$req->observation}" : null,
            ],
            'Voir les vacances judiciaires', route('personnel.vacations.index', ['annee' => $req->campaign->annee]),
        );
    }
}
