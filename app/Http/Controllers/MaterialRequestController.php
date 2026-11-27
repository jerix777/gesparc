<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Consumable;
use App\Models\MaterialRequest;
use App\Services\MaterialRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MaterialRequestController extends Controller
{
    public function __construct(private MaterialRequests $requests) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $all = Gate::allows('requests.view-all');
        $query = MaterialRequest::with(['requester', 'decider', 'institution', 'consumable'])
            ->when(! $all, fn ($q) => $q->where('requester_id', $user->id));

        $pending = (clone $query)->where('statut', RequestStatus::EnAttente->value)
            ->when($user->hasRole(Role::GestionnaireStock), fn ($q) => $q->where('categorie', 'cartouche'))
            ->oldest()->get();

        return view('requests.index', [
            'pending' => $pending,
            'history' => (clone $query)->where('statut', '!=', RequestStatus::EnAttente->value)->latest()->paginate(20),
            'all' => $all,
            'consumables' => Gate::allows('requests.decide') ? Consumable::orderBy('reference')->get() : collect(),
        ]);
    }

    public function create()
    {
        Gate::authorize('requests.create');

        return view('requests.create', ['materiels' => MaterialRequest::MATERIELS]);
    }

    public function store(Request $request)
    {
        Gate::authorize('requests.create');
        $req = $this->requests->create($request->user(), $this->requests->validateNew($request));

        return redirect()->route('requests.index')->with('ok', "Votre demande n° {$req->numero} a été transmise au Service informatique.");
    }

    public function decide(Request $request, MaterialRequest $materialRequest)
    {
        Gate::authorize('requests.decide');
        if ($denial = $this->requests->denial($request->user(), $materialRequest)) {
            abort($denial[0], $denial[1]);
        }
        $data = $this->requests->validateDecision($request, $materialRequest);
        $this->requests->decide($request->user(), $materialRequest, $data);

        return back()->with('ok', "Demande {$materialRequest->numero} ".($data['decision'] === 'accorder' ? 'accordée.' : 'refusée.'));
    }
}
