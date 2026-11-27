<?php

namespace App\Models;

use App\Enums\Position;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Fiche du personnel ; le compte utilisateur y est rattaché quand l'agent en a un. */
class Staff extends Model
{
    use BelongsToInstitution;

    protected $table = 'staff';

    protected $fillable = [
        'institution_id', 'staff_category_id', 'service_id', 'user_id', 'matricule', 'nom', 'prenoms',
        'fonction', 'telephone', 'email', 'bureau', 'date_prise_service', 'statut', 'date_sortie', 'motif_sortie', 'source_ref',
    ];

    protected function casts(): array
    {
        return ['date_prise_service' => 'date', 'date_sortie' => 'date'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StaffCategory::class, 'staff_category_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(StaffPosition::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->nom.' '.$this->prenoms);
    }

    public function holds(Position ...$positions): bool
    {
        $held = $this->positions->pluck('position');

        return collect($positions)->contains(fn ($p) => $held->contains($p));
    }

    public function isActive(): bool
    {
        return $this->statut === 'actif';
    }

    /** Fiche « complète » : l'agent ne peut plus la modifier lui-même. */
    public function isProfileComplete(): bool
    {
        return $this->bureau && $this->date_prise_service && $this->service_id;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('statut', 'actif');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('nom', 'like', "%{$term}%")->orWhere('prenoms', 'like', "%{$term}%")
            ->orWhere('matricule', 'like', "%{$term}%")->orWhere('fonction', 'like', "%{$term}%"));
    }
}
