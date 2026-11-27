<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'institution_id', 'service_id', 'name', 'email', 'password', 'matricule', 'fonction', 'bureau', 'role', 'active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class, 'assigned_user_id');
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Utilisateurs visibles par l'utilisateur connecté (même cloisonnement que les données). */
    public function scopeVisibleTo($query, User $viewer)
    {
        return $viewer->role->isCrossInstitution() ? $query : $query->where('institution_id', $viewer->institution_id);
    }
}
