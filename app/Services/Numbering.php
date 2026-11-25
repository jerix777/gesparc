<?php

namespace App\Services;

use App\Models\Equipment;

/**
 * Numéros lisibles par institution et par année : DEM-2026-001, PAN-2026-014…
 */
class Numbering
{
    public static function next(string $modelClass, string $prefix, int $institutionId): string
    {
        $year = now()->year;
        $base = "{$prefix}-{$year}-";
        $last = $modelClass::withoutGlobalScopes()
            ->where('institution_id', $institutionId)
            ->where('numero', 'like', $base.'%')
            ->orderByDesc('id')
            ->value('numero');
        $n = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    public static function nextInventory(int $institutionId, string $institutionCode): string
    {
        $base = strtoupper($institutionCode).'-';
        $last = Equipment::withoutGlobalScopes()
            ->where('institution_id', $institutionId)
            ->where('numero_inventaire', 'like', $base.'%')
            ->pluck('numero_inventaire')
            ->map(fn ($n) => (int) substr($n, strlen($base)))
            ->max() ?? 0;

        return $base.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }
}
