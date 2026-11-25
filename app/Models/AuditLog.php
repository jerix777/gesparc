<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['institution_id', 'user_id', 'action', 'subject_type', 'subject_id', 'details', 'ip'];

    public static function record(string $action, ?Model $subject = null, ?string $details = null, ?int $institutionId = null): void
    {
        $user = Auth::user();
        static::create([
            'institution_id' => $institutionId ?? $subject?->institution_id ?? $user?->institution_id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'details' => $details,
            'ip' => Request::ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
