<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('users.manage');

        return view('admin.services.index', ['services' => Service::with('institution')->orderBy('nom')->get()]);
    }

    public function store(Request $request)
    {
        Gate::authorize('users.manage');
        $me = $request->user();
        abort_unless($me->hasRole(Role::AdminInstitution), 403, "Seul l'administrateur de l'institution crée ses services.");
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150', Rule::unique('services')->where('institution_id', $me->institution_id)],
        ]);
        Service::create($data);

        return back()->with('ok', 'Service ajouté.');
    }

    public function destroy(Service $service)
    {
        Gate::authorize('users.manage');
        $service->delete();

        return back()->with('ok', 'Service supprimé.');
    }
}
