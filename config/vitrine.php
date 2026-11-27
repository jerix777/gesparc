<?php

/*
 * Personnalisation de la page d'accueil publique (vitrine).
 * Modifiez ces valeurs (ou les variables VITRINE_* du fichier .env) sans toucher au code.
 */
return [
    // Code de l'institution mise en avant : ses statistiques réelles s'affichent sur la vitrine.
    'institution' => env('VITRINE_INSTITUTION', 'TPI-BKE'),

    'nom' => env('VITRINE_NOM', 'Tribunal de Première Instance de Bouaké'),
    'sigle' => env('VITRINE_SIGLE', 'TPI Bouaké'),
    'tutelle' => env('VITRINE_TUTELLE', "Ministère de la Justice et des Droits de l'Homme"),
    'service' => env('VITRINE_SERVICE', 'Service informatique'),
    'ville' => env('VITRINE_VILLE', 'Bouaké, région du Gbêkê'),

    // Afficher les statistiques calculées en direct depuis SIGACI (chiffres agrégés, aucune donnée personnelle).
    'statistiques' => (bool) env('VITRINE_STATISTIQUES', true),

    // Coordonnées affichées en bas de page (laisser vide pour masquer).
    'contact' => [
        'adresse' => env('VITRINE_ADRESSE', ''),
        'telephone' => env('VITRINE_TELEPHONE', ''),
        'email' => env('VITRINE_EMAIL', ''),
    ],

    /*
     * Diapositives du carrousel. « illustration » : dessin intégré (balance, palais, maillet, parc).
     * Pour utiliser une photo, déposez-la dans public/images/vitrine/ et indiquez son nom dans « image » :
     * la photo remplace alors le dessin. Utilisez des photos dont le tribunal détient les droits.
     */
    'diapositives' => [
        [
            'illustration' => 'balance',
            'image' => null,
            'surtitre' => 'Une justice mieux équipée',
            'titre' => 'Chaque poste de travail compte pour rendre la justice',
            'texte' => "Greffes, parquet, présidence : le Service informatique veille à ce que chaque agent dispose d'un matériel en état de marche.",
        ],
        [
            'illustration' => 'palais',
            'image' => null,
            'surtitre' => 'Au service des justiciables',
            'titre' => 'Un tribunal au cœur de la région du Gbêkê',
            'texte' => 'Des actes délivrés à temps et des audiences qui se tiennent : un parc informatique fiable est au service des usagers du tribunal.',
        ],
        [
            'illustration' => 'maillet',
            'image' => null,
            'surtitre' => 'Des audiences sans interruption',
            'titre' => 'Une panne signalée est une panne suivie',
            'texte' => "Signalement en un clic, intervention tracée, matériel remis en service : chaque incident est suivi jusqu'à sa résolution.",
        ],
        [
            'illustration' => 'parc',
            'image' => null,
            'surtitre' => 'Un parc suivi en temps réel',
            'titre' => 'Inventaire, affectations, consommables',
            'texte' => 'Ordinateurs, imprimantes, onduleurs et cartouches : tout le matériel du tribunal est recensé et affecté, avec son historique.',
        ],
    ],
];
