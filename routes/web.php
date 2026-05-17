<?php

use App\Http\Controllers\GuardianConsentController;
use App\Http\Controllers\GuardianAccessController;
use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\CommerceCheckoutController;
use App\Http\Controllers\OutfitSubscriptionController;
use Illuminate\Http\Request;
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
Route::post('/webhooks/outfit-subscriptions/paypal', [OutfitSubscriptionController::class, 'paypalWebhook'])
    ->name('webhooks.outfit-subscriptions.paypal');
Route::get('/ads/active', [CommerceCheckoutController::class, 'activeAd'])->name('ads.active');
Route::get('/ads/{campaign}/click', [CommerceCheckoutController::class, 'clickAd'])->name('ads.click');
Route::post('/ads/{campaign}/conversion', [CommerceCheckoutController::class, 'conversionAd'])->name('ads.conversion');
Route::get('/shared-files/{token}', [FileController::class, 'sharedDownload'])
    ->middleware('throttle:file-shared-download')
    ->name('files.shared-download');
Route::get('/checkout/guest-commerce/{order}/{token}/success', [CommerceCheckoutController::class, 'guestSuccess'])
    ->name('commerce-checkout.guest.success');
Route::get('/checkout/guest-commerce/{order}/{token}/cancel', [CommerceCheckoutController::class, 'guestCancel'])
    ->name('commerce-checkout.guest.cancel');
Route::get('/checkout/guest-commerce/{order}/{token}/bank-transfer', [CommerceCheckoutController::class, 'guestBankTransfer'])
    ->name('commerce-checkout.guest.bank-transfer.show');

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::get('/checkout/csrf-token', function (Request $request) {
        $request->session()->regenerateToken();

        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    })->name('checkout.csrf-token');

    Route::post('/user/deletion-code', [AccountDeletionController::class, 'sendCode'])
        ->name('current-user.deletion-code');
    Route::delete('/user', [AccountDeletionController::class, 'destroy'])
        ->name('current-user.destroy');

    Route::post('/checkout/subscriptions/{subscriptionPlan}', [SubscriptionCheckoutController::class, 'store'])
        ->name('subscription-checkout.store');
    Route::get('/checkout/subscriptions/{subscriptionPlanId}/start', [SubscriptionCheckoutController::class, 'start'])
        ->name('subscription-checkout.start');
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
    Route::get('/checkout/outfit-subscriptions/{subscription}/success', [OutfitSubscriptionController::class, 'success'])
        ->name('outfit-subscription-checkout.success');
    Route::get('/checkout/outfit-subscriptions/{subscription}/cancel', [OutfitSubscriptionController::class, 'cancelCheckout'])
        ->name('outfit-subscription-checkout.cancel');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])
    ->get('/guardian-consent/pending', [GuardianConsentController::class, 'pending'])
    ->name('guardian-consent.pending');

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])
    ->post('/guardian-consent/resend', [GuardianConsentController::class, 'resend'])
    ->name('guardian-consent.resend');

Route::get('/guardian-consent/{token}', [GuardianConsentController::class, 'show'])
    ->name('guardian-consent.show');

Route::get('/guardian-consent/{token}/approve', [GuardianConsentController::class, 'approveDirect'])
    ->middleware('signed')
    ->name('guardian-consent.approve-direct');

Route::get('/guardian-consent/{token}/reject', [GuardianConsentController::class, 'rejectDirect'])
    ->middleware('signed')
    ->name('guardian-consent.reject-direct');

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
Route::put('/eltern/kinder/{child}/zustimmen', [GuardianAccessController::class, 'approve'])
    ->name('guardian-access.children.approve');
Route::post('/eltern/logout', [GuardianAccessController::class, 'destroy'])
    ->name('guardian-access.destroy');

require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
require __DIR__.'/guest.php';
