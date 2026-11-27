<?php

return [
    'alpha_dash' => 'Le champ :attribute ne peut contenir que des lettres, chiffres, tirets et soulignés.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale à :date.',
    'between' => ['numeric' => 'Le champ :attribute doit être compris entre :min et :max.'],
    'date' => "Le champ :attribute n'est pas une date valide.",
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'enum' => 'La valeur choisie pour :attribute est invalide.',
    'exists' => 'La valeur choisie pour :attribute est invalide.',
    'in' => 'La valeur choisie pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => ['numeric' => 'Le champ :attribute ne peut dépasser :max.', 'string' => 'Le champ :attribute ne peut dépasser :max caractères.'],
    'min' => ['numeric' => 'Le champ :attribute doit être au moins :min.', 'string' => 'Le champ :attribute doit contenir au moins :min caractères.'],
    'password' => ['min' => 'Le mot de passe doit contenir au moins :min caractères.'],
    'prohibited' => "Le champ :attribute n'est pas autorisé.",
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être un texte.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',

    'attributes' => [
        'name' => 'nom', 'email' => 'e-mail', 'password' => 'mot de passe', 'role' => 'rôle',
        'institution_id' => 'institution', 'service_id' => 'service', 'equipment_type_id' => 'type',
        'designation' => 'désignation', 'numero_inventaire' => "n° d'inventaire", 'date_acquisition' => "date d'acquisition",
        'prix_achat' => "prix d'achat", 'fin_garantie' => 'fin de garantie', 'quantite' => 'quantité', 'materiel' => 'matériel',
        'motif' => 'motif', 'description' => 'description', 'code' => 'code', 'nom' => 'nom', 'etat' => 'état',
    ],
];
