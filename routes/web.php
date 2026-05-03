<?php

use App\Http\Controllers\GuardianConsentController;
use App\Http\Controllers\GuardianAccessController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\CommerceCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'microsoft'])
    ->name('social-auth.redirect');

Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'microsoft'])
    ->name('social-auth.callback');

Route::post('/webhooks/stripe', [SubscriptionCheckoutController::class, 'stripeWebhook'])
    ->name('webhooks.stripe');

Route::post('/webhooks/paypal', [SubscriptionCheckoutController::class, 'paypalWebhook'])
    ->name('webhooks.paypal');
Route::post('/webhooks/commerce/stripe', [CommerceCheckoutController::class, 'stripeWebhook'])
    ->name('webhooks.commerce.stripe');
Route::post('/webhooks/commerce/paypal', [CommerceCheckoutController::class, 'paypalWebhook'])
    ->name('webhooks.commerce.paypal');
Route::get('/ads/active', [CommerceCheckoutController::class, 'activeAd'])->name('ads.active');
Route::get('/ads/{campaign}/click', [CommerceCheckoutController::class, 'clickAd'])->name('ads.click');

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::post('/checkout/subscriptions/{subscriptionPlan}', [SubscriptionCheckoutController::class, 'store'])
        ->name('subscription-checkout.store');
    Route::get('/checkout/subscriptions/{checkout}/success', [SubscriptionCheckoutController::class, 'success'])
        ->name('subscription-checkout.success');
    Route::get('/checkout/subscriptions/{checkout}/cancel', [SubscriptionCheckoutController::class, 'cancel'])
        ->name('subscription-checkout.cancel');
    Route::get('/checkout/subscriptions/{checkout}/bank-transfer', [SubscriptionCheckoutController::class, 'bankTransfer'])
        ->name('subscription-checkout.bank-transfer.show');
    Route::get('/checkout/commerce/{order}/success', [CommerceCheckoutController::class, 'success'])
        ->name('commerce-checkout.success');
    Route::get('/checkout/commerce/{order}/cancel', [CommerceCheckoutController::class, 'cancel'])
        ->name('commerce-checkout.cancel');
    Route::get('/checkout/commerce/{order}/bank-transfer', [CommerceCheckoutController::class, 'bankTransfer'])
        ->name('commerce-checkout.bank-transfer.show');
});

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
