<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'nom', 'categorie', 'ville', 'telephone', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('nom');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }
}
