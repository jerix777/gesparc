<?php

use App\Http\Controllers\Admin\InstitutionController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsumableController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\Personnel\LeaveController;
use App\Http\Controllers\Personnel\SettingsController as PersonnelSettingsController;
use App\Http\Controllers\Personnel\StaffController;
use App\Http\Controllers\Personnel\VacationController;
use App\Http\Controllers\Personnel\VerificationController;
use Illuminate\Support\Facades\Route;

// Page vitrine pour les visiteurs ; un utilisateur connecté est redirigé vers son espace.
Route::get('/', [DashboardController::class, 'home'])->name('home');

// Vérification publique d'une décision (code QR imprimé sur le document).
Route::get('/verification/{code}', [VerificationController::class, 'show'])->name('verification')->middleware('throttle:30,1');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');
    Route::get('/mot-de-passe', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/mot-de-passe', [AuthController::class, 'updatePassword'])->name('password.update');
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

    // Module Personnel
    Route::prefix('personnel')->name('personnel.')->group(function () {
        Route::get('absences', [LeaveController::class, 'index'])->name('absences.index');
        Route::get('absences/nouvelle', [LeaveController::class, 'create'])->name('absences.create');
        Route::post('absences', [LeaveController::class, 'store'])->name('absences.store');
        Route::get('absences/{leave}', [LeaveController::class, 'show'])->name('absences.show');
        Route::post('absences/{leave}/decision', [LeaveController::class, 'decide'])->name('absences.decide');
        Route::post('absences/{leave}/annulation', [LeaveController::class, 'cancel'])->name('absences.cancel');
        Route::get('absences/{leave}/document', [LeaveController::class, 'document'])->name('absences.document');

        Route::get('vacances', [VacationController::class, 'index'])->name('vacations.index');
        Route::post('vacances/campagnes', [VacationController::class, 'open'])->name('vacations.open');
        Route::post('vacances/{campaign}/demande', [VacationController::class, 'store'])->name('vacations.store');
        Route::post('vacances/demandes/{vacationRequest}/decision', [VacationController::class, 'decide'])->name('vacations.decide');
        Route::post('vacances/{campaign}/cloture', [VacationController::class, 'close'])->name('vacations.close');
        Route::post('vacances/{campaign}/reouverture', [VacationController::class, 'reopen'])->name('vacations.reopen');
        Route::get('vacances/{campaign}/provisoires', [VacationController::class, 'provisional'])->name('vacations.provisional');
        Route::get('vacances/listes/{list}', [VacationController::class, 'list'])->name('vacations.lists.show');
        Route::post('vacances/listes/{list}/scan', [VacationController::class, 'uploadScan'])->name('vacations.lists.upload');
        Route::get('vacances/listes/{list}/scan', [VacationController::class, 'downloadScan'])->name('vacations.lists.scan');

        Route::post('parametrage/jours-feries', [PersonnelSettingsController::class, 'addHoliday'])->name('holidays.store');
        Route::delete('parametrage/jours-feries/{holiday}', [PersonnelSettingsController::class, 'removeHoliday'])->name('holidays.destroy');
        Route::get('parametrage', [PersonnelSettingsController::class, 'index'])->name('settings');
        Route::post('parametrage/modele', [PersonnelSettingsController::class, 'preset'])->name('preset');
        Route::post('parametrage/fonctions', [PersonnelSettingsController::class, 'assign'])->name('positions.store');
        Route::delete('parametrage/fonctions/{position}', [PersonnelSettingsController::class, 'unassign'])->name('positions.destroy');
        Route::put('parametrage/regles/{category}/{kind}', [PersonnelSettingsController::class, 'updateRule'])->name('rules.update');
        Route::post('{staff}/sortie', [StaffController::class, 'leave'])->name('staff.leave');
        Route::post('{staff}/reintegration', [StaffController::class, 'reinstate'])->name('staff.reinstate');
        Route::post('{staff}/compte', [StaffController::class, 'createAccount'])->name('staff.account');
    });
    Route::resource('personnel', StaffController::class)->parameters(['personnel' => 'staff'])->names('personnel.staff')->except('destroy');

    Route::prefix('administration')->name('admin.')->group(function () {
        Route::resource('institutions', InstitutionController::class)->except(['show', 'destroy']);
        Route::resource('utilisateurs', UserController::class)->parameters(['utilisateurs' => 'user'])->names('users')->except(['show', 'destroy']);
        Route::post('utilisateurs/{user}/reinitialiser', [UserController::class, 'resetPassword'])->name('users.reset');
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
        Route::get('parametres', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('parametres', [SettingsController::class, 'update'])->name('settings.update');
    });
});
