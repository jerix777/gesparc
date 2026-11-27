<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InstitutionController extends Controller
{
    public function index()
    {
        Gate::authorize('institutions.manage');

        return view('admin.institutions.index', [
            'institutions' => Institution::withCount(['users', 'equipments'])->orderBy('nom')->get(),
        ]);
    }

    public function create()
    {
        Gate::authorize('institutions.manage');

        return view('admin.institutions.form', ['institution' => new Institution(['active' => true])]);
    }

    public function store(Request $request)
    {
        Gate::authorize('institutions.manage');
        $institution = Institution::create($this->validated($request));
        AuditLog::record('institution.creation', $institution, null, $institution->id);

        return redirect()->route('admin.institutions.index')->with('ok', "Institution {$institution->nom} créée. Créez maintenant son administrateur.");
    }

    public function edit(Institution $institution)
    {
        Gate::authorize('institutions.manage');

        return view('admin.institutions.form', compact('institution'));
    }

    public function update(Request $request, Institution $institution)
    {
        Gate::authorize('institutions.manage');
        $institution->update($this->validated($request, $institution));
        AuditLog::record('institution.modification', $institution, null, $institution->id);

        return redirect()->route('admin.institutions.index')->with('ok', 'Institution mise à jour.');
    }

    private function validated(Request $request, ?Institution $institution = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('institutions')->ignore($institution?->id)],
            'nom' => ['required', 'string', 'max:255'],
            'categorie' => ['nullable', 'string', 'max:100'],
            'ville' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:30'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
