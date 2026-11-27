<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Liste définitive figée à la clôture ; elle est imprimée, signée à la main, puis numérisée. */
class VacationList extends Model
{
    use BelongsToInstitution;

    protected $fillable = ['institution_id', 'vacation_campaign_id', 'numero', 'mois', 'lignes', 'edited_by', 'scan_path', 'scan_by', 'scan_at'];

    protected function casts(): array
    {
        return ['lignes' => 'array', 'scan_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(VacationCampaign::class, 'vacation_campaign_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'edited_by');
    }

    public function titre(): string
    {
        return match ($this->mois) {
            'Août' => 'Mois d’août',
            'Septembre' => 'Mois de septembre',
            default => 'Août et septembre',
        };
    }
}
