<?php

namespace App\Models\Concerns;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Cloisonnement par institution : chaque requête est filtrée sur l'institution
 * de l'utilisateur connecté, sauf pour l'administrateur national et l'auditeur.
 * À la création, l'institution de l'utilisateur est renseignée automatiquement.
 */
trait BelongsToInstitution
{
    public static function bootBelongsToInstitution(): void
    {
        static::addGlobalScope('institution', function (Builder $query) {
            $user = Auth::user();
            if ($user && ! $user->role->isCrossInstitution()) {
                $query->where($query->getModel()->getTable().'.institution_id', $user->institution_id);
            }
        });

        static::creating(function ($model) {
            if (! $model->institution_id && ($user = Auth::user())) {
                $model->institution_id = $user->institution_id;
            }
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
