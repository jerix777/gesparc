<?php

use App\Http\Controllers\Admin\InstitutionController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsumableController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MaterialRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'home'])->name('home');
    Route::get('/tableau-de-bord', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/mon-espace', [DashboardController::class, 'mySpace'])->name('my-space');

    Route::get('/inventaire/export', [EquipmentController::class, 'export'])->name('equipments.export');
    Route::resource('inventaire', EquipmentController::class)
        ->parameters(['inventaire' => 'equipment'])->names('equipments')->except('destroy');
    Route::get('/inventaire/{equipment}/etiquette', [EquipmentController::class, 'label'])->name('equipments.label');
    Route::post('/inventaire/{equipment}/affecter', [EquipmentController::class, 'assign'])->name('equipments.assign');
    Route::post('/inventaire/{equipment}/restituer', [EquipmentController::class, 'release'])->name('equipments.release');
    Route::post('/inventaire/{equipment}/proposer-reforme', [EquipmentController::class, 'proposeRetirement'])->name('equipments.retire.propose');
    Route::post('/inventaire/{equipment}/valider-reforme', [EquipmentController::class, 'approveRetirement'])->name('equipments.retire.approve');
    Route::post('/inventaire/{equipment}/rejeter-reforme', [EquipmentController::class, 'rejectRetirement'])->name('equipments.retire.reject');
    Route::post('/inventaire/{equipment}/panne', [IncidentController::class, 'store'])->name('incidents.store');

    Route::get('/pannes', [IncidentController::class, 'index'])->name('incidents.index');
    Route::post('/pannes/{incident}/maintenance', [IncidentController::class, 'maintenance'])->name('incidents.maintenance');
    Route::post('/pannes/{incident}/cloturer', [IncidentController::class, 'close'])->name('incidents.close');

    Route::get('/consommables', [ConsumableController::class, 'index'])->name('consumables.index');
    Route::post('/consommables', [ConsumableController::class, 'store'])->name('consumables.store');
    Route::post('/consommables/{consumable}/mouvement', [ConsumableController::class, 'move'])->name('consumables.move');

    Route::get('/demandes', [MaterialRequestController::class, 'index'])->name('requests.index');
    Route::get('/demandes/nouvelle', [MaterialRequestController::class, 'create'])->name('requests.create');
    Route::post('/demandes', [MaterialRequestController::class, 'store'])->name('requests.store');
    Route::post('/demandes/{materialRequest}/decision', [MaterialRequestController::class, 'decide'])->name('requests.decide');

    Route::prefix('administration')->name('admin.')->group(function () {
        Route::resource('institutions', InstitutionController::class)->except(['show', 'destroy']);
        Route::resource('utilisateurs', UserController::class)->parameters(['utilisateurs' => 'user'])->names('users')->except(['show', 'destroy']);
        Route::post('utilisateurs/{user}/reinitialiser', [UserController::class, 'resetPassword'])->name('users.reset');
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });
});
