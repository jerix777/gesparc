<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use BelongsToInstitution;

    protected $fillable = ['institution_id', 'nom'];
}
