<?php

use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\GamificationRuleController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SportAdminController;
use App\Http\Controllers\SponsorController;
use App\Http\Controllers\SubscriptionPlanController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth'])->group(function () {

    
    // Users
    Route::get('/admin/users', [MemberController::class, 'index'])->middleware('can:users.view')->name('users.index');
    Route::get('/admin/users/create', [MemberController::class, 'create'])->middleware('can:users.create')->name('users.create');
    Route::get('/admin/users/{user}/edit', [MemberController::class, 'edit'])->middleware('can:users.edit')->name('users.edit');
    Route::post('/admin/users', [MemberController::class, 'store'])->middleware('can:users.create')->name('users.store');
    Route::put('/admin/users/{user}', [MemberController::class, 'update'])->middleware('can:users.edit')->name('users.update');
    Route::delete('/admin/users/{user}', [MemberController::class, 'destroy'])->middleware('can:users.delete')->name('users.destroy');
   
    // MEMBERS
    Route::get('/admin/members', [MemberController::class, 'index'])->middleware('can:users.view')->name('members.index');
    Route::get('/admin/members/create', [MemberController::class, 'create'])->middleware('can:users.create')->name('members.create');
    Route::get('/admin/members/{user}/edit', [MemberController::class, 'edit'])->middleware('can:users.edit')->name('members.edit');
    Route::post('/admin/members', [MemberController::class, 'store'])->middleware('can:users.create')->name('members.store');
    Route::put('/admin/members/{user}', [MemberController::class, 'update'])->middleware('can:users.edit')->name('members.update');
    Route::delete('/admin/members/{user}', [MemberController::class, 'destroy'])->middleware('can:users.delete')->name('members.destroy');

    // ROLES & PERMISSIONS
    Route::get('/admin/roles-permissions', [RolePermissionController::class, 'index'])->middleware('can:users.assign_roles')->name('roles-permissions.index');
    Route::post('/admin/roles', [RolePermissionController::class, 'storeRole'])->middleware('can:users.assign_roles')->name('roles.store');
    Route::put('/admin/roles/{role}', [RolePermissionController::class, 'updateRole'])->middleware('can:users.assign_roles')->name('roles.update');
    Route::delete('/admin/roles/{role}', [RolePermissionController::class, 'destroyRole'])->middleware('can:users.assign_roles')->name('roles.destroy');
    Route::post('/admin/permissions', [RolePermissionController::class, 'storePermission'])->middleware('can:users.assign_roles')->name('permissions.store');

    // GAMIFICATION
    Route::get('/admin/gamification', [GamificationRuleController::class, 'index'])->middleware('can:system.manage')->name('gamification-rules.index');
    Route::put('/admin/gamification', [GamificationRuleController::class, 'update'])->middleware('can:system.manage')->name('gamification-rules.update');

    // SPORTS
    Route::get('/admin/sports', [SportAdminController::class, 'index'])->middleware('can:system.manage')->name('admin.sports.index');
    Route::post('/admin/sports', [SportAdminController::class, 'store'])->middleware('can:system.manage')->name('admin.sports.store');
    Route::put('/admin/sports/{sport}', [SportAdminController::class, 'update'])->middleware('can:system.manage')->name('admin.sports.update');
    Route::delete('/admin/sports/{sport}', [SportAdminController::class, 'destroy'])->middleware('can:system.manage')->name('admin.sports.destroy');

    // MODERATION
    Route::get('/admin/moderation', [ModerationController::class, 'index'])->middleware('can:system.manage')->name('admin.moderation.index');
    Route::put('/admin/moderation/flags/{flag}', [ModerationController::class, 'updateFlag'])->middleware('can:system.manage')->name('admin.moderation.flags.update');
    Route::put('/admin/moderation/reports/{report}', [ModerationController::class, 'updateReport'])->middleware('can:system.manage')->name('admin.moderation.reports.update');

    // BLOG CMS
    Route::get('/admin/blogs', [BlogPostController::class, 'index'])->middleware('can:blog.view')->name('blogs.index');
    Route::post('/admin/blogs', [BlogPostController::class, 'store'])->middleware('can:blog.create')->name('blogs.store');
    Route::put('/admin/blogs/{blogPost}', [BlogPostController::class, 'update'])->middleware('can:blog.update')->name('blogs.update');
    Route::delete('/admin/blogs/{blogPost}', [BlogPostController::class, 'destroy'])->middleware('can:blog.delete')->name('blogs.destroy');

    // PAYMENTS
    Route::get('/admin/payments', [PaymentController::class, 'index'])->middleware('can:billing.manage')->name('payments.index');
    Route::post('/admin/payments', [PaymentController::class, 'store'])->middleware('can:billing.manage')->name('payments.store');
    Route::delete('/admin/payments/{payment}', [PaymentController::class, 'destroy'])->middleware('can:billing.manage')->name('payments.destroy');

    // SUBSCRIPTIONS
    Route::get('/admin/subscriptions', [SubscriptionPlanController::class, 'index'])->middleware('can:subscriptions.manage')->name('admin.subscriptions.index');
    Route::put('/admin/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update'])->middleware('can:subscriptions.manage')->name('admin.subscription-plans.update');
    Route::put('/admin/clubs/{club}/subscription', [SubscriptionPlanController::class, 'assignClub'])->middleware('can:subscriptions.manage')->name('admin.clubs.subscription.update');

    // INVOICES
    Route::get('/admin/invoices', [InvoiceController::class, 'index'])->middleware('can:billing.manage')->name('invoices.index');
    Route::post('/admin/invoices', [InvoiceController::class, 'store'])->middleware('can:billing.manage')->name('invoices.store');
    Route::delete('/admin/invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('can:billing.manage')->name('invoices.destroy');

    // SPONSORS
    Route::get('/admin/sponsors', [SponsorController::class, 'index'])->middleware('can:finance.view')->name('sponsors.index');
    Route::post('/admin/sponsors', [SponsorController::class, 'store'])->middleware('can:finance.edit')->name('sponsors.store');
    Route::put('/admin/sponsors/{sponsor}', [SponsorController::class, 'update'])->middleware('can:finance.edit')->name('sponsors.update');
    Route::delete('/admin/sponsors/{sponsor}', [SponsorController::class, 'destroy'])->middleware('can:finance.edit')->name('sponsors.destroy');

    // SETTINGS
    Route::get('/admin/settings', [SettingController::class, 'index'])->middleware('can:system.manage')->name('admin.settings.index');
    Route::put('/admin/settings', [SettingController::class, 'update'])->middleware('can:system.manage')->name('admin.settings.update');

});
