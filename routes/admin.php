<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SponsorController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth'])->group(function () {

    
    // Users
    Route::get('/admin/users', [MemberController::class, 'index'])->name('users.index');
    Route::get('/admin/users/create', [MemberController::class, 'create'])->name('users.create');
    Route::get('/admin/users/{user}/edit', [MemberController::class, 'edit'])->name('users.edit');
    Route::post('/admin/users', [MemberController::class, 'store'])->name('users.store');
    Route::put('/admin/users/{user}', [MemberController::class, 'update'])->name('users.update');
    Route::delete('/admin/users/{user}', [MemberController::class, 'destroy'])->name('users.destroy');
    // MEMBERS
    Route::get('/admin/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/admin/members/create', [MemberController::class, 'create'])->name('members.create');
    Route::get('/admin/members/{user}/edit', [MemberController::class, 'edit'])->name('members.edit');
    Route::post('/admin/members', [MemberController::class, 'store'])->name('members.store');
    Route::put('/admin/members/{user}', [MemberController::class, 'update'])->name('members.update');
    Route::delete('/admin/members/{user}', [MemberController::class, 'destroy'])->name('members.destroy');

    // PAYMENTS
    Route::get('/admin/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/admin/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::delete('/admin/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    // INVOICES
    Route::get('/admin/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('/admin/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::delete('/admin/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    // SPONSORS
    Route::get('/admin/sponsors', [SponsorController::class, 'index'])->name('sponsors.index');
    Route::post('/admin/sponsors', [SponsorController::class, 'store'])->name('sponsors.store');
    Route::put('/admin/sponsors/{sponsor}', [SponsorController::class, 'update'])->name('sponsors.update');
    Route::delete('/admin/sponsors/{sponsor}', [SponsorController::class, 'destroy'])->name('sponsors.destroy');

    // SETTINGS
    Route::get('/admin/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/admin/settings', [SettingController::class, 'update'])->name('settings.update');

});
