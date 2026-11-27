<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Consumable;
use App\Models\Equipment;
use App\Models\MaterialRequest;
use App\Services\EquipmentLifecycle;
use App\Services\LoginGuard;
use App\Services\MaterialRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * API v1, utilisée par l'application TPI. Authentification : en-tête « Authorization: Bearer <jeton> ».
 * Mêmes droits et même cloisonnement par institution que l'interface web.
 */
class ApiController extends Controller
{
    public function __construct(private MaterialRequests $requests, private EquipmentLifecycle $lifecycle) {}

    public function login(Request $request, LoginGuard $guard)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'appareil' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $guard->check($data['email'], $data['password']);
        $token = $user->createToken($data['appareil'] ?? 'Application TPI', ['*'], now()->addDays(30));
        AuditLog::record('api.jeton', $user, $data['appareil'] ?? null, $user->institution_id);

        return response()->json([
            'jeton' => $token->plainTextToken,
            'expire_le' => $token->accessToken->expires_at?->toIso8601String(),
            'utilisateur' => Presenter::user($user->load('institution', 'service')),
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return ['utilisateur' => Presenter::user($request->user()->load('institution', 'service')), 'droits' => [
            'voir_inventaire' => Gate::allows('inventory.view'),
            'gerer_inventaire' => Gate::allows('inventory.manage'),
            'deposer_demande' => Gate::allows('requests.create'),
            'traiter_demandes' => Gate::allows('requests.decide'),
            'gerer_consommables' => Gate::allows('consumables.manage'),
        ]];
    }

    public function myEquipments(Request $request)
    {
        $items = Equipment::with(['type', 'service', 'assignedUser', 'institution'])
            ->where('assigned_user_id', $request->user()->id)->orderBy('numero_inventaire')->get();

        return ['donnees' => $items->map(fn ($e) => Presenter::equipment($e))];
    }

    public function equipments(Request $request)
    {
        Gate::authorize('inventory.view');
        $page = Equipment::with(['type', 'service', 'assignedUser', 'institution'])
            ->search($request->string('q')->toString())
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderBy('numero_inventaire')
            ->paginate(min(200, max(1, (int) $request->input('par_page', 50))));

        return [
            'donnees' => collect($page->items())->map(fn ($e) => Presenter::equipment($e)),
            'pagination' => ['page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total()],
        ];
    }

    public function equipment(Request $request, Equipment $equipment)
    {
        abort_unless(Gate::allows('inventory.view') || $equipment->assigned_user_id === $request->user()->id, 403);

        return ['donnees' => Presenter::equipment($equipment->load(['type', 'service', 'assignedUser', 'institution']))];
    }

    public function reportFailure(Request $request, Equipment $equipment)
    {
        abort_unless(Gate::allows('incidents.manage') || $equipment->assigned_user_id === $request->user()->id, 403);
        $data = $request->validate(['description' => ['required', 'string', 'max:2000']]);
        $incident = $this->lifecycle->reportFailure($equipment, $data['description'], $request->user());

        return response()->json(['donnees' => Presenter::incident($incident)], 201);
    }

    public function requestsIndex(Request $request)
    {
        $user = $request->user();
        $all = Gate::allows('requests.view-all') && ! $request->boolean('miennes');
        $items = MaterialRequest::with(['requester', 'decider'])
            ->when(! $all, fn ($q) => $q->where('requester_id', $user->id))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($user->hasRole(Role::GestionnaireStock) && $request->input('statut') === RequestStatus::EnAttente->value,
                fn ($q) => $q->where('categorie', 'cartouche'))
            ->latest('id')->limit(min(500, max(1, (int) $request->input('limite', 100))))->get();

        return ['donnees' => $items->map(fn ($r) => Presenter::request($r))];
    }

    public function requestsStore(Request $request)
    {
        Gate::authorize('requests.create');
        $req = $this->requests->create($request->user(), $this->requests->validateNew($request), 'api');

        return response()->json(['donnees' => Presenter::request($req->load('requester'))], 201);
    }

    public function requestsDecide(Request $request, MaterialRequest $materialRequest)
    {
        Gate::authorize('requests.decide');
        if ($denial = $this->requests->denial($request->user(), $materialRequest)) {
            return response()->json(['message' => $denial[1]], $denial[0]);
        }
        $data = $this->requests->validateDecision($request, $materialRequest);
        $this->requests->decide($request->user(), $materialRequest, $data);

        return ['donnees' => Presenter::request($materialRequest->refresh()->load(['requester', 'decider']))];
    }

    public function consumables()
    {
        Gate::authorize('inventory.view');

        return ['donnees' => Consumable::orderBy('reference')->get()->map(fn ($c) => Presenter::consumable($c))];
    }
}
