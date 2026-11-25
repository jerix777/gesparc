<?php

namespace App\Providers;

use App\Enums\Role as R;
use App\Models\User;
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
        Gate::define('inventory.retire', $roles(R::Informaticien, R::Responsable));
        Gate::define('requests.create', fn (User $u) => $u->institution_id !== null && ! $u->role->isCrossInstitution());
        Gate::define('requests.decide', $roles(R::Informaticien, R::GestionnaireStock));
        Gate::define('requests.view-all', $roles(R::Informaticien, R::GestionnaireStock, R::Responsable, R::AdminInstitution, R::AdminNational, R::Auditeur));
        Gate::define('incidents.manage', $roles(R::Informaticien));
        Gate::define('users.manage', $roles(R::AdminInstitution, R::AdminNational));
        Gate::define('institutions.manage', $roles(R::AdminNational));
        Gate::define('dashboard.view', $roles(R::Informaticien, R::GestionnaireStock, R::Responsable, R::AdminInstitution, R::AdminNational, R::Auditeur));
    }
}
