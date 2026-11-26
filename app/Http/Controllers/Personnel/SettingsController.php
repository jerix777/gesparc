<?php

namespace App\Http\Controllers\Personnel;

use App\Enums\Position;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRule;
use App\Models\AuditLog;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\StaffPosition;
use App\Services\Personnel\PersonnelSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Paramétrage du module Personnel : fonctions d'autorité et règles de décision par catégorie. */
class SettingsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('personnel.configure');

        return view('personnel.settings.index', [
            'categories' => StaffCategory::with('rules')->withCount(['staff' => fn ($q) => $q->active()])->orderBy('ordre')->get(),
            'holders' => StaffPosition::with('staff')->get()->groupBy(fn ($p) => $p->position->value),
            'staff' => Staff::active()->orderBy('nom')->get(['id', 'nom', 'prenoms', 'fonction']),
            'institution' => $request->user()->institution,
        ]);
    }

    public function preset(Request $request)
    {
        Gate::authorize('personnel.configure');
        $institution = $request->user()->institution ?? abort(422, 'Choisissez une institution.');
        PersonnelSetup::applyJuridiction($institution);
        AuditLog::record('personnel.parametrage', $institution, 'Modèle juridiction appliqué');

        return back()->with('ok', 'Catégories et règles du modèle « juridiction » ajoutées (les règles déjà réglées sont conservées).');
    }

    public function assign(Request $request)
    {
        Gate::authorize('personnel.configure');
        $data = $request->validate([
            'staff_id' => ['required', Rule::exists('staff', 'id')->where('institution_id', $request->user()->institution_id)],
            'position' => ['required', Rule::enum(Position::class)],
        ]);
        $position = Position::from($data['position']);
        if ($position->isUnique()) {
            StaffPosition::where('position', $position)->delete(); // un seul titulaire : la nomination remplace l'ancien
        }
        $p = StaffPosition::firstOrCreate(['staff_id' => $data['staff_id'], 'position' => $position]);
        AuditLog::record('personnel.fonction-attribuee', $p->staff, $position->label());

        return back()->with('ok', "{$p->staff->full_name} : {$position->label()}.");
    }

    public function unassign(StaffPosition $position)
    {
        Gate::authorize('personnel.configure');
        AuditLog::record('personnel.fonction-retiree', $position->staff, $position->position->label());
        $position->delete();

        return back()->with('ok', 'Fonction retirée.');
    }

    public function updateRule(Request $request, StaffCategory $category, string $kind)
    {
        Gate::authorize('personnel.configure');
        abort_unless(array_key_exists($kind, ApprovalRule::KINDS), 404);
        $data = $request->validate([
            'first_level' => ['array'], 'first_level.*' => [Rule::enum(Position::class)],
            'final_level' => ['nullable', Rule::enum(Position::class)],
            'copy_to' => ['array'], 'copy_to.*' => [Rule::enum(Position::class)],
        ]);
        if (empty($data['first_level']) && empty($data['final_level'])) {
            return back()->withErrors(['first_level' => 'Indiquez au moins une autorité qui décide.']);
        }
        ApprovalRule::updateOrCreate(
            ['staff_category_id' => $category->id, 'kind' => $kind],
            ['institution_id' => $category->institution_id, 'first_level' => array_values($data['first_level'] ?? []),
                'final_level' => $data['final_level'] ?? null, 'copy_to' => array_values($data['copy_to'] ?? [])],
        );
        AuditLog::record('personnel.regle', $category, $category->nom.' / '.ApprovalRule::KINDS[$kind]);

        return back()->with('ok', "Règle mise à jour : {$category->nom}, ".mb_strtolower(ApprovalRule::KINDS[$kind]).'.');
    }
}
