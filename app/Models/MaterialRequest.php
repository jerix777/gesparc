<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequest extends Model
{
    use BelongsToInstitution;

    /** Liste reprise de l'application TPI Bouaké. */
    public const MATERIELS = [
        'Clavier', 'Souris', 'Écran', 'Ordinateur portable', 'Ordinateur de bureau', 'Unité centrale', 'Onduleur',
        'Disque dur externe', 'Clé USB', 'Câble réseau RJ45', 'Câble HDMI', 'Câble VGA', 'Chargeur d’ordinateur',
        'Batterie d’ordinateur', 'Adaptateur', 'Multiprise', 'Cartouche d’encre', 'Autres accessoires informatiques',
    ];

    protected $fillable = [
        'institution_id', 'numero', 'requester_id', 'categorie', 'materiel', 'consumable_id', 'source', 'quantite', 'bureau',
        'caracteristiques', 'observations', 'statut', 'decided_by', 'decided_at', 'motif_refus',
    ];

    protected $casts = [
        'statut' => RequestStatus::class,
        'decided_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
