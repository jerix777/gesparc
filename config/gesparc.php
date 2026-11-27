<?php

return [
    // Courriels automatiques (nouvelle demande, décision, panne, réforme à valider, stock bas).
    'notifications' => (bool) env('GESPARC_NOTIFICATIONS', true),
];
