<?php

namespace App\Providers;

use App\Enums\Role as R;
use App\Models\Staff;
use App\Models\User;
use App\Services\Personnel\Authority;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        // Droits d'accès : voir le tableau du cahier des charges (docs/ARCHITECTURE.md).
        $roles = fn (R ...$roles) => fn (User $u) => $u->hasRole(...$roles);

        Gate::define('inventory.view', $roles(R::Informaticien, R::GestionnaireStock, R::Responsable, R::AdminInstitution, R::AdminNational, R::Auditeur));
        Gate::define('inventory.manage', $roles(R::Informaticien));
        Gate::define('inventory.retire', $roles(R::Responsable)); // valide ou rejette une réforme proposée
        Gate::define('requests.create', fn (User $u) => $u->institution_id !== null && ! $u->role->isCrossInstitution());
        Gate::define('requests.decide', $roles(R::Informaticien, R::GestionnaireStock));
        Gate::define('requests.view-all', $roles(R::Informaticien, R::GestionnaireStock, R::Responsable, R::AdminInstitution, R::AdminNational, R::Auditeur));
        Gate::define('consumables.manage', $roles(R::Informaticien, R::GestionnaireStock));
        Gate::define('incidents.manage', $roles(R::Informaticien));
        Gate::define('users.manage', $roles(R::AdminInstitution, R::AdminNational));
        Gate::define('institutions.manage', $roles(R::AdminNational));
        // Module Personnel : rôle GesParc + fonctions d'autorité de la fiche (App\Services\Personnel\Authority).
        Gate::define('personnel.view', fn (User $u) => Authority::canView($u));
        Gate::define('personnel.create', fn (User $u) => Authority::canCreate($u));
        Gate::define('personnel.manage', fn (User $u, Staff $s) => Authority::canManage($u, $s));
        Gate::define('personnel.configure', fn (User $u) => Authority::canConfigure($u));
        Gate::define('dashboard.view', $roles(R::Informaticien, R::GestionnaireStock, R::Responsable, R::AdminInstitution, R::AdminNational, R::Auditeur));
    }
}
