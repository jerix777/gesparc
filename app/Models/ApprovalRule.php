<?php

namespace App\Models;

use App\Enums\Position;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qui décide pour une catégorie de personnel et un type de demande :
 * - first_level : fonctions habilitées à décider (ou à valider provisoirement s'il existe un niveau final) ;
 * - final_level : fonction qui décide définitivement (null = la décision de 1er niveau est définitive) ;
 * - copy_to : fonctions informées de la décision.
 */
class ApprovalRule extends Model
{
    use BelongsToInstitution;

    public const KINDS = ['absence' => "Autorisation d'absence", 'vacances' => 'Vacances judiciaires', 'attestation' => 'Attestation de prise de service'];

    protected $fillable = ['institution_id', 'staff_category_id', 'kind', 'first_level', 'final_level', 'copy_to'];

    protected function casts(): array
    {
        return ['first_level' => 'array', 'copy_to' => 'array', 'final_level' => Position::class];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StaffCategory::class, 'staff_category_id');
    }

    /** @return Position[] */
    public function firstLevelPositions(): array
    {
        return array_map(fn ($p) => Position::from($p), $this->first_level ?? []);
    }

    /** @return Position[] */
    public function copyPositions(): array
    {
        return array_map(fn ($p) => Position::from($p), $this->copy_to ?? []);
    }

    public function describe(): string
    {
        $first = implode(' ou ', array_map(fn (Position $p) => $p->label(), $this->firstLevelPositions()));
        $final = $this->final_level?->label();

        return match (true) {
            $first && $final => "{$first}, puis {$final}",
            (bool) $first => $first,
            (bool) $final => $final,
            default => 'Non défini',
        };
    }
}
