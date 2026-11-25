<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\MaterialRequest;
use App\Services\Numbering;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MaterialRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $all = Gate::allows('requests.view-all');
        $query = MaterialRequest::with(['requester', 'decider', 'institution'])
            ->when(! $all, fn ($q) => $q->where('requester_id', $user->id));

        $pending = (clone $query)->where('statut', RequestStatus::EnAttente->value)
            ->when($user->hasRole(Role::GestionnaireStock), fn ($q) => $q->where('categorie', 'cartouche'))
            ->oldest()->get();

        return view('requests.index', [
            'pending' => $pending,
            'history' => (clone $query)->where('statut', '!=', RequestStatus::EnAttente->value)->latest()->paginate(20),
            'all' => $all,
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
        $user = $request->user();
        $data = $request->validate([
            'materiel' => ['required', Rule::in(MaterialRequest::MATERIELS)],
            'quantite' => ['required', 'integer', 'between:1,99'],
            'caracteristiques' => ['nullable', 'string', 'max:500', 'required_if:materiel,Autres accessoires informatiques'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ], ['caracteristiques.required_if' => 'Précisez l’accessoire demandé.']);

        $req = MaterialRequest::create($data + [
            'institution_id' => $user->institution_id,
            'numero' => Numbering::next(MaterialRequest::class, 'DEM', $user->institution_id),
            'requester_id' => $user->id,
            'categorie' => $data['materiel'] === 'Cartouche d’encre' ? 'cartouche' : 'materiel',
            'bureau' => $user->bureau,
        ]);
        AuditLog::record('demande.creation', $req);

        return redirect()->route('requests.index')->with('ok', "Votre demande n° {$req->numero} a été transmise au Service informatique.");
    }

    public function decide(Request $request, MaterialRequest $materialRequest)
    {
        Gate::authorize('requests.decide');
        $user = $request->user();
        abort_if($materialRequest->statut !== RequestStatus::EnAttente, 422, 'Cette demande est déjà clôturée.');
        abort_if($materialRequest->requester_id === $user->id, 403, 'Vous ne pouvez pas traiter votre propre demande.');
        abort_if($user->hasRole(Role::GestionnaireStock) && $materialRequest->categorie !== 'cartouche', 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accorder', 'refuser'])],
            'motif_refus' => ['nullable', 'required_if:decision,refuser', 'string', 'max:500'],
        ], ['motif_refus.required_if' => 'Le motif du refus est obligatoire.']);

        $accorder = $data['decision'] === 'accorder';
        $materialRequest->update([
            'statut' => $accorder ? RequestStatus::Accordee : RequestStatus::Refusee,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'motif_refus' => $accorder ? null : $data['motif_refus'],
        ]);
        AuditLog::record('demande.'.($accorder ? 'accord' : 'refus'), $materialRequest, $data['motif_refus'] ?? null);

        return back()->with('ok', "Demande {$materialRequest->numero} ".($accorder ? 'accordée.' : 'refusée.'));
    }
}
