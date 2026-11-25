<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Incident;
use App\Services\EquipmentLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IncidentController extends Controller
{
    public function __construct(private EquipmentLifecycle $lifecycle) {}

    public function index(Request $request)
    {
        Gate::authorize('inventory.view');

        return view('incidents.index', [
            'incidents' => Incident::with(['equipment', 'reporter', 'institution'])
                ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
                ->latest()->paginate(25)->withQueryString(),
        ]);
    }

    /** Signalement : par l'informaticien, ou par l'agent pour un équipement qui lui est affecté. */
    public function store(Request $request, Equipment $equipment)
    {
        $user = $request->user();
        abort_unless(Gate::allows('incidents.manage') || $equipment->assigned_user_id === $user->id, 403);
        $data = $request->validate(['description' => ['required', 'string', 'max:2000']]);
        $incident = $this->lifecycle->reportFailure($equipment, $data['description'], $user);

        return back()->with('ok', "Panne {$incident->numero} signalée au Service informatique.");
    }

    public function maintenance(Request $request, Incident $incident)
    {
        Gate::authorize('incidents.manage');
        $data = $request->validate(['prestataire' => ['nullable', 'string', 'max:150']]);
        $this->lifecycle->startMaintenance($incident, $data['prestataire'] ?? null);

        return back()->with('ok', 'Équipement mis en maintenance.');
    }

    public function close(Request $request, Incident $incident)
    {
        Gate::authorize('incidents.manage');
        $data = $request->validate([
            'issue' => ['required', 'in:repare,irreparable'],
            'resolution' => ['nullable', 'string', 'max:2000'],
            'cout' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->lifecycle->closeIncident($incident, $data['issue'] === 'repare', $data['resolution'] ?? null, $data['cout'] ?? null);

        return back()->with('ok', $data['issue'] === 'repare' ? 'Panne résolue.' : 'Équipement déclaré irréparable et réformé.');
    }
}
