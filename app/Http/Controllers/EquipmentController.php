<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Institution;
use App\Models\Service;
use App\Models\User;
use App\Services\EquipmentLifecycle;
use App\Services\Numbering;
use App\Support\Qr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentController extends Controller
{
    public function __construct(private EquipmentLifecycle $lifecycle) {}

    private function filtered(Request $request)
    {
        return Equipment::with(['type', 'service', 'assignedUser', 'institution'])
            ->search($request->string('q')->toString())
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('type'), fn ($q) => $q->where('equipment_type_id', $request->type))
            ->when($request->filled('service'), fn ($q) => $q->where('service_id', $request->service))
            ->when($request->filled('institution'), fn ($q) => $q->where('equipments.institution_id', $request->institution))
            ->when($request->boolean('reforme'), fn ($q) => $q->pendingRetirement());
    }

    public function index(Request $request)
    {
        Gate::authorize('inventory.view');

        return view('equipments.index', [
            'equipments' => $this->filtered($request)->orderBy('numero_inventaire')->paginate(25)->withQueryString(),
            'types' => EquipmentType::orderBy('nom')->get(),
            'services' => Service::orderBy('nom')->get(),
            'institutions' => auth()->user()->role->isCrossInstitution() ? Institution::orderBy('nom')->get() : collect(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('inventory.view');
        $rows = $this->filtered($request)->orderBy('numero_inventaire')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
            fputcsv($out, ['Institution', 'N° inventaire', 'Type', 'Désignation', 'Marque', 'Modèle', 'N° de série', 'Statut', 'État', 'Service', 'Bureau', 'Affecté à', 'Date d\'acquisition', 'Prix (FCFA)', 'Fin de garantie'], ';');
            foreach ($rows as $e) {
                fputcsv($out, [
                    $e->institution->code, $e->numero_inventaire, $e->type->nom, $e->designation, $e->marque, $e->modele,
                    $e->numero_serie, $e->statut->label(), $e->etat->label(), $e->service?->nom, $e->bureau,
                    $e->assignedUser?->name, $e->date_acquisition?->format('d/m/Y'), $e->prix_achat, $e->fin_garantie?->format('d/m/Y'),
                ], ';');
            }
            fclose($out);
        }, 'inventaire-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create()
    {
        Gate::authorize('inventory.manage');

        return view('equipments.form', ['equipment' => new Equipment(['etat' => EquipmentCondition::Neuf]), ...$this->formData()]);
    }

    public function store(Request $request)
    {
        Gate::authorize('inventory.manage');
        $user = $request->user();
        $data = $this->validated($request);
        $data['institution_id'] = $user->institution_id;
        $data['statut'] = EquipmentStatus::EnStock;
        $data['numero_inventaire'] = ($data['numero_inventaire'] ?? null)
            ?: Numbering::nextInventory($user->institution_id, $user->institution->code);

        $equipment = Equipment::create($data);
        $this->lifecycle->recordReception($equipment);
        AuditLog::record('equipement.creation', $equipment);

        return redirect()->route('equipments.show', $equipment)->with('ok', "Équipement {$equipment->numero_inventaire} enregistré en stock.");
    }

    public function show(Equipment $equipment)
    {
        Gate::authorize('inventory.view');
        $equipment->load(['type', 'service', 'assignedUser', 'retirementProposer', 'movements.author', 'movements.toUser', 'movements.toService', 'incidents', 'institution']);

        return view('equipments.show', [
            'equipment' => $equipment,
            'agents' => User::where('institution_id', $equipment->institution_id)->where('active', true)->orderBy('name')->get(),
            'services' => Service::withoutGlobalScopes()->where('institution_id', $equipment->institution_id)->orderBy('nom')->get(),
        ]);
    }

    public function edit(Equipment $equipment)
    {
        Gate::authorize('inventory.manage');

        return view('equipments.form', ['equipment' => $equipment, ...$this->formData()]);
    }

    public function update(Request $request, Equipment $equipment)
    {
        Gate::authorize('inventory.manage');
        $data = $this->validated($request, $equipment);
        $data['numero_inventaire'] = ($data['numero_inventaire'] ?? null) ?: $equipment->numero_inventaire;
        $equipment->update($data);
        AuditLog::record('equipement.modification', $equipment);

        return redirect()->route('equipments.show', $equipment)->with('ok', 'Fiche mise à jour.');
    }

    public function label(Equipment $equipment)
    {
        Gate::authorize('inventory.view');

        // Le QR code ouvre la fiche de l'équipement (après connexion) : inventaire physique au téléphone.
        $qr = Qr::svg(route('equipments.show', $equipment));

        return view('equipments.label', ['equipment' => $equipment->load('institution', 'type'), 'qr' => $qr]);
    }

    // --- Transitions du cycle de vie ---------------------------------------------------------

    public function assign(Request $request, Equipment $equipment)
    {
        Gate::authorize('inventory.manage');
        $data = $request->validate([
            'to_user_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('institution_id', $equipment->institution_id)],
            'bureau' => ['nullable', 'string', 'max:50'],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);
        $user = isset($data['to_user_id']) ? User::findOrFail($data['to_user_id']) : null;
        $this->lifecycle->assign($equipment, $user, $data['service_id'] ?? null, $data['bureau'] ?? null, $data['motif'] ?? null);

        return back()->with('ok', 'Affectation enregistrée.');
    }

    public function release(Request $request, Equipment $equipment)
    {
        Gate::authorize('inventory.manage');
        $this->lifecycle->release($equipment, $request->input('motif'));

        return back()->with('ok', 'Équipement restitué et remis en stock.');
    }

    public function proposeRetirement(Request $request, Equipment $equipment)
    {
        Gate::authorize('inventory.manage');
        $data = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $this->lifecycle->proposeRetirement($equipment, $data['motif']);

        return back()->with('ok', 'Réforme proposée. Elle doit être validée par le responsable.');
    }

    public function approveRetirement(Request $request, Equipment $equipment)
    {
        Gate::authorize('inventory.retire');
        $data = $request->validate(['commentaire' => ['nullable', 'string', 'max:500']]);
        $this->lifecycle->approveRetirement($equipment, $data['commentaire'] ?? null);

        return back()->with('ok', "Réforme de {$equipment->numero_inventaire} validée.");
    }

    public function rejectRetirement(Request $request, Equipment $equipment)
    {
        Gate::authorize('inventory.retire');
        $data = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $this->lifecycle->rejectRetirement($equipment, $data['motif']);

        return back()->with('ok', 'Proposition de réforme rejetée.');
    }

    private function formData(): array
    {
        return [
            'types' => EquipmentType::orderBy('nom')->get(),
            'services' => Service::orderBy('nom')->get(),
            'conditions' => EquipmentCondition::cases(),
        ];
    }

    private function validated(Request $request, ?Equipment $equipment = null): array
    {
        $institutionId = $equipment?->institution_id ?? $request->user()->institution_id;

        return $request->validate([
            'numero_inventaire' => ['nullable', 'string', 'max:40',
                Rule::unique('equipments')->where('institution_id', $institutionId)->ignore($equipment?->id)],
            'equipment_type_id' => ['required', 'exists:equipment_types,id'],
            'designation' => ['required', 'string', 'max:255'],
            'marque' => ['nullable', 'string', 'max:100'],
            'modele' => ['nullable', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'caracteristiques' => ['nullable', 'string', 'max:2000'],
            'fournisseur' => ['nullable', 'string', 'max:150'],
            'date_acquisition' => ['nullable', 'date', 'before_or_equal:today'],
            'prix_achat' => ['nullable', 'integer', 'min:0'],
            'fin_garantie' => ['nullable', 'date'],
            'etat' => ['required', Rule::enum(EquipmentCondition::class)],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('institution_id', $institutionId)],
            'bureau' => ['nullable', 'string', 'max:50'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
