<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\HasWorkflowEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Demande d'autorisation d'absence (validation électronique). */
class LeaveRequest extends Model
{
    use BelongsToInstitution, HasWorkflowEvents;

    public const STATUTS = [
        'en_attente' => ['En attente de décision', 'amber'],
        'transmise' => ['Transmise pour décision définitive', 'blue'],
        'accordee' => ['Accordée', 'green'],
        'refusee' => ['Refusée', 'red'],
        'annulee' => ['Annulée', 'grey'],
    ];

    /** Motifs repris de l'application d'origine. */
    public const MOTIFS = [
        'Santé' => ['Maladie', 'Consultation ou rendez-vous médical', 'Examens médicaux ou soins', "Accompagnement d'un enfant ou d'un proche à un rendez-vous médical"],
        'Événements familiaux' => ["Mariage de l'agent", "Naissance d'un enfant", "Décès d'un membre de la famille ou d'un proche", 'Participation à des obsèques', 'Cérémonie familiale (mariage, baptême, etc.)', 'Obligations familiales urgentes'],
        'Démarches et obligations officielles' => ['Démarches administratives personnelles', 'Convocation par une administration', 'Convocation judiciaire ou par une autorité officielle', 'Examen ou concours', 'Formation ou stage', 'Obligation civique ou officielle', 'Participation à une cérémonie officielle ou institutionnelle'],
        'Autres situations' => ['Motif personnel', 'Motif personnel exceptionnel', 'Autre motif'],
    ];

    public const MOTIFS_REFUS = [
        'Nécessités de service : présence indispensable aux dates demandées',
        'Période incompatible avec le calendrier des audiences',
        'Effectif insuffisant dans le service pendant cette période',
        'Dossier incomplet ou justificatif manquant (ex. certificat médical)',
        'Demande tardive : délai de prévenance non respecté',
        'Durée demandée excessive : à réduire ou à fractionner',
        'Demande en double ou déjà traitée',
    ];

    protected $fillable = [
        'institution_id', 'numero', 'staff_id', 'created_by', 'motif', 'precision', 'debut', 'fin', 'jours_ouvres',
        'jours_calendaires', 'statut', 'first_decider_id', 'first_decided_at', 'decider_id', 'decider_fonction',
        'signe_po', 'decided_at', 'motif_refus', 'verification_code',
    ];

    protected function casts(): array
    {
        return ['debut' => 'date', 'fin' => 'date', 'first_decided_at' => 'datetime', 'decided_at' => 'datetime', 'signe_po' => 'boolean'];
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

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut][0] ?? $this->statut;
    }

    public function statutColor(): string
    {
        return self::STATUTS[$this->statut][1] ?? 'grey';
    }

    public function isOpen(): bool
    {
        return in_array($this->statut, ['en_attente', 'transmise'], true);
    }

    public static function allMotifs(): array
    {
        return array_merge(...array_values(self::MOTIFS));
    }
}
