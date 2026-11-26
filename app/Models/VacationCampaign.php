<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\HasWorkflowEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VacationCampaign extends Model
{
    use BelongsToInstitution, HasWorkflowEvents;

    public const MOIS = ['Août', 'Septembre'];

    protected $fillable = ['institution_id', 'annee', 'mois', 'statut', 'closed_by', 'closed_at', 'reouvertures'];

    protected function casts(): array
    {
        return ['mois' => 'array', 'closed_at' => 'datetime'];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(VacationList::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->statut === 'ouverte';
    }

    public static function periode(?string $mois, int $annee): string
    {
        return match ($mois) {
            'Août' => "du 1er au 31 août {$annee}",
            'Septembre' => "du 1er au 30 septembre {$annee}",
            default => "du 1er août au 30 septembre {$annee}",
        };
    }
}
