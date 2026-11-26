<?php

namespace App\Http\Controllers\Personnel;

use App\Enums\Position as P;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VacationCampaign;
use App\Models\VacationList;
use App\Models\VacationRequest;
use App\Services\Personnel\Approvals;
use App\Services\Personnel\Authority;
use App\Services\Personnel\VacationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VacationController extends Controller
{
    public function index(Request $request, VacationService $service)
    {
        $user = $request->user();
        $me = $user->staff?->load('positions', 'service', 'category.rules');
        $annee = $request->integer('annee') ?: now()->year;
        $campaign = VacationCampaign::where('annee', $annee)->first();
        $years = VacationCampaign::orderByDesc('annee')->pluck('annee');

        $data = compact('annee', 'campaign', 'years', 'me') + [
            'canOpen' => self::canOpen($user), 'isChef' => (bool) $me?->holds(P::Chef),
            'mine' => null, 'toDecide' => collect(), 'all' => collect(), 'stats' => collect(), 'lists' => collect(),
            'canRequest' => $me && $me->isActive() && ! Approvals::isExempt($me, 'vacances'), 'quota' => [],
        ];
        if ($campaign) {
            $requests = $campaign->requests()->with(['staff.category.rules', 'staff.positions', 'staff.service', 'staff.user', 'firstDecider', 'decider'])->get();
            $data['mine'] = $me ? $requests->where('staff_id', $me->id)->sortByDesc('id')->first() : null;
            $data['toDecide'] = $requests->filter(fn ($r) => Approvals::canDecide($user, $r->staff, 'vacances', $r->statut))->values();
            $data['all'] = $requests->filter(fn ($r) => $r->staff_id !== $me?->id && Approvals::canView($user, $r->staff, 'vacances'))
                ->sortBy(fn ($r) => $r->staff->nom)->values();
            if (Authority::canView($user)) {
                $data['stats'] = $service->serviceStats($campaign);
            }
            $data['lists'] = $campaign->lists()->with('editor')->latest('id')->get();
            if ($data['canRequest'] && (! $data['mine'] || $data['mine']->statut === 'refusee')) {
                $data['quota'] = collect($campaign->mois)->mapWithKeys(fn ($m) => [$m => VacationService::quotaError($me, $campaign, $m)])->all();
            }
            // Effectif du service pour chaque demande à décider (départs ce mois / maximum).
            $data['staffing'] = fn (VacationRequest $r) => $this->staffing($r);
        }

        return view('personnel.vacations.index', $data);
    }

    public function open(Request $request)
    {
        abort_unless(self::canOpen($request->user()), 403);
        $data = $request->validate([
            'annee' => ['required', 'integer', 'between:2020,2100', Rule::unique('vacation_campaigns')->where('institution_id', $request->user()->institution_id)],
            'mois' => ['required', 'array', 'min:1'], 'mois.*' => [Rule::in(VacationCampaign::MOIS)],
        ], ['annee.unique' => 'Une campagne existe déjà pour cette année.']);
        $campaign = VacationCampaign::create(['annee' => $data['annee'], 'mois' => array_values(array_intersect(VacationCampaign::MOIS, $data['mois'])), 'statut' => 'ouverte']);
        $campaign->logEvent('ouverture');

        return redirect()->route('personnel.vacations.index', ['annee' => $campaign->annee])->with('ok', "Campagne {$campaign->annee} ouverte : les agents peuvent choisir leur mois.");
    }

    public function store(Request $request, VacationCampaign $campaign, VacationService $service)
    {
        $data = $request->validate(['mois' => ['required', 'string']]);
        $me = $request->user()->staff ?? abort(403, 'Votre compte n’est relié à aucune fiche du personnel.');
        $req = $service->request($me->load('positions', 'category.rules', 'service'), $campaign, $data['mois'], $request->user());

        return back()->with('ok', "Demande enregistrée pour {$data['mois']} ; elle est transmise à : "
            .Approvals::describe(Approvals::positionsFor($me, 'vacances', $req->statut)).'.');
    }

    public function decide(Request $request, VacationRequest $vacationRequest, VacationService $service)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['valider', 'refuser'])],
            'mois' => ['nullable', 'string'],
            'observation' => ['nullable', 'string', 'max:500'],
            'motif_refus' => ['nullable', 'string', 'max:190'],
        ]);
        $refus = $data['decision'] === 'refuser';
        $obs = $refus ? trim(($data['motif_refus'] ?? '').(($data['observation'] ?? '') ? ' — '.$data['observation'] : ''), ' —') : ($data['observation'] ?? null);
        $service->decide($vacationRequest, $request->user(), ! $refus, $data['mois'] ?? null, $obs ?: null);
        $vacationRequest->refresh();

        return back()->with('ok', match ($vacationRequest->statut) {
            'transmise' => "Mois retenu à titre provisoire pour {$vacationRequest->staff->full_name} : demande transmise au Président.",
            'validee' => "Vacances de {$vacationRequest->staff->full_name} validées définitivement ({$vacationRequest->mois_accorde}).",
            default => "Demande de {$vacationRequest->staff->full_name} refusée ; l’agent est prévenu.",
        });
    }

    public function close(Request $request, VacationCampaign $campaign, VacationService $service)
    {
        $service->close($campaign, $request->user());

        return back()->with('ok', "Programmation {$campaign->annee} clôturée. Imprimez les listes définitives, signez-les, puis téléversez leur scan.");
    }

    public function reopen(Request $request, VacationCampaign $campaign, VacationService $service)
    {
        $data = $request->validate(['motif' => ['required', 'string', 'max:300']], ['motif.required' => 'Le motif de la réouverture est obligatoire.']);
        $service->reopen($campaign, $request->user(), $data['motif']);

        return back()->with('ok', "Programmation {$campaign->annee} rouverte.");
    }

    /** Liste définitive imprimable, à signer à la main. */
    public function list(Request $request, VacationList $list)
    {
        abort_unless(Authority::canView($request->user()), 403);

        return view('personnel.vacations.list', ['list' => $list->load('campaign', 'editor', 'institution')]);
    }

    public function uploadScan(Request $request, VacationList $list)
    {
        abort_unless(self::canOpen($request->user()), 403);
        $request->validate(['scan' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']], ['scan.mimes' => 'Le scan doit être un PDF ou une image (JPG, PNG).']);
        if ($list->scan_path) {
            Storage::delete($list->scan_path);
        }
        $path = $request->file('scan')->store("personnel/{$list->institution_id}/vacances");
        $list->update(['scan_path' => $path, 'scan_by' => $request->user()->id, 'scan_at' => now()]);

        return back()->with('ok', "Liste signée {$list->numero} archivée.");
    }

    public function downloadScan(Request $request, VacationList $list)
    {
        abort_unless(Authority::canView($request->user()) && $list->scan_path, 404);

        return Storage::download($list->scan_path, $list->numero.'-signee.'.pathinfo($list->scan_path, PATHINFO_EXTENSION));
    }

    /** État des validations provisoires du 1er niveau, imprimable. */
    public function provisional(Request $request, VacationCampaign $campaign)
    {
        $user = $request->user();
        abort_unless(Authority::canView($user), 403);
        $rows = $campaign->requests()->whereNotNull('first_decided_at')->with('staff.service', 'staff.category.rules', 'staff.positions', 'firstDecider')
            ->orderBy('first_decided_at')->get()->filter(fn ($r) => Approvals::canView($user, $r->staff, 'vacances'))->values();

        return view('personnel.vacations.provisional', ['campaign' => $campaign->load('institution'), 'rows' => $rows]);
    }

    public static function canOpen(User $user): bool
    {
        return $user->hasRole(Role::AdminInstitution) || Authority::holds($user, P::Chef, P::ChefAdministratif);
    }

    private function staffing(VacationRequest $r): array
    {
        if (! $r->staff->service_id) {
            return ['Service non renseigné', 'red'];
        }
        $members = VacationService::serviceMembers($r->staff->service_id);
        $max = VacationService::maxDepartures(max($members->count(), 1));
        $k = VacationRequest::where('vacation_campaign_id', $r->vacation_campaign_id)->whereIn('staff_id', $members->pluck('id'))
            ->where('statut', '!=', 'refusee')->get()->filter(fn ($x) => $x->moisEffectif() === $r->moisEffectif())->count();

        return ["{$r->staff->service->nom} : {$k}/{$max} en {$r->moisEffectif()} (effectif {$members->count()})", $k > $max ? 'amber' : 'green'];
    }
}
