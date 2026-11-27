<?php

use Illuminate\Support\Facades\Schedule;

// Tâches planifiées. Sur le serveur : * * * * * cd /var/www/gesparc && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('sanctum:prune-expired --hours=24')->daily();
