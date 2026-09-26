<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\Api\V1\ClubGuardianRelationshipController as WebClubGuardianRelationshipController;
use App\Http\Controllers\Api\V1\ClubMemberRelationshipController as WebClubMemberRelationshipController;
use App\Http\Controllers\ClubSepaFeeRechargeCreditDocumentController;
use App\Http\Controllers\CommerceCheckoutController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GuardianAccessController;
use App\Http\Controllers\GuardianConsentController;
use App\Http\Controllers\OutfitSubscriptionController;
use App\Http\Controllers\PublicWebManifestController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\TwoFactorEmailCodeController;
use App\Http\Controllers\Webhooks\PostmarkMailWebhookController;
use App\Http\Middleware\EnsureIdempotentApiRequest;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;

Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed:relative', 'throttle:10,1'])
    ->whereNumber('id')
    ->name('email.verification.bridge');

// Mobile reset emails use a query-string token. Keep this route alongside
// Fortify's /reset-password/{token} route so browser links do not return 404.
Route::get('/reset-password', function (Request $request) {
    return Inertia::render('Auth/ResetPassword', [
        'email' => $request->query('email'),
        'token' => $request->query('token'),
    ]);
})->middleware('guest')->name('password.reset.query');

Route::post('/two-factor-challenge/email-code', [TwoFactorEmailCodeController::class, 'send'])
    ->middleware(['guest', 'throttle:3,1'])
    ->name('two-factor.email.send');
Route::post('/two-factor-challenge/email-login', [TwoFactorEmailCodeController::class, 'store'])
    ->middleware(['guest', 'throttle:two-factor'])
    ->name('two-factor.email.login');

Route::get('/site.webmanifest', PublicWebManifestController::class)
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])
    ->name('site.webmanifest');

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
Route::post('/webhooks/mail/postmark', PostmarkMailWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.mail.postmark');
Route::get('/ads/active', [CommerceCheckoutController::class, 'activeAd'])->name('ads.active');
Route::get('/ads/{campaign}/click', [CommerceCheckoutController::class, 'clickAd'])->name('ads.click');
Route::post('/ads/{campaign}/conversion', [CommerceCheckoutController::class, 'conversionAd'])->name('ads.conversion');
Route::get('/shared-files/{token}', [FileController::class, 'sharedDownload'])
    ->middleware('throttle:file-shared-download')
    ->name('files.shared-download');
Route::get('/commerce/documents/{order}/{type}', [CommerceCheckoutController::class, 'downloadSignedDocument'])
    ->middleware(['signed', 'throttle:60,1'])
    ->whereIn('type', ['invoice', 'credit-note'])
    ->name('commerce.documents.signed');
Route::get('/documents/club-sepa-fee-recharge-credits/{credit}', ClubSepaFeeRechargeCreditDocumentController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->whereNumber('credit')
    ->name('club-sepa-fee-recharge-credits.documents.signed');
Route::get('/checkout/guest-commerce/{order}/{token}/success', [CommerceCheckoutController::class, 'guestSuccess'])
    ->middleware('throttle:60,1')
    ->name('commerce-checkout.guest.success');
Route::get('/checkout/guest-commerce/{order}/{token}/cancel', [CommerceCheckoutController::class, 'guestCancel'])
    ->middleware(['signed', 'throttle:payment-actions'])
    ->name('commerce-checkout.guest.cancel');
Route::get('/checkout/guest-commerce/{order}/{token}/bank-transfer', [CommerceCheckoutController::class, 'guestBankTransfer'])
    ->middleware('throttle:60,1')
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
        ->middleware(['throttle:payment-actions', EnsureIdempotentApiRequest::class])
        ->name('subscription-checkout.store');
    Route::get('/checkout/subscriptions/{checkout}/success', [SubscriptionCheckoutController::class, 'success'])
        ->name('subscription-checkout.success');
    Route::get('/checkout/subscriptions/{checkout}/cancel', [SubscriptionCheckoutController::class, 'cancel'])
        ->middleware(['signed', 'throttle:payment-actions'])
        ->name('subscription-checkout.cancel');
    Route::get('/checkout/subscriptions/{checkout}/bank-transfer', [SubscriptionCheckoutController::class, 'bankTransfer'])
        ->name('subscription-checkout.bank-transfer.show');
    Route::get('/checkout/commerce/{order}/success', [CommerceCheckoutController::class, 'success'])
        ->name('commerce-checkout.success');
    Route::get('/checkout/commerce/{order}/cancel', [CommerceCheckoutController::class, 'cancel'])
        ->middleware(['signed', 'throttle:payment-actions'])
        ->name('commerce-checkout.cancel');
    Route::get('/checkout/commerce/{order}/bank-transfer', [CommerceCheckoutController::class, 'bankTransfer'])
        ->name('commerce-checkout.bank-transfer.show');
    Route::get('/checkout/outfit-subscriptions/{subscription}/success', [OutfitSubscriptionController::class, 'success'])
        ->name('outfit-subscription-checkout.success');
    Route::get('/checkout/outfit-subscriptions/{subscription}/cancel', [OutfitSubscriptionController::class, 'cancelCheckout'])
        ->middleware(['signed', 'throttle:payment-actions'])
        ->name('outfit-subscription-checkout.cancel');

    Route::get('/clubs/{club}/members/{child}/guardians', [WebClubGuardianRelationshipController::class, 'index'])
        ->name('auth.clubs.members.guardians.index');
    Route::get('/clubs/{club}/members/{user}/relationships', [WebClubMemberRelationshipController::class, 'index'])
        ->name('auth.clubs.members.relationships.index');
    Route::post('/clubs/{club}/members/{user}/relationships', [WebClubMemberRelationshipController::class, 'store'])
        ->name('auth.clubs.members.relationships.store');
    Route::post('/clubs/{club}/members/{child}/guardians', [WebClubGuardianRelationshipController::class, 'store'])
        ->name('auth.clubs.members.guardians.store');
    Route::post('/clubs/{club}/members/{child}/guardians/{relationship}/accept', [WebClubGuardianRelationshipController::class, 'accept'])
        ->name('auth.clubs.members.guardians.accept');
    Route::post('/clubs/{club}/members/{child}/guardians/{relationship}/decline', [WebClubGuardianRelationshipController::class, 'decline'])
        ->name('auth.clubs.members.guardians.decline');
    Route::post('/clubs/{club}/members/{child}/guardians/{relationship}/revoke', [WebClubGuardianRelationshipController::class, 'revoke'])
        ->name('auth.clubs.members.guardians.revoke');
    Route::post('/clubs/{club}/members/{child}/guardians/{relationship}/primary', [WebClubGuardianRelationshipController::class, 'primary'])
        ->name('auth.clubs.members.guardians.primary');
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
