<?php

namespace App\Http\Controllers\Personnel;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\User;
use App\Services\Personnel\Authority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('personnel.view');
        $statut = $request->input('statut', 'actif');

        $staff = Staff::with(['category', 'service', 'positions', 'user'])
            ->search($request->input('q'))
            ->when($request->filled('categorie'), fn ($q) => $q->where('staff_category_id', $request->integer('categorie')))
            ->when($request->filled('service'), fn ($q) => $q->where('service_id', $request->integer('service')))
            ->when($statut !== 'tous', fn ($q) => $q->where('statut', $statut))
            ->when($request->boolean('incomplet'), fn ($q) => $q->where(fn ($w) => $w->whereNull('service_id')->orWhereNull('staff_category_id')))
            ->orderBy('nom')->orderBy('prenoms')
            ->paginate(30)->withQueryString();

        return view('personnel.staff.index', [
            'staff' => $staff,
            'categories' => StaffCategory::orderBy('ordre')->get(),
            'services' => Service::orderBy('nom')->get(),
            'counts' => Staff::active()->selectRaw('staff_category_id, count(*) as n')->groupBy('staff_category_id')->pluck('n', 'staff_category_id'),
            'incomplete' => Staff::active()->where(fn ($q) => $q->whereNull('service_id')->orWhereNull('staff_category_id'))->count(),
        ]);
    }

    public function show(Staff $staff)
    {
        Gate::authorize('personnel.view');
        $staff->load(['category.rules', 'service', 'positions', 'user']);

        return view('personnel.staff.show', ['s' => $staff, 'canManage' => Authority::canManage(auth()->user(), $staff)]);
    }

    public function create(Request $request)
    {
        Gate::authorize('personnel.create');

        return view('personnel.staff.form', $this->formData($request->user(), new Staff(['statut' => 'actif'])));
    }

    public function store(Request $request)
    {
        Gate::authorize('personnel.create');
        $staff = new Staff($this->validated($request));
        $this->ensureCategoryAllowed($request->user(), $staff);
        $staff->save();
        AuditLog::record('personnel.creation', $staff, $staff->full_name);

        return redirect()->route('personnel.staff.show', $staff)->with('ok', "Fiche de {$staff->full_name} créée.");
    }

    public function edit(Request $request, Staff $staff)
    {
        Gate::authorize('personnel.manage', $staff);

        return view('personnel.staff.form', $this->formData($request->user(), $staff));
    }

    public function update(Request $request, Staff $staff)
    {
        Gate::authorize('personnel.manage', $staff);
        $staff->fill($this->validated($request, $staff));
        $this->ensureCategoryAllowed($request->user(), $staff);
        $staff->save();
        $staff->user?->update(['matricule' => $staff->matricule, 'fonction' => $staff->fonction, 'bureau' => $staff->bureau, 'service_id' => $staff->service_id]);
        AuditLog::record('personnel.modification', $staff, $staff->full_name);

        return redirect()->route('personnel.staff.show', $staff)->with('ok', 'Fiche mise à jour.');
    }

    /** Sortie du personnel (mutation, retraite…) : la fiche est conservée, le compte est désactivé. */
    public function leave(Request $request, Staff $staff)
    {
        Gate::authorize('personnel.manage', $staff);
        $data = $request->validate([
            'date_sortie' => ['required', 'date'],
            'motif_sortie' => ['required', 'string', 'max:190'],
        ]);
        DB::transaction(function () use ($staff, $data) {
            $staff->update($data + ['statut' => 'sorti']);
            $staff->user?->update(['active' => false]);
        });
        AuditLog::record('personnel.sortie', $staff, $data['motif_sortie']);

        return back()->with('ok', "{$staff->full_name} est enregistré(e) comme sorti(e) des effectifs.");
    }

    public function reinstate(Staff $staff)
    {
        Gate::authorize('personnel.manage', $staff);
        $staff->update(['statut' => 'actif', 'date_sortie' => null, 'motif_sortie' => null]);
        $staff->user?->update(['active' => true]);
        AuditLog::record('personnel.reintegration', $staff);

        return back()->with('ok', "{$staff->full_name} est de nouveau dans les effectifs.");
    }

    /**
     * Ouvre le compte de l'agent : identifiant = matricule (ou e-mail), mot de passe provisoire affiché une fois,
     * à changer à la première connexion. Remplace la création libre avec le numéro de téléphone.
     */
    public function createAccount(Request $request, Staff $staff)
    {
        Gate::authorize('personnel.manage', $staff);
        abort_if($staff->user_id || ! $staff->isActive(), 422, 'Cet agent a déjà un compte ou n\'est plus dans les effectifs.');
        if (! $staff->matricule && ! $staff->email) {
            return back()->withErrors(['matricule' => 'Renseignez un matricule (ou un identifiant) ou une adresse e-mail dans la fiche avant d\'ouvrir le compte.']);
        }
        if ($staff->matricule && User::where('matricule', $staff->matricule)->exists()) {
            return back()->withErrors(['matricule' => 'Ce matricule est déjà utilisé par un autre compte.']);
        }
        if ($staff->email && User::where('email', $staff->email)->exists()) {
            return back()->withErrors(['email' => 'Cette adresse e-mail est déjà utilisée par un autre compte.']);
        }

        $temp = Str::password(10, symbols: false);
        DB::transaction(function () use ($staff, $temp) {
            $user = User::create([
                'institution_id' => $staff->institution_id, 'service_id' => $staff->service_id,
                'name' => $staff->full_name, 'email' => $staff->email, 'matricule' => $staff->matricule,
                'fonction' => $staff->fonction, 'bureau' => $staff->bureau, 'role' => Role::Agent,
                'password' => $temp, 'active' => true, 'must_change_password' => true,
            ]);
            $staff->update(['user_id' => $user->id]);
            AuditLog::record('personnel.ouverture-compte', $staff, $staff->full_name);
        });
        $login = $staff->matricule ?: $staff->email;

        return back()->with('ok', "Compte ouvert. Identifiant : {$login} — mot de passe provisoire : {$temp}. Remettez-les à l'agent : il devra changer le mot de passe à sa première connexion.");
    }

    private function formData(User $user, Staff $staff): array
    {
        $categories = StaffCategory::orderBy('ordre')->get()
            ->filter(fn ($c) => Authority::canManage($user, new Staff(['institution_id' => $user->institution_id ?? $staff->institution_id, 'staff_category_id' => $c->id]))
                || ($staff->exists && $staff->staff_category_id === $c->id));

        return ['s' => $staff, 'categories' => $categories, 'services' => Service::orderBy('nom')->get()];
    }

    private function ensureCategoryAllowed(User $user, Staff $staff): void
    {
        $staff->institution_id ??= $user->institution_id;
        $staff->setRelation('category', StaffCategory::find($staff->staff_category_id));
        abort_unless(Authority::canManage($user, $staff), 403, "Cette catégorie de personnel relève d'une autre autorité.");
    }

    private function validated(Request $request, ?Staff $staff = null): array
    {
        $institution = $staff?->institution_id ?? $request->user()->institution_id;

        return $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'prenoms' => ['nullable', 'string', 'max:160'],
            'matricule' => ['nullable', 'string', 'max:40', Rule::unique('staff')->where('institution_id', $institution)->ignore($staff?->id)],
            'staff_category_id' => ['required', Rule::exists('staff_categories', 'id')->where('institution_id', $institution)],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('institution_id', $institution)],
            'fonction' => ['nullable', 'string', 'max:120'],
            'telephone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:190'],
            'bureau' => ['nullable', 'string', 'max:40'],
            'date_prise_service' => ['nullable', 'date'],
        ]);
    }
}
