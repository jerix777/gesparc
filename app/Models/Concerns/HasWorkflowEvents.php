<?php

namespace App\Models\Concerns;

use App\Models\WorkflowEvent;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

trait HasWorkflowEvents
{
    public function events(): MorphMany
    {
        return $this->morphMany(WorkflowEvent::class, 'subject')->orderBy('id');
    }

    public function logEvent(string $action, ?string $details = null): void
    {
        $user = Auth::user();
        $staff = $user?->staff;
        $this->events()->create([
            'institution_id' => $this->institution_id,
            'action' => $action,
            'user_id' => $user?->id,
            'auteur' => $staff ? trim($staff->full_name.($staff->fonction ? ', '.$staff->fonction : '')) : $user?->name,
            'details' => $details ? mb_substr($details, 0, 500) : null,
        ]);
    }
}
