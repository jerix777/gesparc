<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use BelongsToInstitution;

    protected $fillable = [
        'institution_id', 'numero', 'equipment_id', 'reported_by', 'description', 'statut', 'prestataire',
        'cout', 'resolution', 'resolved_at',
    ];

    protected $casts = [
        'statut' => IncidentStatus::class,
        'resolved_at' => 'datetime',
        'cout' => 'integer',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
