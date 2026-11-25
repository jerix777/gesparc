<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consumable extends Model
{
    use BelongsToInstitution;

    public const CATEGORIES = ['cartouche' => 'Cartouche d’encre', 'toner' => 'Toner', 'accessoire' => 'Accessoire'];

    protected $fillable = ['institution_id', 'reference', 'designation', 'categorie', 'imprimantes_compatibles', 'stock', 'seuil_alerte'];

    protected $casts = ['stock' => 'integer', 'seuil_alerte' => 'integer'];

    public function movements(): HasMany
    {
        return $this->hasMany(ConsumableMovement::class)->latest('id');
    }

    public function isLow(): bool
    {
        return $this->stock <= $this->seuil_alerte;
    }

    public function scopeLow(Builder $q): Builder
    {
        return $q->whereColumn('stock', '<=', 'seuil_alerte');
    }
}
