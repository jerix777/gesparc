<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('users.manage');
        $me = $request->user();

        return view('admin.users.index', [
            'users' => User::visibleTo($me)->with(['institution', 'service'])
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')))
                ->orderBy('name')->paginate(30)->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('users.manage');

        return view('admin.users.form', ['user' => new User(['active' => true, 'role' => Role::Agent]), ...$this->formData($request->user())]);
    }

    public function store(Request $request)
    {
        Gate::authorize('users.manage');
        $data = $this->validated($request);
        $user = User::create($data);
        AuditLog::record('utilisateur.creation', $user, $user->email, $user->institution_id);

        return redirect()->route('admin.users.index')->with('ok', "Compte de {$user->name} créé.");
    }

    public function edit(Request $request, User $user)
    {
        Gate::authorize('users.manage');
        $this->ensureVisible($request->user(), $user);

        return view('admin.users.form', ['user' => $user, ...$this->formData($request->user())]);
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('users.manage');
        $this->ensureVisible($request->user(), $user);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);
        AuditLog::record('utilisateur.modification', $user, null, $user->institution_id);

        return redirect()->route('admin.users.index')->with('ok', 'Compte mis à jour.');
    }

    public function resetPassword(Request $request, User $user)
    {
        Gate::authorize('users.manage');
        $this->ensureVisible($request->user(), $user);
        $temp = Str::password(12, symbols: false);
        $user->forceFill(['password' => $temp, 'failed_logins' => 0, 'locked_until' => null, 'must_change_password' => true])->save();
        AuditLog::record('utilisateur.reinitialisation', $user, null, $user->institution_id);

        return back()->with('ok', "Mot de passe provisoire de {$user->name} : {$temp} — transmettez-le à l'agent.");
    }

    private function ensureVisible(User $me, User $user): void
    {
        abort_unless($me->role->isCrossInstitution() || $user->institution_id === $me->institution_id, 404);
    }

    private function formData(User $me): array
    {
        $national = $me->hasRole(Role::AdminNational);

        return [
            'roles' => $national ? Role::cases() : Role::assignableByInstitutionAdmin(),
            'institutions' => $national ? Institution::orderBy('nom')->get() : collect([$me->institution]),
            'services' => $national ? Service::with('institution')->orderBy('nom')->get() : Service::orderBy('nom')->get(),
            'national' => $national,
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $me = $request->user();
        $national = $me->hasRole(Role::AdminNational);
        $allowedRoles = array_map(fn (Role $r) => $r->value, $national ? Role::cases() : Role::assignableByInstitutionAdmin());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'matricule' => ['nullable', 'string', 'max:30'],
            'fonction' => ['nullable', 'string', 'max:100'],
            'bureau' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in($allowedRoles)],
            'institution_id' => [$national ? 'nullable' : 'prohibited', 'exists:institutions,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ]);

        if (! $national) {
            $data['institution_id'] = $me->institution_id;
        }
        $role = Role::from($data['role']);
        if ($role->isCrossInstitution()) {
            $data['institution_id'] = null;
        } elseif (empty($data['institution_id'])) {
            abort(422, 'Ce rôle doit être rattaché à une institution.');
        }
        if (! empty($data['service_id'])) {
            $ok = Service::withoutGlobalScopes()->whereKey($data['service_id'])->where('institution_id', $data['institution_id'])->exists();
            abort_unless($ok, 422, "Le service choisi n'appartient pas à l'institution.");
        }
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
