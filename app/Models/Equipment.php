<?php

namespace App\Models;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use BelongsToInstitution, HasFactory;

    protected $table = 'equipments';

    protected $fillable = [
        'institution_id', 'numero_inventaire', 'equipment_type_id', 'designation', 'marque', 'modele',
        'numero_serie', 'caracteristiques', 'fournisseur', 'date_acquisition', 'prix_achat', 'fin_garantie',
        'statut', 'etat', 'service_id', 'bureau', 'assigned_user_id', 'observations',
        'reforme_motif', 'reforme_proposee_par', 'reforme_proposee_le', 'source_ref',
    ];

    protected $casts = [
        'statut' => EquipmentStatus::class,
        'etat' => EquipmentCondition::class,
        'date_acquisition' => 'date',
        'fin_garantie' => 'date',
        'prix_achat' => 'integer',
        'reforme_proposee_le' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function retirementProposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reforme_proposee_par');
    }

    public function hasPendingRetirement(): bool
    {
        return $this->reforme_proposee_le !== null && $this->statut !== EquipmentStatus::Reforme;
    }

    public function scopePendingRetirement(Builder $q): Builder
    {
        return $q->whereNotNull('reforme_proposee_le')->where('statut', '!=', EquipmentStatus::Reforme->value);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class)->latest('id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class)->latest('id');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }
        $like = '%'.$term.'%';

        return $q->where(fn ($w) => $w->where('designation', 'like', $like)
            ->orWhere('numero_inventaire', 'like', $like)
            ->orWhere('numero_serie', 'like', $like)
            ->orWhere('marque', 'like', $like)
            ->orWhere('modele', 'like', $like)
            ->orWhere('bureau', 'like', $like));
    }

    public function warrantyExpiresSoon(): bool
    {
        return $this->fin_garantie && $this->fin_garantie->isFuture() && $this->fin_garantie->lte(now()->addDays(90));
    }

    public function ageInYears(): ?int
    {
        return $this->date_acquisition ? (int) $this->date_acquisition->diffInYears(now()) : null;
    }

    public function location(): string
    {
        return collect([$this->service?->nom, $this->bureau ? 'Bureau '.$this->bureau : null])->filter()->implode(' · ') ?: '—';
    }
}
