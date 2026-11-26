<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\HasWorkflowEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationRequest extends Model
{
    use BelongsToInstitution, HasWorkflowEvents;

    public const STATUTS = [
        'en_attente' => ['En attente du 1er niveau', 'amber'],
        'transmise' => ['Transmise au Président', 'blue'],
        'validee' => ['Validée définitivement', 'green'],
        'refusee' => ['Refusée', 'red'],
    ];

    public const MOTIFS_REFUS = [
        'Nécessités de service : agent indispensable pendant la période demandée',
        'Permanence ou vacation à assurer sur le mois demandé',
        'Équilibre des effectifs du service entre août et septembre',
        'Demande hors délai',
        'Demande en double ou déjà traitée',
    ];

    protected $fillable = [
        'institution_id', 'vacation_campaign_id', 'staff_id', 'mois_demande', 'mois_retenu', 'mois_accorde', 'statut',
        'first_decider_id', 'first_decided_at', 'decider_id', 'decided_at', 'observation', 'motif_refus',
    ];

    protected function casts(): array
    {
        return ['first_decided_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(VacationCampaign::class, 'vacation_campaign_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'decider_id');
    }

    public function firstDecider(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'first_decider_id');
    }

    /** Mois en vigueur : accordé, sinon retenu au 1er niveau, sinon demandé. */
    public function moisEffectif(): string
    {
        return $this->mois_accorde ?? $this->mois_retenu ?? $this->mois_demande;
    }

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut][0] ?? $this->statut;
    }

    public function statutColor(): string
    {
        return self::STATUTS[$this->statut][1] ?? 'grey';
    }
}
