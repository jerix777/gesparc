<?php

namespace App\Models;

use App\Enums\Position;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPosition extends Model
{
    use BelongsToInstitution;

    protected $fillable = ['institution_id', 'staff_id', 'position'];

    protected function casts(): array
    {
        return ['position' => Position::class];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
