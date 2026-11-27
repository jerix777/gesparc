<?php

namespace App\Http\Controllers\Personnel;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Services\Personnel\Approvals;
use App\Services\Personnel\Authority;
use App\Services\Personnel\LeaveService;
use App\Support\Qr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $me = $user->staff;
        $with = ['staff.category.rules', 'staff.positions', 'staff.service'];

        $toDecide = LeaveRequest::with($with)->whereIn('statut', ['en_attente', 'transmise'])->orderBy('debut')->get()
            ->filter(fn ($l) => Approvals::canDecide($user, $l->staff, 'absence', $l->statut))->values();
        $register = LeaveRequest::with($with + ['decider'])->whereIn('statut', ['accordee', 'refusee'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('staff', fn ($s) => $s->search($request->input('q')))->orWhere('numero', $request->input('q')))
            ->latest('decided_at')->limit(300)->get()
            ->filter(fn ($l) => $l->staff_id !== $me?->id && Approvals::canView($user, $l->staff, 'absence'))->take(100)->values();

        return view('personnel.absences.index', [
            'mine' => $me ? LeaveRequest::where('staff_id', $me->id)->latest()->get() : collect(),
            'me' => $me,
            'toDecide' => $toDecide,
            'register' => $register,
            'canRequest' => $me && $me->isActive() && ! Approvals::isExempt($me->load('positions'), 'absence'),
            'granted' => $me ? LeaveService::grantedDays($me, now()->year) : 0,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('personnel.absences.create', [
            'me' => $user->staff,
            // Une autorité (ou l'administrateur) peut saisir la demande d'un agent de son ressort.
            'agents' => Staff::with('category', 'positions')->active()->orderBy('nom')->get()
                ->filter(fn ($s) => $s->id !== $user->staff?->id && Authority::canManage($user, $s) && ! Approvals::isExempt($s, 'absence'))->values(),
        ]);
    }

    public function store(Request $request, LeaveService $service)
    {
        $user = $request->user();
        $data = $request->validate([
            'staff_id' => ['nullable', 'integer'],
            'motif' => ['required', Rule::in(LeaveRequest::allMotifs())],
            'precision' => [Rule::requiredIf($request->input('motif') === 'Autre motif'), 'nullable', 'string', 'max:250'],
            'debut' => ['required', 'date', 'after_or_equal:'.now()->subDays(30)->toDateString()],
            'fin' => ['required', 'date', 'after_or_equal:debut'],
        ], ['precision.required' => 'Précisez le motif de l’absence.']);

        if (! empty($data['staff_id']) && (int) $data['staff_id'] !== $user->staff?->id) {
            $staff = Staff::findOrFail($data['staff_id']);
            abort_unless(Authority::canManage($user, $staff), 403);
        } else {
            $staff = $user->staff ?? abort(403, 'Votre compte n’est relié à aucune fiche du personnel.');
        }
        $leave = $service->submit($staff, $data, $user);

        return redirect()->route('personnel.absences.show', $leave)->with('ok', "Demande {$leave->numero} transmise à : "
            .Approvals::describe(Approvals::positionsFor($staff, 'absence', $leave->statut)).'.');
    }

    public function show(Request $request, LeaveRequest $leave)
    {
        $user = $request->user();
        abort_unless(Approvals::canView($user, $leave->staff, 'absence'), 403);
        $leave->load(['staff.category.rules', 'staff.service', 'staff.positions', 'events', 'decider', 'firstDecider']);

        return view('personnel.absences.show', [
            'l' => $leave,
            'canDecide' => Approvals::canDecide($user, $leave->staff, 'absence', $leave->statut),
            'nextLevel' => $leave->isOpen() ? Approvals::describe(Approvals::positionsFor($leave->staff, 'absence', $leave->statut)) : null,
            'willTransmit' => $leave->statut === 'en_attente' && ! Approvals::firstLevelIsFinal($leave->staff, 'absence'),
            'granted' => LeaveService::grantedDays($leave->staff, $leave->debut->year),
        ]);
    }

    public function decide(Request $request, LeaveRequest $leave, LeaveService $service)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accorder', 'refuser'])],
            'motif_refus' => ['nullable', 'string', 'max:190'],
            'precision_refus' => ['nullable', 'string', 'max:250'],
        ]);
        $accept = $data['decision'] === 'accorder';
        $motif = null;
        if (! $accept) {
            $motif = trim(($data['motif_refus'] ?? '').(($data['precision_refus'] ?? '') ? ' — '.$data['precision_refus'] : ''), ' —');
            if ($motif === '') {
                return back()->withErrors(['motif_refus' => 'Choisissez ou précisez le motif du refus.']);
            }
        }
        $service->decide($leave, $request->user(), $accept, $motif);
        $leave->refresh();

        return back()->with('ok', match ($leave->statut) {
            'transmise' => 'Avis favorable enregistré : la demande est transmise pour décision définitive.',
            'accordee' => 'Absence accordée. L’agent est prévenu et peut imprimer la décision.',
            default => 'Demande refusée. L’agent est prévenu avec le motif.',
        });
    }

    public function cancel(Request $request, LeaveRequest $leave, LeaveService $service)
    {
        $service->cancel($leave, $request->user());

        return back()->with('ok', 'Demande annulée.');
    }

    /** Décision imprimable, avec code QR de vérification. */
    public function document(Request $request, LeaveRequest $leave)
    {
        abort_unless(Approvals::canView($request->user(), $leave->staff, 'absence') && $leave->statut === 'accordee', 404);
        $leave->load('staff.category', 'staff.service', 'decider', 'institution');

        return view('personnel.absences.document', ['l' => $leave, 'qr' => Qr::svg(route('verification', $leave->verification_code))]);
    }
}
