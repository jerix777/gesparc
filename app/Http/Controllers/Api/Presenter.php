<?php

namespace App\Http\Controllers\Api;

use App\Models\Consumable;
use App\Models\Equipment;
use App\Models\Incident;
use App\Models\MaterialRequest;
use App\Models\User;

/** Format JSON stable de l'API v1 (noms de champs en français, dates ISO 8601). */
class Presenter
{
    public static function user(User $u): array
    {
        return [
            'id' => $u->id,
            'nom' => $u->name,
            'email' => $u->email,
            'role' => $u->role->value,
            'role_libelle' => $u->role->label(),
            'fonction' => $u->fonction,
            'bureau' => $u->bureau,
            'service' => $u->service?->nom,
            'institution' => $u->institution ? ['id' => $u->institution->id, 'code' => $u->institution->code, 'nom' => $u->institution->nom] : null,
        ];
    }

    public static function equipment(Equipment $e): array
    {
        return [
            'id' => $e->id,
            'numero_inventaire' => $e->numero_inventaire,
            'institution' => $e->institution?->code,
            'type' => $e->type?->nom,
            'designation' => $e->designation,
            'marque' => $e->marque,
            'modele' => $e->modele,
            'numero_serie' => $e->numero_serie,
            'statut' => $e->statut->value,
            'statut_libelle' => $e->statut->label(),
            'etat' => $e->etat->value,
            'service' => $e->service?->nom,
            'bureau' => $e->bureau,
            'affecte_a' => $e->assignedUser ? ['id' => $e->assignedUser->id, 'nom' => $e->assignedUser->name] : null,
            'date_acquisition' => $e->date_acquisition?->toDateString(),
            'fin_garantie' => $e->fin_garantie?->toDateString(),
        ];
    }

    public static function request(MaterialRequest $r): array
    {
        return [
            'id' => $r->id,
            'numero' => $r->numero,
            'categorie' => $r->categorie,
            'materiel' => $r->materiel,
            'quantite' => $r->quantite,
            'caracteristiques' => $r->caracteristiques,
            'observations' => $r->observations,
            'bureau' => $r->bureau,
            'demandeur' => ['id' => $r->requester_id, 'nom' => $r->requester?->name],
            'statut' => $r->statut->value,
            'statut_libelle' => $r->statut->label(),
            'motif_refus' => $r->motif_refus,
            'decide_par' => $r->decider?->name,
            'decide_le' => $r->decided_at?->toIso8601String(),
            'cree_le' => $r->created_at?->toIso8601String(),
        ];
    }

    public static function incident(Incident $i): array
    {
        return [
            'id' => $i->id,
            'numero' => $i->numero,
            'equipement_id' => $i->equipment_id,
            'description' => $i->description,
            'statut' => $i->statut->value,
            'statut_libelle' => $i->statut->label(),
            'cree_le' => $i->created_at?->toIso8601String(),
        ];
    }

    public static function consumable(Consumable $c): array
    {
        return [
            'id' => $c->id,
            'reference' => $c->reference,
            'designation' => $c->designation,
            'categorie' => $c->categorie,
            'imprimantes_compatibles' => $c->imprimantes_compatibles,
            'stock' => $c->stock,
            'seuil_alerte' => $c->seuil_alerte,
            'en_alerte' => $c->isLow(),
        ];
    }
}
