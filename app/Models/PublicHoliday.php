<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jour férié national (exclu du décompte des jours ouvrés). */
class PublicHoliday extends Model
{
    protected $fillable = ['date', 'libelle'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
