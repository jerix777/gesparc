<?php

namespace App\Services\Personnel;

use App\Enums\Position as P;
use App\Enums\Role as R;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Droits du module Personnel. Ils combinent le rôle SIGACI (administrateur, auditeur…)
 * et les fonctions d'autorité attribuées à la fiche de l'utilisateur (Président, Greffier en Chef…).
 */
class Authority
{
    /** @return Collection<int, P> */
    public static function positions(User $user): Collection
    {
        $staff = $user->relationLoaded('staff') ? $user->staff : $user->staff()->with('positions')->first();
        $user->setRelation('staff', $staff);

        return $staff ? $staff->positions->pluck('position') : collect();
    }

    public static function holds(User $user, P ...$positions): bool
    {
        $held = self::positions($user);

        return collect($positions)->contains(fn ($p) => $held->contains($p));
    }

    public static function canView(User $user): bool
    {
        return $user->hasRole(R::AdminInstitution, R::AdminNational, R::Auditeur)
            || self::holds($user, P::Chef, P::ChefParquet, P::ChefAdministratif, P::AdjointAdministratif, P::Secretaire);
    }

    public static function canConfigure(User $user): bool
    {
        return $user->hasRole(R::AdminInstitution, R::AdminNational);
    }

    /** Peut créer des fiches (dans au moins une catégorie). */
    public static function canCreate(User $user): bool
    {
        return $user->institution_id !== null && ($user->hasRole(R::AdminInstitution) || self::holds($user, P::Chef, P::ChefParquet, P::ChefAdministratif));
    }

    /**
     * Qui a la main sur une fiche : le Président sur tout le tribunal, le Procureur sur les magistrats du parquet
     * (sauf sa propre fiche), le Greffier en Chef sur le personnel non magistrat ; l'administrateur partout.
     */
    public static function canManage(User $user, Staff $staff): bool
    {
        if ($user->hasRole(R::AdminNational)) {
            return true;
        }
        if ($user->institution_id !== $staff->institution_id) {
            return false;
        }
        if ($user->hasRole(R::AdminInstitution) || self::holds($user, P::Chef)) {
            return true;
        }
        $category = $staff->category;
        if (self::holds($user, P::ChefParquet) && $category?->code === 'magistrats_parquet') {
            return $staff->user_id !== $user->id;
        }

        return self::holds($user, P::ChefAdministratif) && $category && ! $category->magistrat;
    }
}
