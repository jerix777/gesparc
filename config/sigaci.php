<?php

return [
    // Nom affiché partout dans l'application, et sa description. Modifiables dans Administration > Paramètres
    // (App\Models\Setting), qui l'emporte sur ces valeurs.
    'sigle' => env('SIGACI_SIGLE', 'SIGACI'),
    'nom' => env('SIGACI_NOM', "Système Intégré de Gestion et d'Administration de Côte d'Ivoire"),

    // Ministère de tutelle affiché en haut de chaque page.
    'tutelle' => env('SIGACI_TUTELLE', "Ministère de la Justice et des Droits de l'Homme"),

    // Bandeau « Information » qui défile en haut des pages (laisser vide pour le masquer).
    'information' => env('SIGACI_INFORMATION', "Bienvenue sur :sigle !Toutes les démarches administratives internes se font désormais en ligne : demandes de matériel, pannes, absences et vacances judiciaires."),

    // Courriels automatiques (nouvelle demande, décision, panne, réforme à valider, stock bas).
    'notifications' => (bool) env('SIGACI_NOTIFICATIONS', true),
];
