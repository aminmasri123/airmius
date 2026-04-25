<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SponsorController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth', 'role:admin'])->group(function () {

    // MEMBERS
    Route::get('/admin/members', [MemberController::class, 'index']);
    Route::post('/admin/members', [MemberController::class, 'store']);
    Route::put('/admin/members/{user}', [MemberController::class, 'update']);
    Route::delete('/admin/members/{user}', [MemberController::class, 'destroy']);

    // PAYMENTS
    Route::get('/admin/payments', [PaymentController::class, 'index']);
    Route::post('/admin/payments', [PaymentController::class, 'store']);
    Route::delete('/admin/payments/{payment}', [PaymentController::class, 'destroy']);

    // INVOICES
    Route::get('/admin/invoices', [InvoiceController::class, 'index']);
    Route::post('/admin/invoices', [InvoiceController::class, 'store']);
    Route::delete('/admin/invoices/{invoice}', [InvoiceController::class, 'destroy']);

    // SPONSORS
    Route::get('/admin/sponsors', [SponsorController::class, 'index']);
    Route::post('/admin/sponsors', [SponsorController::class, 'store']);
    Route::put('/admin/sponsors/{sponsor}', [SponsorController::class, 'update']);
    Route::delete('/admin/sponsors/{sponsor}', [SponsorController::class, 'destroy']);

    // SETTINGS
    Route::get('/admin/settings', [SettingController::class, 'index']);
    Route::put('/admin/settings', [SettingController::class, 'update']);

});
