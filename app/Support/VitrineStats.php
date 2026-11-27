<?php

namespace App\Support;

use App\Enums\EquipmentStatus as S;
use App\Enums\RequestStatus as R;
use App\Models\Equipment;
use App\Models\Incident;
use App\Models\Institution;
use App\Models\MaterialRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Statistiques publiques de la vitrine : uniquement des totaux agrégés pour l'institution configurée,
 * mis en cache 10 minutes. Requêtes sans filtre global (visiteur non connecté), limitées à cette institution.
 */
class VitrineStats
{
    public static function for(?string $code): ?array
    {
        if (! $code || ! config('vitrine.statistiques')) {
            return null;
        }

        return Cache::remember('vitrine.stats.'.$code, 600, function () use ($code) {
            $inst = Institution::where('code', $code)->first();
            if (! $inst) {
                return null;
            }
            $eq = fn () => Equipment::withoutGlobalScopes()->where('institution_id', $inst->id);
            $actifs = $eq()->where('statut', '!=', S::Reforme->value)->count();
            $indispo = $eq()->whereIn('statut', [S::EnPanne->value, S::EnMaintenance->value])->count();
            $traitees = MaterialRequest::withoutGlobalScopes()->where('institution_id', $inst->id)
                ->whereIn('statut', [R::Accordee->value, R::Refusee->value])->whereNotNull('decided_at')->get(['created_at', 'decided_at']);
            $heures = $traitees->isEmpty() ? null
                : $traitees->avg(fn ($r) => $r->created_at->diffInMinutes($r->decided_at)) / 60;

            return [
                'equipements' => $actifs,
                'disponibilite' => $actifs ? (int) round(100 * ($actifs - $indispo) / $actifs) : null,
                'agents_equipes' => $eq()->whereNotNull('assigned_user_id')->distinct()->count('assigned_user_id'),
                'agents' => User::where('institution_id', $inst->id)->where('active', true)->count(),
                'services' => Service::withoutGlobalScopes()->where('institution_id', $inst->id)->count(),
                'demandes_traitees' => $traitees->count(),
                'delai_heures' => $heures,
                'pannes_resolues' => Incident::withoutGlobalScopes()->where('institution_id', $inst->id)->where('statut', 'resolu')->count(),
                'maj' => now()->format('d/m/Y à H:i'), // chaîne : le cache ne restaure pas les objets
            ];
        });
    }

    public static function formatDelay(?float $hours): string
    {
        if ($hours === null) {
            return '—';
        }
        if ($hours < 1) {
            return max(1, (int) round($hours * 60)).' min';
        }
        if ($hours < 48) {
            return (int) round($hours).' h';
        }

        return (int) round($hours / 24).' j';
    }
}
