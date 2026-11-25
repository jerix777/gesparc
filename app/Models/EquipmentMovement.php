<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMovement extends Model
{
    use BelongsToInstitution;

    public const UPDATED_AT = null;

    public const TYPES = [
        'reception' => 'Réception',
        'affectation' => 'Affectation',
        'transfert' => 'Transfert',
        'restitution' => 'Restitution',
        'panne' => 'Panne signalée',
        'maintenance' => 'Mise en maintenance',
        'reparation' => 'Réparation',
        'reforme' => 'Réforme',
        'import' => 'Import',
    ];

    protected $fillable = [
        'institution_id', 'equipment_id', 'type', 'statut_avant', 'statut_apres', 'from_user_id', 'to_user_id',
        'to_service_id', 'to_bureau', 'motif', 'author_id',
    ];

    protected $casts = [
        'statut_avant' => EquipmentStatus::class,
        'statut_apres' => EquipmentStatus::class,
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function toService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'to_service_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
