<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffCategory extends Model
{
    use BelongsToInstitution;

    protected $fillable = ['institution_id', 'code', 'nom', 'magistrat', 'ordre'];

    protected function casts(): array
    {
        return ['magistrat' => 'boolean'];
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class);
    }

    public function rule(string $kind): ?ApprovalRule
    {
        return $this->rules->firstWhere('kind', $kind);
    }
}
