<?php

use App\Http\Controllers\GuardianConsentController;
use App\Http\Controllers\GuardianAccessController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])
    ->get('/guardian-consent/pending', [GuardianConsentController::class, 'pending'])
    ->name('guardian-consent.pending');

Route::get('/guardian-consent/{token}', [GuardianConsentController::class, 'show'])
    ->name('guardian-consent.show');

Route::post('/guardian-consent/{token}', [GuardianConsentController::class, 'approve'])
    ->name('guardian-consent.approve');

Route::delete('/guardian-consent/{token}', [GuardianConsentController::class, 'reject'])
    ->name('guardian-consent.reject');

Route::get('/eltern-login', [GuardianAccessController::class, 'create'])
    ->name('guardian-access.create');
Route::post('/eltern-login', [GuardianAccessController::class, 'store'])
    ->name('guardian-access.store');
Route::get('/eltern-login/code', [GuardianAccessController::class, 'verify'])
    ->name('guardian-access.verify');
Route::post('/eltern-login/code', [GuardianAccessController::class, 'confirm'])
    ->name('guardian-access.confirm');
Route::get('/eltern/kinder', [GuardianAccessController::class, 'children'])
    ->name('guardian-access.children');
Route::get('/eltern/konto-erstellen', [GuardianAccessController::class, 'createAccount'])
    ->name('guardian-access.account.create');
Route::post('/eltern/konto-erstellen', [GuardianAccessController::class, 'storeAccount'])
    ->name('guardian-access.account.store');
Route::put('/eltern/kinder/{child}/widerrufen', [GuardianAccessController::class, 'revoke'])
    ->name('guardian-access.children.revoke');
Route::post('/eltern/logout', [GuardianAccessController::class, 'destroy'])
    ->name('guardian-access.destroy');

require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
require __DIR__.'/guest.php';
