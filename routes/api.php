<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

// API v1 — documentation : docs/API.md
Route::prefix('v1')->group(function () {
    Route::post('/jetons', [ApiController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::delete('/jetons/courant', [ApiController::class, 'logout']);
        Route::get('/moi', [ApiController::class, 'me']);
        Route::get('/mes-equipements', [ApiController::class, 'myEquipments']);
        Route::get('/equipements', [ApiController::class, 'equipments']);
        Route::get('/equipements/{equipment}', [ApiController::class, 'equipment']);
        Route::post('/equipements/{equipment}/pannes', [ApiController::class, 'reportFailure']);
        Route::get('/demandes', [ApiController::class, 'requestsIndex']);
        Route::post('/demandes', [ApiController::class, 'requestsStore']);
        Route::post('/demandes/{materialRequest}/decision', [ApiController::class, 'requestsDecide']);
        Route::get('/consommables', [ApiController::class, 'consumables']);
    });
});
