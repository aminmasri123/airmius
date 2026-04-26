<?php

use App\Http\Controllers\GuardianConsentController;
use Illuminate\Support\Facades\Route;

Route::get('/guardian-consent/{token}', [GuardianConsentController::class, 'show'])
    ->name('guardian-consent.show');

Route::post('/guardian-consent/{token}', [GuardianConsentController::class, 'approve'])
    ->name('guardian-consent.approve');


require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
require __DIR__.'/guest.php';
