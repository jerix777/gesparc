<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentStatus;
use App\Enums\IncidentStatus;
use App\Enums\RequestStatus;
use App\Models\Consumable;
use App\Models\Equipment;
use App\Models\Incident;
use App\Models\Institution;
use App\Models\MaterialRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function home()
    {
        return Gate::allows('dashboard.view') ? redirect()->route('dashboard') : redirect()->route('my-space');
    }

    public function index()
    {
        Gate::authorize('dashboard.view');

        $byStatus = Equipment::select('statut', DB::raw('count(*) as n'))->groupBy('statut')->pluck('n', 'statut');
        $byType = Equipment::join('equipment_types', 'equipment_types.id', '=', 'equipments.equipment_type_id')
            ->where('statut', '!=', EquipmentStatus::Reforme->value)
            ->select('equipment_types.nom', DB::raw('count(*) as n'))
            ->groupBy('equipment_types.nom')->orderByDesc('n')->pluck('n', 'nom');

        $oldThreshold = now()->subYears(5);

        $stats = [
            'total' => Equipment::where('statut', '!=', EquipmentStatus::Reforme->value)->count(),
            'old' => Equipment::where('statut', '!=', EquipmentStatus::Reforme->value)->whereDate('date_acquisition', '<=', $oldThreshold)->count(),
            'warranty' => Equipment::whereBetween('fin_garantie', [now()->toDateString(), now()->addDays(90)->toDateString()])->count(),
            'pending_requests' => MaterialRequest::where('statut', RequestStatus::EnAttente->value)->count(),
            'low_stock' => Consumable::low()->count(),
            'open_incidents' => Incident::whereIn('statut', [IncidentStatus::Ouvert->value, IncidentStatus::EnCours->value])->count(),
        ];

        $perInstitution = auth()->user()->role->isCrossInstitution()
            ? Institution::withCount([
                'equipments as actifs_count' => fn ($q) => $q->where('statut', '!=', EquipmentStatus::Reforme->value),
                'equipments as pannes_count' => fn ($q) => $q->whereIn('statut', [EquipmentStatus::EnPanne->value, EquipmentStatus::EnMaintenance->value]),
            ])->orderBy('nom')->get()
            : collect();

        return view('dashboard', [
            'byStatus' => $byStatus,
            'byType' => $byType,
            'stats' => $stats,
            'perInstitution' => $perInstitution,
            'latestRequests' => MaterialRequest::with('requester')->latest()->limit(5)->get(),
            'pendingRetirements' => Equipment::with(['type', 'retirementProposer'])->pendingRetirement()->orderBy('reforme_proposee_le')->limit(10)->get(),
            'latestIncidents' => Incident::with('equipment')->whereIn('statut', ['ouvert', 'en_cours'])->latest()->limit(5)->get(),
        ]);
    }

    public function mySpace()
    {
        $user = auth()->user();

        return view('my-space', [
            'equipments' => Equipment::with('type')->where('assigned_user_id', $user->id)->get(),
            'requests' => MaterialRequest::where('requester_id', $user->id)->latest()->limit(10)->get(),
        ]);
    }
}
