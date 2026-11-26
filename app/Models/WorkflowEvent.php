<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Étape horodatée d'une demande (dépôt, validation, transmission, refus…). */
class WorkflowEvent extends Model
{
    use BelongsToInstitution;

    public const UPDATED_AT = null;

    protected $fillable = ['institution_id', 'subject_type', 'subject_id', 'action', 'user_id', 'auteur', 'details'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
