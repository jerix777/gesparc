<?php

namespace App\Services\Personnel;

use App\Enums\Position as P;
use App\Enums\Role;
use App\Models\Staff;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Moteur commun des circuits (absences, vacances, attestations) à partir des règles de la catégorie.
 *
 * Un circuit a au plus deux niveaux : le 1er niveau (une ou plusieurs fonctions habilitées) et la décision
 * définitive (une fonction). Un agent ne décide jamais de sa propre demande : s'il détient une fonction
 * du 1er niveau, sa demande passe directement au niveau définitif, ou au chef de l'institution.
 */
class Approvals
{
    /** @return array{first: P[], final: ?P} */
    public static function route(Staff $applicant, string $kind): array
    {
        $rule = $applicant->category?->rules->firstWhere('kind', $kind);
        $first = $rule ? $rule->firstLevelPositions() : [];
        $final = $rule?->final_level;

        if ($first && $applicant->holds(...$first)) {
            $first = [];
            $final ??= P::Chef;
        }
        if (! $first && ! $final) {
            $final = P::Chef;
        }

        return ['first' => $first, 'final' => $final];
    }

    public static function isExempt(Staff $staff, string $kind): bool
    {
        return $staff->positions->contains(fn ($p) => $kind === 'absence' ? $p->position->exemptFromAbsence() : $p->position->exemptFromVacation());
    }

    /** Statut de départ : en attente du 1er niveau, ou directement transmise au niveau définitif. */
    public static function initialStatus(Staff $applicant, string $kind): string
    {
        return self::route($applicant, $kind)['first'] ? 'en_attente' : 'transmise';
    }

    /** Fonctions habilitées à agir sur la demande dans son statut actuel. @return P[] */
    public static function positionsFor(Staff $applicant, string $kind, string $statut): array
    {
        $route = self::route($applicant, $kind);

        return match ($statut) {
            'en_attente' => $route['first'],
            'transmise' => array_filter([$route['final']]),
            default => [],
        };
    }

    /** La décision du 1er niveau est-elle définitive (aucun niveau supérieur) ? */
    public static function firstLevelIsFinal(Staff $applicant, string $kind): bool
    {
        return self::route($applicant, $kind)['final'] === null;
    }

    public static function canDecide(User $user, Staff $applicant, string $kind, string $statut): bool
    {
        $me = $user->staff;
        if (! $me || ! $me->isActive() || $me->id === $applicant->id || $me->institution_id !== $applicant->institution_id) {
            return false;
        }
        $positions = self::positionsFor($applicant, $kind, $statut);

        return $positions && $me->holds(...$positions);
    }

    /**
     * Peut consulter une demande : l'agent lui-même, les autorités de son circuit (tous niveaux et copies),
     * l'administrateur de l'institution et l'auditeur.
     */
    public static function canView(User $user, Staff $applicant, string $kind): bool
    {
        if ($user->hasRole(Role::AdminNational, Role::Auditeur)) {
            return true;
        }
        if ($user->institution_id !== $applicant->institution_id) {
            return false;
        }
        if ($user->hasRole(Role::AdminInstitution) || $user->staff?->id === $applicant->id) {
            return true;
        }
        $route = self::route($applicant, $kind);
        $rule = $applicant->category?->rules->firstWhere('kind', $kind);
        $positions = array_filter(array_merge($route['first'], [$route['final']], $rule?->copyPositions() ?? []));

        return $positions && $user->staff && $user->staff->holds(...$positions);
    }

    /** Comptes des titulaires des fonctions données (pour les notifier). */
    public static function holders(int $institutionId, array $positions): Collection
    {
        if (! $positions) {
            return collect();
        }

        return StaffPosition::withoutGlobalScopes()->where('institution_id', $institutionId)
            ->whereIn('position', array_map(fn (P $p) => $p->value, $positions))
            ->with('staff.user')->get()
            ->map(fn ($p) => $p->staff?->isActive() ? $p->staff->user : null)
            ->filter()->unique('id')->values();
    }

    /** Libellé de qui doit agir : « Greffier en Chef ou Greffier en Chef adjoint ». */
    public static function describe(array $positions): string
    {
        return implode(' ou ', array_map(fn (P $p) => $p->label(), $positions)) ?: '—';
    }
}
