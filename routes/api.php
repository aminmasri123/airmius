<?php

use App\Http\Controllers\AccountRoleApplicationController;
use App\Http\Controllers\AdminClubController;
use App\Http\Controllers\Api\V1\AccountDeletionController as MobileAccountDeletionController;
use App\Http\Controllers\Api\V1\AccountSecurityController;
use App\Http\Controllers\Api\V1\AdminBackofficeController;
use App\Http\Controllers\Api\V1\AdminCommerceController as MobileAdminCommerceController;
use App\Http\Controllers\Api\V1\AdminInsightsController;
use App\Http\Controllers\Api\V1\AdminMailController;
use App\Http\Controllers\Api\V1\AdminOutfitController;
use App\Http\Controllers\Api\V1\AdminSystemController;
use App\Http\Controllers\Api\V1\AiCommunicationDraftController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChallengeController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ClubAccessHandoverController;
use App\Http\Controllers\Api\V1\ClubAnnouncementController;
use App\Http\Controllers\Api\V1\ClubBudgetController;
use App\Http\Controllers\Api\V1\ClubController;
use App\Http\Controllers\Api\V1\ClubDunningController;
use App\Http\Controllers\Api\V1\ClubFundingProgramController;
use App\Http\Controllers\Api\V1\ClubGovernanceController;
use App\Http\Controllers\Api\V1\ClubGovernanceMeetingController;
use App\Http\Controllers\Api\V1\ClubGuardianRelationshipController;
use App\Http\Controllers\Api\V1\ClubInventoryController;
use App\Http\Controllers\Api\V1\ClubJobController;
use App\Http\Controllers\Api\V1\ClubMasterDataChangeRequestController;
use App\Http\Controllers\Api\V1\ClubMemberCardController;
use App\Http\Controllers\Api\V1\ClubMemberQualificationController;
use App\Http\Controllers\Api\V1\ClubMemberRelationshipController;
use App\Http\Controllers\Api\V1\ClubMembershipProspectController;
use App\Http\Controllers\Api\V1\ClubMetadataController;
use App\Http\Controllers\Api\V1\ClubNewsletterController;
use App\Http\Controllers\Api\V1\ClubOrganizationController;
use App\Http\Controllers\Api\V1\ClubPermissionDelegationController;
use App\Http\Controllers\Api\V1\ClubPolicyDocumentController;
use App\Http\Controllers\Api\V1\ClubProcurementController;
use App\Http\Controllers\Api\V1\ClubRoleDefinitionController;
use App\Http\Controllers\Api\V1\ClubSepaBatchController;
use App\Http\Controllers\Api\V1\ClubServiceHourController;
use App\Http\Controllers\Api\V1\ClubStaffSchedulingController;
use App\Http\Controllers\Api\V1\ClubSurveyController;
use App\Http\Controllers\Api\V1\ClubTaskController;
use App\Http\Controllers\Api\V1\ClubVolunteerProfileController;
use App\Http\Controllers\Api\V1\ClubYearPeriodController;
use App\Http\Controllers\Api\V1\CommentController as MobileCommentController;
use App\Http\Controllers\Api\V1\CommerceController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DataErasureController;
use App\Http\Controllers\Api\V1\EditorialController;
use App\Http\Controllers\Api\V1\EventCompetitivenessController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\ExternalClubMemberController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\LearningController as MobileLearningController;
use App\Http\Controllers\Api\V1\MaturityController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MemberPortalController;
use App\Http\Controllers\Api\V1\MobileDeepLinkController;
use App\Http\Controllers\Api\V1\MobileEmailVerificationController;
use App\Http\Controllers\Api\V1\MobileMetaController;
use App\Http\Controllers\Api\V1\MobilePushDeviceController;
use App\Http\Controllers\Api\V1\MobileSyncController;
use App\Http\Controllers\Api\V1\MobileTwoFactorChallengeController;
use App\Http\Controllers\Api\V1\MobileTwoFactorController;
use App\Http\Controllers\Api\V1\MobileTwoFactorEmailCodeController;
use App\Http\Controllers\Api\V1\NotificationController as MobileNotificationController;
use App\Http\Controllers\Api\V1\NutritionController;
use App\Http\Controllers\Api\V1\PasswordRecoveryController;
use App\Http\Controllers\Api\V1\PlatformAdminController;
use App\Http\Controllers\Api\V1\PostImageUploadController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\PublicAgencyController;
use App\Http\Controllers\Api\V1\PublicContentController;
use App\Http\Controllers\Api\V1\PublicRecruitingController;
use App\Http\Controllers\Api\V1\PublicSelfServiceController;
use App\Http\Controllers\Api\V1\RecruitingPipelineController;
use App\Http\Controllers\Api\V1\RideController as MobileRideController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SponsorDeliverableManagementController;
use App\Http\Controllers\Api\V1\SponsorManagementController;
use App\Http\Controllers\Api\V1\SponsorWorkspaceController;
use App\Http\Controllers\Api\V1\SportIntegrationController as MobileSportIntegrationController;
use App\Http\Controllers\Api\V1\SportMapController as MobileSportMapController;
use App\Http\Controllers\Api\V1\SportMatchingController;
use App\Http\Controllers\Api\V1\SportProfileController;
use App\Http\Controllers\Api\V1\StoryController as MobileStoryController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\TeamBulkOrderController;
use App\Http\Controllers\Api\V1\TeamCompetitivenessController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamPenaltyController;
use App\Http\Controllers\Api\V1\TrainingAnalyticsController;
use App\Http\Controllers\Api\V1\TrainingAvailabilityController;
use App\Http\Controllers\Api\V1\TrainingController;
use App\Http\Controllers\Api\V1\TrainingExerciseController;
use App\Http\Controllers\Api\V1\TrainingFeedbackController;
use App\Http\Controllers\Api\V1\TrainingSessionController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\UserBadgeController as MobileUserBadgeController;
use App\Http\Controllers\Api\V1\UserSocialProfileController;
use App\Http\Controllers\Api\V1\WorkAutomationJobController;
use App\Http\Controllers\ClubDeletionController;
use App\Http\Controllers\ClubMemberTimelineController;
use App\Http\Controllers\CommerceCheckoutController as MobileCommerceCheckoutController;
use App\Http\Controllers\ContentReportController;
use App\Http\Controllers\CountryCatalogController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\KontaktController as MobileContactController;
use App\Http\Controllers\LearningStudioController as MobileLearningStudioController;
use App\Http\Controllers\MediaGuidelineController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OutfitSubscriptionController as MobileOutfitSubscriptionController;
use App\Http\Controllers\PublicClubController;
use App\Http\Controllers\PublicCommerceCatalogController;
use App\Http\Controllers\PublicLearningController as MobilePublicLearningController;
use App\Http\Controllers\PublicMarketplaceController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\TrainerCockpitController as MobileTrainerCockpitController;
use App\Http\Controllers\TrainingController as MobileTrainingAiController;
use App\Http\Middleware\EnsureApiCorsHeaders;
use App\Http\Middleware\EnsureApiProcessingPurpose;
use App\Http\Middleware\EnsureGuardianConsentResolved;
use App\Http\Middleware\EnsureIdempotentApiRequest;
use App\Http\Middleware\EnsurePlatformAdminTwoFactor;
use App\Http\Resources\Api\V1\ClubMembershipRequestResource;
use App\Http\Resources\Api\V1\ClubResource;
use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\Post;
use App\Models\Sport;
use App\Models\Team;
use App\Services\ClubService;
use App\Services\TeamDailyLifeService;
use App\Support\ClubProfilePermissions;
use App\Support\UploadStorage;
use App\Support\Validation\ClubProfileRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/country-catalog', [CountryCatalogController::class, 'index'])->name('country-catalog.index');
    Route::post('/country-catalog', [CountryCatalogController::class, 'store'])->middleware(['auth:sanctum', 'throttle:20,1'])->name('country-catalog.store');
    Route::options('/{any}', fn () => response('', 204))
        ->middleware(EnsureApiCorsHeaders::class)
        ->where('any', '.*')
        ->name('options');

    Route::get('/meta', MobileMetaController::class)->name('meta');
    Route::get('/newsletter/confirm/{token}', [ClubNewsletterController::class, 'confirm'])
        ->where('token', '[A-Za-z0-9]+')
        ->name('newsletter.confirm');
    Route::get('/newsletter/unsubscribe/{token}', [ClubNewsletterController::class, 'unsubscribe'])
        ->where('token', '[A-Za-z0-9.]+')
        ->name('newsletter.unsubscribe');

    Route::middleware(EnsureApiProcessingPurpose::class)->group(function () {
        Route::get('/public/blog', [PublicContentController::class, 'blog'])
            ->middleware('throttle:public-content')
            ->name('public.blog.index');
        Route::get('/public/blog/{blogPost:slug}', [PublicContentController::class, 'blogPost'])
            ->middleware('throttle:public-content')
            ->name('public.blog.show');
        Route::get('/public/sponsors', [PublicContentController::class, 'sponsors'])
            ->middleware('throttle:public-content')
            ->name('public.sponsors.index');
        Route::get('/public/recruiting/jobs', [PublicRecruitingController::class, 'index'])
            ->middleware('throttle:public-content')
            ->name('public.recruiting.jobs.index');
        Route::post('/public/recruiting/jobs/{organizationJob}/interest', [PublicRecruitingController::class, 'submitInterest'])
            ->middleware(['throttle:content-reports', EnsureIdempotentApiRequest::class])
            ->name('public.recruiting.jobs.interest');
        Route::post('/public/agency/requests', [PublicAgencyController::class, 'store'])
            ->middleware(['throttle:content-reports', EnsureIdempotentApiRequest::class])
            ->name('public.agency.requests.store');
        Route::get('/public/clubs', [PublicClubController::class, 'indexJson'])
            ->middleware('throttle:public-content')
            ->name('public.clubs.index');
        Route::get('/public/marketplace', [PublicMarketplaceController::class, 'indexJson'])
            ->middleware('throttle:public-content')
            ->name('public.marketplace.index');
        Route::get('/public/commerce/catalog', PublicCommerceCatalogController::class)
            ->middleware('throttle:public-content')
            ->name('public.commerce.catalog');
        Route::get('/public/learning/certificates/{code}', [MobilePublicLearningController::class, 'verifyCertificateJson'])
            ->where('code', '[A-Za-z0-9_-]+')
            ->middleware('throttle:public-content')
            ->name('public.learning.certificates.verify');
        Route::get('/public/learning/courses', [MobilePublicLearningController::class, 'indexJson'])
            ->middleware('throttle:public-content')
            ->name('public.learning.courses.index');
        Route::post('/public/clubs/{club}/membership-applications', [PublicSelfServiceController::class, 'storeMembershipApplication'])
            ->middleware(['throttle:public-self-service', EnsureIdempotentApiRequest::class])
            ->name('public.membership-applications.store');
        Route::get('/public/membership-applications/status/{token}', [PublicSelfServiceController::class, 'membershipApplicationStatus'])
            ->where('token', '[A-Za-z0-9]+')
            ->middleware('throttle:public-status')
            ->name('public.membership-applications.status');
        Route::post('/public/learning/courses/{course}/bookings', [PublicSelfServiceController::class, 'storeCourseBooking'])
            ->middleware(['throttle:public-self-service', EnsureIdempotentApiRequest::class])
            ->name('public.learning.bookings.store');
        Route::get('/public/learning/bookings/status/{token}', [PublicSelfServiceController::class, 'courseBookingStatus'])
            ->where('token', '[A-Za-z0-9]+')
            ->middleware('throttle:public-status')
            ->name('public.learning.bookings.status');
        Route::post('/public/contact', [MobileContactController::class, 'store'])
            ->middleware('throttle:content-reports')
            ->name('public.contact.store');
        Route::post('/safety/reports', [SupportTicketController::class, 'storeSafetyReport'])
            ->middleware('throttle:content-reports')
            ->name('safety.reports.store');
    });
    Route::get('/posts/{post}/image', function (Request $request, Post $post) {
        Gate::forUser($request->user())->authorize('view', $post);
        abort_unless($post->image, 404);

        if (str_starts_with($post->image, 'http://') || str_starts_with($post->image, 'https://')) {
            return redirect()->away($post->image);
        }

        try {
            return Storage::disk(UploadStorage::disk($post->image))->response($post->image);
        } catch (Throwable) {
            abort(404);
        }
    })->middleware('auth:sanctum')->name('posts.image');

    Route::get('/posts/{post}/media/{name}', function (Request $request, Post $post, string $name) {
        Gate::forUser($request->user())->authorize('view', $post);
        $path = 'private-post-media/'.$post->id.'/'.$name;
        abort_unless($post->image === $path || $post->files()->where(function ($query) use ($path) {
            $query->where('path', $path)->orWhere('thumbnail_path', $path);
        })->exists(), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->where('name', '[A-Za-z0-9._-]+')->middleware('auth:sanctum')->name('posts.media');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::get('/auth/register/email', [AuthController::class, 'registrationEmail'])
        ->middleware('throttle:20,1')
        ->name('auth.register.email');

    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('auth.register');
    Route::post('/auth/forgot-password', [PasswordRecoveryController::class, 'sendResetLink'])
        ->middleware('throttle:5,1')
        ->name('auth.password.email');
    Route::post('/auth/reset-password', [PasswordRecoveryController::class, 'reset'])
        ->middleware('throttle:5,1')
        ->name('auth.password.reset');
    Route::post('/auth/two-factor-challenge', MobileTwoFactorChallengeController::class)
        ->middleware('throttle:6,1')
        ->name('auth.two-factor.challenge');
    Route::post('/auth/two-factor-challenge/email-code', MobileTwoFactorEmailCodeController::class)
        ->middleware('throttle:3,1')
        ->name('auth.two-factor.email-code');
    Route::get('/auth/verify-email/{id}/{hash}', [MobileEmailVerificationController::class, 'verify'])
        ->middleware(['signed:relative', 'throttle:10,1'])
        ->whereNumber('id')
        ->name('auth.email.verify');

    Route::post('/teams', [TeamController::class, 'storeWithToken'])
        ->middleware(['throttle:30,1', EnsureApiCorsHeaders::class])
        ->name('teams.store.token');

    Route::post('/clubs/{club}/members/invite-token', [ClubController::class, 'inviteMemberWithToken'])
        ->middleware(['throttle:30,1', EnsureApiCorsHeaders::class])
        ->name('clubs.members.invite.token');

    Route::middleware(['auth:sanctum', EnsureGuardianConsentResolved::class, EnsureIdempotentApiRequest::class, EnsureApiProcessingPurpose::class])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/role-applications', [AccountRoleApplicationController::class, 'index'])->name('role-applications.index');
        Route::post('/role-applications', [AccountRoleApplicationController::class, 'store'])->name('role-applications.store');
        Route::post('/me/email/verification-notification', [MobileEmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('me.email.verification.send');
        Route::get('/me/two-factor-authentication', [MobileTwoFactorController::class, 'show'])
            ->name('me.two-factor.show');
        Route::post('/me/two-factor-authentication', [MobileTwoFactorController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('me.two-factor.store');
        Route::post('/me/two-factor-authentication/confirm', [MobileTwoFactorController::class, 'confirm'])
            ->middleware('throttle:6,1')
            ->name('me.two-factor.confirm');
        Route::delete('/me/two-factor-authentication', [MobileTwoFactorController::class, 'destroy'])
            ->middleware('throttle:5,1')
            ->name('me.two-factor.destroy');
        Route::post('/me/two-factor-recovery-codes', [MobileTwoFactorController::class, 'regenerateRecoveryCodes'])
            ->middleware('throttle:5,1')
            ->name('me.two-factor.recovery-codes');
        Route::post('/account/deletion-code', [MobileAccountDeletionController::class, 'sendCode'])
            ->middleware('throttle:5,1')
            ->name('account.deletion-code');
        Route::delete('/account', [MobileAccountDeletionController::class, 'destroy'])
            ->middleware('throttle:5,1')
            ->name('account.destroy');

        Route::get('/me', [MeController::class, 'show'])->name('me.show');
        Route::put('/me/profile', [MeController::class, 'updateProfile'])->name('me.profile.update');
        Route::patch('/me/language', [MeController::class, 'updateLanguage'])
            ->middleware('purpose:product_operation')
            ->name('me.language');
        Route::get('/guardian/consent', [GuardianController::class, 'consentStatus'])
            ->name('guardian.consent.show');
        Route::post('/guardian/consent/resend', [GuardianController::class, 'resendOwnConsent'])
            ->middleware('throttle:10,1')
            ->name('guardian.consent.resend');
        Route::get('/guardian/children', [GuardianController::class, 'index'])
            ->name('guardian.children.index');
        Route::get('/guardian/children/{child}', [GuardianController::class, 'child'])
            ->whereNumber('child')
            ->name('guardian.children.show');
        Route::post('/guardian/children/{child}/approve', [GuardianController::class, 'approve'])
            ->whereNumber('child')
            ->middleware('throttle:20,1')
            ->name('guardian.children.approve');
        Route::post('/guardian/children/{child}/revoke', [GuardianController::class, 'revoke'])
            ->whereNumber('child')
            ->middleware('throttle:20,1')
            ->name('guardian.children.revoke');
        Route::post('/guardian/children/{child}/resend', [GuardianController::class, 'resend'])
            ->whereNumber('child')
            ->middleware('throttle:10,1')
            ->name('guardian.children.resend');
        Route::get('/guardian/invitations', [GuardianController::class, 'invitations'])
            ->name('guardian.invitations.index');
        Route::post('/guardian/invitations/{relationship}/accept', [GuardianController::class, 'acceptInvitation'])
            ->whereNumber('relationship')
            ->middleware('throttle:20,1')
            ->name('guardian.invitations.accept');
        Route::post('/guardian/invitations/{relationship}/decline', [GuardianController::class, 'declineInvitation'])
            ->whereNumber('relationship')
            ->middleware('throttle:20,1')
            ->name('guardian.invitations.decline');
        Route::put('/me/password', [AccountSecurityController::class, 'updatePassword'])
            ->middleware('throttle:5,1')
            ->name('me.password.update');
        Route::post('/me/profile-photo', [AccountSecurityController::class, 'updateProfilePhoto'])
            ->middleware('throttle:file-uploads')
            ->name('me.profile-photo.update');
        Route::delete('/me/profile-photo', [AccountSecurityController::class, 'destroyProfilePhoto'])
            ->name('me.profile-photo.destroy');
        Route::get('/me/sessions', [AccountSecurityController::class, 'sessions'])->name('me.sessions.index');
        Route::delete('/me/sessions/others', [AccountSecurityController::class, 'destroyOtherSessions'])
            ->name('me.sessions.destroy-others');
        Route::delete('/me/sessions/{token}', [AccountSecurityController::class, 'destroySession'])
            ->whereNumber('token')
            ->name('me.sessions.destroy');

        Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/portal', [MemberPortalController::class, 'show'])->name('portal.show');
        Route::get('/portal/{section}', [MemberPortalController::class, 'details'])
            ->whereIn('section', ['memberships', 'invoices', 'payments', 'refunds', 'requests'])
            ->name('portal.details');
        Route::get('/privacy', [PrivacyController::class, 'show'])->name('privacy.show');
        Route::get('/privacy/rights-process', [PrivacyController::class, 'rightsProcess'])->name('privacy.rights-process');
        Route::get('/privacy/processing-activities', [PrivacyController::class, 'processingActivities'])->name('privacy.processing-activities');
        Route::get('/privacy/export', [PrivacyController::class, 'export'])->name('privacy.export');
        Route::patch('/privacy/correction', [PrivacyController::class, 'correct'])->name('privacy.correct');
        Route::post('/privacy/withdraw-consents', [PrivacyController::class, 'withdrawConsents'])->name('privacy.withdraw-consents');
        Route::post('/privacy/data-erasure-code', [DataErasureController::class, 'sendCode'])
            ->middleware('throttle:5,1')
            ->name('privacy.data-erasure.code');
        Route::post('/privacy/data-erasure', [DataErasureController::class, 'destroy'])
            ->middleware('throttle:3,1')
            ->name('privacy.data-erasure.destroy');
        Route::get('/billing/invoices', [SettingsController::class, 'invoices'])->name('billing.invoices.index');
        Route::get('/billing/invoices/{kind}/{invoice}/download', [\App\Http\Controllers\MemberBillingInvoiceController::class, 'download'])->whereNumber('invoice')->name('billing.invoices.download');
        Route::post('/billing/invoices/{kind}/{invoice}/question', [\App\Http\Controllers\MemberBillingInvoiceController::class, 'question'])->whereNumber('invoice')->middleware('throttle:5,1')->name('billing.invoices.question');
        Route::get('/billing/invoices/{invoice}', [SettingsController::class, 'invoice'])->whereNumber('invoice')->name('billing.invoices.show');
        Route::get('/dashboard/daily-flow', [DashboardController::class, 'dailyFlow'])->middleware('rollout:coach_daily_control')->name('dashboard.daily-flow');
        Route::match(['get', 'post'], '/mobile/sync', MobileSyncController::class)->name('mobile.sync');
        Route::post('/mobile/push-devices', [MobilePushDeviceController::class, 'store'])->name('mobile.push-devices.store');
        Route::delete('/mobile/push-devices/{deviceId}', [MobilePushDeviceController::class, 'destroy'])->name('mobile.push-devices.destroy');
        Route::post('/mobile/deep-links/resolve', [MobileDeepLinkController::class, 'resolve'])->name('mobile.deep-links.resolve');
        Route::get('/search', GlobalSearchController::class)->middleware('throttle:global-search')->name('search');
        Route::post('/ai/communication-drafts', AiCommunicationDraftController::class)
            ->middleware('throttle:10,1')
            ->name('ai.communication-drafts');
        Route::get('/saved-views', [SavedViewController::class, 'index'])->name('saved-views.index');
        Route::post('/saved-views', [SavedViewController::class, 'store'])->name('saved-views.store');
        Route::put('/saved-views/{savedView}', [SavedViewController::class, 'update'])->whereNumber('savedView')->name('saved-views.update');
        Route::delete('/saved-views/{savedView}', [SavedViewController::class, 'destroy'])->whereNumber('savedView')->name('saved-views.destroy');
        Route::get('/sports', function () {
            return response()->json([
                'data' => Sport::query()
                    ->where('is_active', true)
                    ->with(['skills' => fn ($query) => $query
                        ->select('id', 'sport_id', 'key', 'name')
                        ->orderBy('sort_order')])
                    ->select(['id', 'name', 'slug', 'category', 'sort_order'])
                    ->orderBy('sort_order')
                    ->get(),
            ]);
        })->name('sports.index');

        Route::middleware('rollout:growth_workspaces')->group(function () {
            Route::get('/sponsor-workspace', [SponsorWorkspaceController::class, 'index'])
                ->name('sponsor-workspace.index');
            Route::put('/sponsor-workspace/profile', [SponsorWorkspaceController::class, 'updateProfile'])
                ->name('sponsor-workspace.profile.update');
            Route::get('/recruiting-pipeline', [RecruitingPipelineController::class, 'index'])
                ->name('recruiting-pipeline.index');
            Route::put('/recruiting-pipeline/applications/{interest}', [RecruitingPipelineController::class, 'update'])
                ->name('recruiting-pipeline.applications.update');
            Route::post('/recruiting-pipeline/applications/{interest}/chat', [RecruitingPipelineController::class, 'chat'])
                ->name('recruiting-pipeline.applications.chat');
            Route::delete('/recruiting-pipeline/applications/{interest}', [RecruitingPipelineController::class, 'destroy'])
                ->name('recruiting-pipeline.applications.destroy');
        });

        Route::get('/users/me/sport-cv', [SportProfileController::class, 'me'])->name('users.me.sport-cv');
        Route::get('/sport-integrations', [MobileSportIntegrationController::class, 'index'])
            ->name('sport-integrations.index');
        Route::post('/sport-integrations/{provider}/request', [MobileSportIntegrationController::class, 'requestProvider'])
            ->where('provider', '[a-z0-9_-]+')
            ->middleware('throttle:10,1')
            ->name('sport-integrations.request');
        Route::post('/sport-integrations/accounts/{account}/sync', [MobileSportIntegrationController::class, 'sync'])
            ->whereNumber('account')
            ->middleware('throttle:10,1')
            ->name('sport-integrations.sync');
        Route::delete('/sport-integrations/accounts/{account}', [MobileSportIntegrationController::class, 'disconnect'])
            ->whereNumber('account')
            ->name('sport-integrations.disconnect');
        Route::post('/sport-integrations/activities/import', [MobileSportIntegrationController::class, 'importActivity'])
            ->middleware('throttle:20,1')
            ->name('sport-integrations.activities.import');
        Route::put('/sport-integrations/activities/{activity}', [MobileSportIntegrationController::class, 'updateActivity'])
            ->whereNumber('activity')
            ->name('sport-integrations.activities.update');
        Route::delete('/sport-integrations/activities/{activity}', [MobileSportIntegrationController::class, 'destroyActivity'])
            ->whereNumber('activity')
            ->name('sport-integrations.activities.destroy');
        Route::get('/users/{user}/sport-cv', [SportProfileController::class, 'show'])->name('users.sport-cv.show');
        Route::post('/users/{user}/follow', [UserSocialProfileController::class, 'follow'])->name('users.follow');
        Route::delete('/users/{user}/follow', [UserSocialProfileController::class, 'unfollow'])->name('users.unfollow');
        Route::post('/users/{user}/block', [UserSocialProfileController::class, 'block'])->name('users.block');
        Route::delete('/users/{user}/block', [UserSocialProfileController::class, 'unblock'])->name('users.unblock');
        Route::get('/sport-profiles/scout-search', [SportProfileController::class, 'scoutSearch'])->name('sport-profiles.scout-search');
        Route::get('/sport-profiles', [SportProfileController::class, 'index'])->name('sport-profiles.index');
        Route::put('/sport-profiles/{sport}', [SportProfileController::class, 'update'])->name('sport-profiles.update');
        Route::delete('/sport-profiles/{sport}', [SportProfileController::class, 'destroy'])->name('sport-profiles.destroy');
        Route::patch('/sport-skills/{userSportSkill}', [SportProfileController::class, 'updateSkill'])->name('sport-skills.update');

        Route::get('/notifications', [MobileNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{notification}', [MobileNotificationController::class, 'show'])->name('notifications.show');
        Route::post('/notifications/read-all', [MobileNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [MobileNotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/{notification}/unread', [MobileNotificationController::class, 'markAsUnread'])->name('notifications.unread');
        Route::delete('/notifications/{notification}', [MobileNotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::get('/badges', [MobileUserBadgeController::class, 'index'])->name('badges.index');
        Route::get('/badges/{userBadge}', [MobileUserBadgeController::class, 'show'])->name('badges.show');
        Route::get('/learning', [MobileLearningController::class, 'index'])->name('learning.index');
        Route::get('/learning/courses/{course}', [MobileLearningController::class, 'show'])->name('learning.courses.show');
        Route::post('/learning/courses/{course}/enroll', [MobileLearningController::class, 'enroll'])->name('learning.courses.enroll');
        Route::put('/learning/courses/{course}/lessons/{lesson}/complete', [MobileLearningController::class, 'completeLesson'])->name('learning.lessons.complete');
        Route::put('/learning/courses/{course}/lessons/{lesson}/progress', [MobileLearningController::class, 'trackProgress'])->name('learning.lessons.progress');
        Route::post('/learning/courses/{course}/lessons/{lesson}/notes', [MobileLearningController::class, 'storeNote'])->name('learning.lessons.notes.store');
        Route::post('/learning/courses/{course}/lessons/{lesson}/comments', [MobileLearningController::class, 'storeComment'])->name('learning.lessons.comments.store');
        Route::post('/learning/courses/{course}/quizzes/{quiz}/attempts', [MobileLearningController::class, 'submitQuiz'])->name('learning.quizzes.attempts.store');
        Route::post('/learning/courses/{course}/assignments/{assignment}/submissions', [MobileLearningController::class, 'submitAssignment'])->name('learning.assignments.submissions.store');
        Route::post('/learning/courses/{course}/reviews', [MobileLearningController::class, 'storeReview'])->name('learning.reviews.store');
        Route::get('/learning/certificates/{certificate}', [MobileLearningController::class, 'certificate'])->name('learning.certificates.show');
        Route::get('/learning/certificates/{certificate}/download', [MobilePublicLearningController::class, 'downloadCertificate'])->name('learning.certificates.download');
        Route::get('/learning-studio', [MobileLearningStudioController::class, 'index'])->name('learning-studio.index');
        Route::get('/admin/learning/courses', [MobileLearningStudioController::class, 'qualityIndex'])->middleware([EnsurePlatformAdminTwoFactor::class, 'verified', 'can:subscriptions.manage'])->name('admin.learning.courses.index');
        Route::put('/admin/learning/courses/{course}/quality', [MobileLearningStudioController::class, 'updateQuality'])->middleware([EnsurePlatformAdminTwoFactor::class, 'verified', 'can:subscriptions.manage'])->name('admin.learning.courses.quality.update');
        Route::post('/learning-studio/courses', [MobileLearningStudioController::class, 'storeCourse'])->name('learning-studio.courses.store');
        Route::put('/learning-studio/courses/{course}', [MobileLearningStudioController::class, 'updateCourse'])->name('learning-studio.courses.update');
        Route::post('/learning-studio/courses/{course}/sections', [MobileLearningStudioController::class, 'storeSection'])->name('learning-studio.sections.store');
        Route::post('/learning-studio/courses/{course}/lessons', [MobileLearningStudioController::class, 'storeLesson'])->name('learning-studio.lessons.store');
        Route::put('/learning-studio/courses/{course}/lessons/reorder', [MobileLearningStudioController::class, 'reorderLessons'])->name('learning-studio.lessons.reorder');
        Route::put('/learning-studio/courses/{course}/lessons/{lesson}', [MobileLearningStudioController::class, 'updateLesson'])->name('learning-studio.lessons.update');
        Route::delete('/learning-studio/courses/{course}/lessons/{lesson}', [MobileLearningStudioController::class, 'destroyLesson'])->name('learning-studio.lessons.destroy');
        Route::post('/learning-studio/courses/{course}/uploads', [MobileLearningStudioController::class, 'uploadAsset'])->name('learning-studio.uploads.store');
        Route::get('/learning-studio/courses/{course}/report.csv', [MobileLearningStudioController::class, 'exportReport'])->name('learning-studio.courses.report');
        Route::get('/learning-studio/courses/{course}/offer-evaluation', [MobileLearningStudioController::class, 'offerEvaluation'])->name('learning-studio.courses.offer-evaluation');
        Route::get('/learning-studio/courses/{course}/offer-evaluation.csv', [MobileLearningStudioController::class, 'exportOfferEvaluation'])->name('learning-studio.courses.offer-evaluation.export');
        Route::put('/learning-studio/courses/{course}/comments/{comment}', [MobileLearningStudioController::class, 'resolveComment'])->name('learning-studio.comments.update');
        Route::post('/learning-studio/courses/{course}/comments/{comment}/replies', [MobileLearningStudioController::class, 'replyComment'])->name('learning-studio.comments.replies.store');
        Route::post('/learning-studio/courses/{course}/enrollments', [MobileLearningStudioController::class, 'grantEnrollment'])->name('learning-studio.enrollments.store');
        Route::get('/learning-studio/courses/{course}/enrollments/{enrollment}/participation-confirmation', [MobileLearningStudioController::class, 'participationConfirmation'])->name('learning-studio.enrollments.participation-confirmation');
        Route::put('/learning-studio/courses/{course}/enrollments/{enrollment}/revoke', [MobileLearningStudioController::class, 'revokeEnrollment'])->name('learning-studio.enrollments.revoke');
        Route::post('/learning-studio/courses/{course}/quizzes', [MobileLearningStudioController::class, 'storeQuiz'])->name('learning-studio.quizzes.store');
        Route::delete('/learning-studio/courses/{course}/quizzes/{quiz}', [MobileLearningStudioController::class, 'destroyQuiz'])->name('learning-studio.quizzes.destroy');
        Route::post('/learning-studio/courses/{course}/assignments', [MobileLearningStudioController::class, 'storeAssignment'])->name('learning-studio.assignments.store');
        Route::put('/learning-studio/courses/{course}/assignment-submissions/{submission}', [MobileLearningStudioController::class, 'gradeAssignment'])->name('learning-studio.assignment-submissions.update');
        Route::post('/learning-studio/courses/{course}/coupons', [MobileLearningStudioController::class, 'storeCoupon'])->name('learning-studio.coupons.store');
        Route::get('/editorial/posts', [EditorialController::class, 'index'])->name('editorial.posts.index');
        Route::post('/editorial/preview', [EditorialController::class, 'preview'])->name('editorial.preview');
        Route::post('/editorial/images', [EditorialController::class, 'uploadImage'])->middleware('throttle:file-uploads')->name('editorial.images.store');
        Route::post('/editorial/posts', [EditorialController::class, 'store'])->name('editorial.posts.store');
        Route::put('/editorial/posts/{blogPost}', [EditorialController::class, 'update'])->name('editorial.posts.update');
        Route::delete('/editorial/posts/{blogPost}', [EditorialController::class, 'destroy'])->name('editorial.posts.destroy');
        Route::post('/editorial/categories', [EditorialController::class, 'storeCategory'])->name('editorial.categories.store');
        Route::put('/editorial/categories/{blogCategory}', [EditorialController::class, 'updateCategory'])->name('editorial.categories.update');
        Route::delete('/editorial/categories/{blogCategory}', [EditorialController::class, 'destroyCategory'])->name('editorial.categories.destroy');
        Route::get('/sponsor-management', [SponsorManagementController::class, 'index'])->name('sponsor-management.index');
        Route::post('/sponsor-management', [SponsorManagementController::class, 'store'])->name('sponsor-management.store');
        Route::put('/sponsor-management/{sponsor}', [SponsorManagementController::class, 'update'])->name('sponsor-management.update');
        Route::delete('/sponsor-management/{sponsor}', [SponsorManagementController::class, 'destroy'])->name('sponsor-management.destroy');
        Route::post('/sponsor-management/{sponsor}/deliverables', [SponsorDeliverableManagementController::class, 'store'])->name('sponsor-management.deliverables.store');
        Route::put('/sponsor-management/{sponsor}/deliverables/{deliverable}', [SponsorDeliverableManagementController::class, 'update'])->name('sponsor-management.deliverables.update');
        Route::delete('/sponsor-management/{sponsor}/deliverables/{deliverable}', [SponsorDeliverableManagementController::class, 'destroy'])->name('sponsor-management.deliverables.destroy');
        Route::get('/rides', [MobileRideController::class, 'index'])->name('rides.index');
        Route::post('/rides', [MobileRideController::class, 'store'])->name('rides.store');
        Route::put('/rides/{ride}', [MobileRideController::class, 'update'])->name('rides.update');
        Route::post('/rides/{ride}/join', [MobileRideController::class, 'join'])->name('rides.join');
        Route::post('/rides/{ride}/leave', [MobileRideController::class, 'leave'])->name('rides.leave');
        Route::post('/rides/{ride}/requests/{user}/approve', [MobileRideController::class, 'approveRequest'])->name('rides.requests.approve');
        Route::post('/rides/{ride}/requests/{user}/reject', [MobileRideController::class, 'rejectRequest'])->name('rides.requests.reject');
        Route::delete('/rides/{ride}/members/{user}', [MobileRideController::class, 'removeMember'])->name('rides.members.destroy');
        Route::delete('/rides/{ride}', [MobileRideController::class, 'destroy'])->name('rides.destroy');

        Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');
        Route::get('/users/{user}/posts', [FeedController::class, 'userPosts'])->name('users.posts.index');
        Route::post('/post-images', PostImageUploadController::class)->middleware('throttle:file-uploads')->name('post-images.store');
        Route::post('/feed', [FeedController::class, 'store'])->name('feed.store');
        Route::get('/posts/{post}', [FeedController::class, 'show'])->name('posts.show');
        Route::put('/posts/{post}', [FeedController::class, 'update'])->name('posts.update');
        Route::post('/posts/{post}', [FeedController::class, 'update'])->name('posts.update.multipart');
        Route::get('/posts/{post}/comments', [MobileCommentController::class, 'index'])->name('posts.comments.index');
        Route::post('/posts/{post}/comments', [MobileCommentController::class, 'store'])->middleware('throttle:content-comments')->name('posts.comments.store');
        Route::post('/posts/{post}/like', [FeedController::class, 'toggleLike'])->name('posts.like');
        Route::post('/posts/{post}/helpful', [FeedController::class, 'toggleHelpful'])->name('posts.helpful');
        Route::post('/posts/{post}/delete', [FeedController::class, 'destroy'])->name('posts.destroy.post');
        Route::delete('/posts/{post}', [FeedController::class, 'destroy'])->name('posts.destroy');
        Route::put('/comments/{comment}', [MobileCommentController::class, 'update'])->middleware('throttle:content-comments')->name('comments.update');
        Route::delete('/comments/{comment}', [MobileCommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('/reports', [ContentReportController::class, 'store'])->middleware('throttle:content-reports')->name('reports.store');
        Route::post('/reports/{report}/appeal', [ContentReportController::class, 'appeal'])->middleware('throttle:content-reports')->name('reports.appeal');
        Route::post('/support/contact', [MobileContactController::class, 'store'])->middleware('throttle:content-reports')->name('support.contact.store');
        Route::get('/support/tickets', [SupportTicketController::class, 'index'])->name('support.tickets.index');
        Route::post('/support/tickets', [SupportTicketController::class, 'store'])->middleware('throttle:content-reports')->name('support.tickets.store');
        Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
        Route::post('/friends/invitations', [FriendController::class, 'store'])->middleware('throttle:6,1')->name('friends.invitations.store');
        Route::get('/friends/invitations/token/{token}', [FriendController::class, 'invitationByToken'])->name('friends.invitations.token.show');
        Route::post('/friends/invitations/token/{token}/accept', [FriendController::class, 'acceptByToken'])->name('friends.invitations.token.accept');
        Route::post('/friends/invitations/token/{token}/decline', [FriendController::class, 'declineByToken'])->name('friends.invitations.token.decline');
        Route::post('/friends/invitations/{invitation}/accept', [FriendController::class, 'accept'])->name('friends.invitations.accept');
        Route::post('/friends/invitations/{invitation}/decline', [FriendController::class, 'decline'])->name('friends.invitations.decline');
        Route::delete('/friends/invitations/{invitation}', [FriendController::class, 'withdraw'])->name('friends.invitations.withdraw');
        Route::delete('/friends/{user}', [FriendController::class, 'destroy'])->name('friends.destroy');
        Route::get('/maturity/feed-discovery', [MaturityController::class, 'feedDiscovery'])->name('maturity.feed-discovery');
        Route::get('/maturity/feed-trending', [MaturityController::class, 'feedTrending'])->name('maturity.feed-trending');
        Route::get('/maturity/overview', [MaturityController::class, 'overview'])->name('maturity.overview');
        Route::get('/maturity/motivation', [MaturityController::class, 'motivation'])->name('maturity.motivation');
        Route::get('/maturity/search', [MaturityController::class, 'search'])->name('maturity.search');
        Route::get('/maturity/challenges', [MaturityController::class, 'challenges'])->name('maturity.challenges');
        Route::get('/maturity/routes/{sportRoute}/analytics', [MaturityController::class, 'routeAnalytics'])->name('maturity.route-analytics');
        Route::get('/maturity/coach-weekly', [MaturityController::class, 'coachWeekly'])->name('maturity.coach-weekly');
        Route::get('/maturity/onboarding', [MaturityController::class, 'onboarding'])->name('maturity.onboarding');
        Route::get('/maturity/viral', [MaturityController::class, 'viral'])->name('maturity.viral');
        Route::get('/maturity/safety', [MaturityController::class, 'safety'])->name('maturity.safety');
        Route::get('/stories', [MobileStoryController::class, 'index'])->name('stories.index');
        Route::post('/stories', [MobileStoryController::class, 'store'])->name('stories.store');
        Route::post('/stories/{story}/viewed', [MobileStoryController::class, 'viewed'])->name('stories.viewed');
        Route::post('/stories/{story}/react', [MobileStoryController::class, 'react'])->name('stories.react');
        Route::delete('/stories/{story}', [MobileStoryController::class, 'destroy'])->name('stories.destroy');

        Route::get('/clubs', [ClubController::class, 'index'])->name('clubs.index');
        Route::post('/clubs', [ClubController::class, 'store'])->name('clubs.store');
        Route::get('/clubs/{club}', [ClubController::class, 'show'])->name('clubs.show');
        Route::post('/clubs/{club}/images', [ClubController::class, 'updateImages'])->name('clubs.images.update');
        Route::get('/clubs/{club}/jobs', [ClubJobController::class, 'index'])->name('clubs.jobs.index');
        Route::post('/clubs/{club}/jobs', [ClubJobController::class, 'store'])->name('clubs.jobs.store');
        Route::put('/clubs/{club}/jobs/{organizationJob}', [ClubJobController::class, 'update'])->name('clubs.jobs.update');
        Route::delete('/clubs/{club}/jobs/{organizationJob}', [ClubJobController::class, 'destroy'])->name('clubs.jobs.destroy');
        Route::put('/clubs/{club}', function (Request $request, Club $club, ClubService $clubs) {
            ClubProfilePermissions::authorizeUpdate($club, $request->user(), array_keys($request->all()));

            $data = $request->validate(ClubProfileRules::update());

            $clubs->update($club, $data, $request->user());
            $club->refresh()->loadCount(['users', 'teams']);

            return response()->json([
                'message' => 'Verein aktualisiert.',
                'data' => (new ClubResource($club))->resolve($request),
            ]);
        })->name('clubs.update');
        Route::delete('/clubs/{club}', [ClubController::class, 'destroy'])->name('clubs.destroy');
        Route::get('/clubs/{club}/deletion', [ClubDeletionController::class, 'show'])->name('clubs.deletion.show');
        Route::delete('/clubs/{club}/deletion', [ClubDeletionController::class, 'destroy'])->name('clubs.deletion.cancel');
        Route::get('/clubs/{club}/budgets', [ClubBudgetController::class, 'index'])->name('clubs.budgets.index');
        Route::post('/clubs/{club}/budgets', [ClubBudgetController::class, 'store'])->name('clubs.budgets.store');
        Route::put('/clubs/{club}/budgets/{budget}', [ClubBudgetController::class, 'update'])->name('clubs.budgets.update');
        Route::put('/clubs/{club}/budgets/{budget}/approval', [ClubBudgetController::class, 'approve'])->name('clubs.budgets.approval.update');
        Route::post('/clubs/{club}/dunning-rules', [ClubDunningController::class, 'storeRule'])->name('clubs.dunning-rules.store');
        Route::post('/clubs/{club}/invoices/{invoice}/dunning-events', [ClubDunningController::class, 'record'])->name('clubs.invoices.dunning-events.store');
        Route::post('/clubs/{club}/membership-invoices/{invoice}/dunning-events', [ClubDunningController::class, 'record'])->name('clubs.membership-invoices.dunning-events.store');
        Route::get('/clubs/{club}/organization', [ClubOrganizationController::class, 'index'])->name('clubs.organization.index');
        Route::post('/clubs/{club}/organization/departments', [ClubOrganizationController::class, 'storeDepartment'])->name('clubs.organization.departments.store');
        Route::put('/clubs/{club}/organization/departments/{department}', [ClubOrganizationController::class, 'updateDepartment'])->name('clubs.organization.departments.update');
        Route::delete('/clubs/{club}/organization/departments/{department}', [ClubOrganizationController::class, 'destroyDepartment'])->name('clubs.organization.departments.destroy');
        Route::post('/clubs/{club}/organization/locations', [ClubOrganizationController::class, 'storeLocation'])->name('clubs.organization.locations.store');
        Route::put('/clubs/{club}/organization/locations/{location}', [ClubOrganizationController::class, 'updateLocation'])->name('clubs.organization.locations.update');
        Route::delete('/clubs/{club}/organization/locations/{location}', [ClubOrganizationController::class, 'destroyLocation'])->name('clubs.organization.locations.destroy');
        Route::post('/clubs/{club}/organization/training-groups', [ClubOrganizationController::class, 'storeTrainingGroup'])->name('clubs.organization.training-groups.store');
        Route::put('/clubs/{club}/organization/training-groups/{trainingGroup}', [ClubOrganizationController::class, 'updateTrainingGroup'])->name('clubs.organization.training-groups.update');
        Route::delete('/clubs/{club}/organization/training-groups/{trainingGroup}', [ClubOrganizationController::class, 'destroyTrainingGroup'])->name('clubs.organization.training-groups.destroy');
        Route::get('/clubs/{club}/governance', [ClubGovernanceController::class, 'index'])->name('clubs.governance.index');
        Route::post('/clubs/{club}/governance/bodies', [ClubGovernanceController::class, 'storeBody'])->name('clubs.governance.bodies.store');
        Route::put('/clubs/{club}/governance/bodies/{governanceBody}', [ClubGovernanceController::class, 'updateBody'])->name('clubs.governance.bodies.update');
        Route::delete('/clubs/{club}/governance/bodies/{governanceBody}', [ClubGovernanceController::class, 'destroyBody'])->name('clubs.governance.bodies.destroy');
        Route::post('/clubs/{club}/governance/bodies/{governanceBody}/assignments', [ClubGovernanceController::class, 'storeAssignment'])->name('clubs.governance.assignments.store');
        Route::put('/clubs/{club}/governance/bodies/{governanceBody}/assignments/{assignment}', [ClubGovernanceController::class, 'updateAssignment'])->name('clubs.governance.assignments.update');
        Route::delete('/clubs/{club}/governance/bodies/{governanceBody}/assignments/{assignment}', [ClubGovernanceController::class, 'destroyAssignment'])->name('clubs.governance.assignments.destroy');
        Route::get('/clubs/{club}/governance/meetings', [ClubGovernanceMeetingController::class, 'index'])->name('clubs.governance.meetings.index');
        Route::post('/clubs/{club}/governance/meetings', [ClubGovernanceMeetingController::class, 'store'])->name('clubs.governance.meetings.store');
        Route::get('/clubs/{club}/governance/meetings/{meeting}', [ClubGovernanceMeetingController::class, 'show'])->name('clubs.governance.meetings.show');
        Route::put('/clubs/{club}/governance/meetings/{meeting}', [ClubGovernanceMeetingController::class, 'update'])->name('clubs.governance.meetings.update');
        Route::patch('/clubs/{club}/governance/meetings/{meeting}/recipients/{recipient}/delivery', [ClubGovernanceMeetingController::class, 'updateRecipientDelivery'])->name('clubs.governance.meetings.recipients.delivery');
        Route::post('/clubs/{club}/governance/meetings/{meeting}/decisions', [ClubGovernanceMeetingController::class, 'storeDecision'])->name('clubs.governance.meetings.decisions.store');
        Route::post('/clubs/{club}/governance/meetings/{meeting}/decisions/{decision}/vote', [ClubGovernanceMeetingController::class, 'castDecisionVote'])->name('clubs.governance.meetings.decisions.vote');
        Route::post('/clubs/{club}/governance/meetings/{meeting}/decisions/{decision}/close', [ClubGovernanceMeetingController::class, 'closeDecision'])->name('clubs.governance.meetings.decisions.close');
        Route::get('/clubs/{club}/policy-documents', [ClubPolicyDocumentController::class, 'index'])->name('clubs.policy-documents.index');
        Route::post('/clubs/{club}/policy-documents', [ClubPolicyDocumentController::class, 'store'])->name('clubs.policy-documents.store');
        Route::put('/clubs/{club}/policy-documents/{policyDocument}', [ClubPolicyDocumentController::class, 'update'])->name('clubs.policy-documents.update');
        Route::delete('/clubs/{club}/policy-documents/{policyDocument}', [ClubPolicyDocumentController::class, 'destroy'])->name('clubs.policy-documents.destroy');
        Route::get('/clubs/{club}/policy-documents/{policyDocument}/download', [ClubPolicyDocumentController::class, 'download'])->name('clubs.policy-documents.download');
        Route::get('/clubs/{club}/year-periods', [ClubYearPeriodController::class, 'index'])->name('clubs.year-periods.index');
        Route::get('/clubs/{club}/year-periods/report', [ClubYearPeriodController::class, 'report'])->name('clubs.year-periods.report');
        Route::post('/clubs/{club}/year-periods', [ClubYearPeriodController::class, 'store'])->name('clubs.year-periods.store');
        Route::put('/clubs/{club}/year-periods/{yearPeriod}', [ClubYearPeriodController::class, 'update'])->name('clubs.year-periods.update');
        Route::delete('/clubs/{club}/year-periods/{yearPeriod}', [ClubYearPeriodController::class, 'destroy'])->name('clubs.year-periods.destroy');
        Route::get('/clubs/{club}/metadata', [ClubMetadataController::class, 'index'])->name('clubs.metadata.index');
        Route::post('/clubs/{club}/metadata/custom-fields', [ClubMetadataController::class, 'storeCustomField'])->name('clubs.metadata.custom-fields.store');
        Route::put('/clubs/{club}/metadata/custom-fields/{customField}', [ClubMetadataController::class, 'updateCustomField'])->name('clubs.metadata.custom-fields.update');
        Route::delete('/clubs/{club}/metadata/custom-fields/{customField}', [ClubMetadataController::class, 'destroyCustomField'])->name('clubs.metadata.custom-fields.destroy');
        Route::post('/clubs/{club}/metadata/categories', [ClubMetadataController::class, 'storeCategory'])->name('clubs.metadata.categories.store');
        Route::put('/clubs/{club}/metadata/categories/{category}', [ClubMetadataController::class, 'updateCategory'])->name('clubs.metadata.categories.update');
        Route::delete('/clubs/{club}/metadata/categories/{category}', [ClubMetadataController::class, 'destroyCategory'])->name('clubs.metadata.categories.destroy');
        Route::post('/clubs/{club}/metadata/number-ranges', [ClubMetadataController::class, 'storeNumberRange'])->name('clubs.metadata.number-ranges.store');
        Route::put('/clubs/{club}/metadata/number-ranges/{numberRange}', [ClubMetadataController::class, 'updateNumberRange'])->name('clubs.metadata.number-ranges.update');
        Route::delete('/clubs/{club}/metadata/number-ranges/{numberRange}', [ClubMetadataController::class, 'destroyNumberRange'])->name('clubs.metadata.number-ranges.destroy');
        Route::post('/clubs/{club}/metadata/number-ranges/{numberRange}/allocate', [ClubMetadataController::class, 'allocateNumber'])->name('clubs.metadata.number-ranges.allocate');
        Route::put('/clubs/{club}/metadata/number-ranges/{numberRange}/default', [ClubMetadataController::class, 'setDefaultNumberRange'])->name('clubs.metadata.number-ranges.default.set');
        Route::delete('/clubs/{club}/metadata/number-ranges/{numberRange}/default', [ClubMetadataController::class, 'clearDefaultNumberRange'])->name('clubs.metadata.number-ranges.default.clear');
        Route::get('/clubs/{club}/metadata/subjects/{subjectType}/{subjectId}', [ClubMetadataController::class, 'showSubject'])->whereNumber('subjectId')->name('clubs.metadata.subjects.show');
        Route::put('/clubs/{club}/metadata/subjects/{subjectType}/{subjectId}', [ClubMetadataController::class, 'updateSubject'])->whereNumber('subjectId')->name('clubs.metadata.subjects.update');
        Route::get('/clubs/{club}/procurements', [ClubProcurementController::class, 'index'])->name('clubs.procurements.index');
        Route::post('/clubs/{club}/procurements', [ClubProcurementController::class, 'store'])->name('clubs.procurements.store');
        Route::put('/clubs/{club}/procurements/{procurement}/approval', [ClubProcurementController::class, 'approve'])->name('clubs.procurements.approval.update');
        Route::post('/clubs/{club}/procurements/{procurement}/order', [ClubProcurementController::class, 'order'])->name('clubs.procurements.order');
        Route::post('/clubs/{club}/procurements/{procurement}/receipts', [ClubProcurementController::class, 'receive'])->name('clubs.procurements.receipts.store');
        Route::get('/clubs/{club}/funding-programs', [ClubFundingProgramController::class, 'index'])->name('clubs.funding-programs.index');
        Route::post('/clubs/{club}/funding-programs', [ClubFundingProgramController::class, 'store'])->name('clubs.funding-programs.store');
        Route::put('/clubs/{club}/funding-programs/{fundingProgram}', [ClubFundingProgramController::class, 'update'])->name('clubs.funding-programs.update');
        Route::put('/clubs/{club}/funding-programs/{fundingProgram}/status', [ClubFundingProgramController::class, 'transition'])->name('clubs.funding-programs.status.update');
        Route::get('/clubs/{club}/members', [ClubController::class, 'members'])->name('clubs.members.index');
        Route::get('/clubs/{club}/announcements', [ClubAnnouncementController::class, 'index'])->name('clubs.announcements.index');
        Route::post('/clubs/{club}/announcements', [ClubAnnouncementController::class, 'store'])->name('clubs.announcements.store');
        Route::put('/clubs/{club}/announcements/{announcement}', [ClubAnnouncementController::class, 'update'])->name('clubs.announcements.update');
        Route::post('/clubs/{club}/announcements/{announcement}/publish', [ClubAnnouncementController::class, 'publish'])->name('clubs.announcements.publish');
        Route::post('/clubs/{club}/announcements/{announcement}/withdraw', [ClubAnnouncementController::class, 'withdraw'])->name('clubs.announcements.withdraw');
        Route::delete('/clubs/{club}/announcements/{announcement}', [ClubAnnouncementController::class, 'destroy'])->name('clubs.announcements.destroy');
        Route::post('/clubs/{club}/announcements/{announcement}/read', [ClubAnnouncementController::class, 'acknowledge'])->name('clubs.announcements.read');
        Route::post('/clubs/{club}/newsletter/subscribe', [ClubNewsletterController::class, 'subscribe'])->name('clubs.newsletter.subscribe');
        Route::post('/clubs/{club}/newsletter/send', [ClubNewsletterController::class, 'send'])->name('clubs.newsletter.send');
        Route::post('/clubs/{club}/newsletter/suppressions', [ClubNewsletterController::class, 'suppress'])->name('clubs.newsletter.suppressions.store');
        Route::post('/clubs/{club}/newsletter/deliveries/{delivery}/bounce', [ClubNewsletterController::class, 'bounce'])->name('clubs.newsletter.deliveries.bounce');
        Route::get('/clubs/{club}/surveys', [ClubSurveyController::class, 'index'])->name('clubs.surveys.index');
        Route::get('/clubs/{club}/tasks', [ClubTaskController::class, 'index'])->name('clubs.tasks.index');
        Route::post('/clubs/{club}/tasks', [ClubTaskController::class, 'store'])->name('clubs.tasks.store');
        Route::put('/clubs/{club}/tasks/{task}', [ClubTaskController::class, 'update'])->name('clubs.tasks.update');
        Route::post('/clubs/{club}/tasks/{task}/comments', [ClubTaskController::class, 'comment'])->name('clubs.tasks.comments.store');
        Route::post('/clubs/{club}/tasks/{task}/attachments', [ClubTaskController::class, 'attach'])->name('clubs.tasks.attachments.store');
        Route::delete('/clubs/{club}/tasks/{task}/attachments/{file}', [ClubTaskController::class, 'detach'])->name('clubs.tasks.attachments.destroy');
        Route::delete('/clubs/{club}/tasks/{task}', [ClubTaskController::class, 'destroy'])->name('clubs.tasks.destroy');
        Route::post('/clubs/{club}/surveys', [ClubSurveyController::class, 'store'])->name('clubs.surveys.store');
        Route::put('/clubs/{club}/surveys/{survey}', [ClubSurveyController::class, 'update'])->name('clubs.surveys.update');
        Route::delete('/clubs/{club}/surveys/{survey}', [ClubSurveyController::class, 'destroy'])->name('clubs.surveys.destroy');
        Route::post('/clubs/{club}/surveys/{survey}/vote', [ClubSurveyController::class, 'vote'])->name('clubs.surveys.vote');
        Route::post('/clubs/{club}/surveys/{survey}/close', [ClubSurveyController::class, 'close'])->name('clubs.surveys.close');
        Route::get('/clubs/{club}/inventory', [ClubInventoryController::class, 'index'])->name('clubs.inventory.index');
        Route::post('/clubs/{club}/inventory', [ClubInventoryController::class, 'store'])->name('clubs.inventory.store');
        Route::put('/clubs/{club}/inventory/{item}', [ClubInventoryController::class, 'update'])->name('clubs.inventory.update');
        Route::delete('/clubs/{club}/inventory/{item}', [ClubInventoryController::class, 'destroy'])->name('clubs.inventory.destroy');
        Route::post('/clubs/{club}/inventory/scan', [ClubInventoryController::class, 'scan'])->name('clubs.inventory.scan');
        Route::post('/clubs/{club}/inventory/{item}/qr/reissue', [ClubInventoryController::class, 'reissueQr'])->name('clubs.inventory.qr.reissue');
        Route::post('/clubs/{club}/inventory/{item}/qr/revoke', [ClubInventoryController::class, 'revokeQr'])->name('clubs.inventory.qr.revoke');
        Route::post('/clubs/{club}/inventory/{item}/movements', [ClubInventoryController::class, 'recordMovement'])->name('clubs.inventory.movements.store');
        Route::post('/clubs/{club}/inventory/{item}/checkout', [ClubInventoryController::class, 'checkout'])->name('clubs.inventory.checkout');
        Route::post('/clubs/{club}/inventory/loans/{loan}/approve', [ClubInventoryController::class, 'approve'])->name('clubs.inventory.loans.approve');
        Route::post('/clubs/{club}/inventory/loans/{loan}/reject', [ClubInventoryController::class, 'reject'])->name('clubs.inventory.loans.reject');
        Route::post('/clubs/{club}/inventory/loans/{loan}/return', [ClubInventoryController::class, 'returnLoan'])->name('clubs.inventory.loans.return');
        Route::post('/clubs/{club}/inventory/{item}/damage-reports', [ClubInventoryController::class, 'reportDamage'])->name('clubs.inventory.damage-reports.store');
        Route::post('/clubs/{club}/inventory/{item}/maintenance', [ClubInventoryController::class, 'storeMaintenance'])->name('clubs.inventory.maintenance.store');
        Route::put('/clubs/{club}/inventory/maintenance/{maintenance}', [ClubInventoryController::class, 'updateMaintenance'])->name('clubs.inventory.maintenance.update');
        Route::get('/commerce/team-bulk-orders', [TeamBulkOrderController::class, 'index'])->name('commerce.team-bulk-orders.index');
        Route::post('/commerce/team-bulk-orders', [TeamBulkOrderController::class, 'store'])->name('commerce.team-bulk-orders.store');
        Route::get('/commerce/team-bulk-orders/{bulkOrder}', [TeamBulkOrderController::class, 'show'])->name('commerce.team-bulk-orders.show');
        Route::post('/commerce/team-bulk-orders/{bulkOrder}/items', [TeamBulkOrderController::class, 'order'])->name('commerce.team-bulk-orders.items.store');
        Route::get('/commerce/team-bulk-orders/{bulkOrder}/supplier-export', [TeamBulkOrderController::class, 'supplierExport'])->name('commerce.team-bulk-orders.supplier-export');
        Route::post('/clubs/{club}/members/invite', [ClubController::class, 'inviteMember'])->name('clubs.members.invite');
        Route::post('/clubs/{club}/members/bulk', [ClubController::class, 'storeExternalMembers'])->name('clubs.members.bulk.store');
        Route::post('/clubs/{club}/members/import', [ClubController::class, 'importMembers'])->name('clubs.members.import');
        Route::post('/clubs/{club}/members/import-preview', [ClubController::class, 'previewMemberImport'])->name('clubs.members.import-preview');
        Route::get('/club-members/import-template', [ClubController::class, 'downloadMemberImportTemplate'])->name('club-members.import-template');
        Route::put('/clubs/{club}/members/{user}', [ClubController::class, 'updateMemberDetails'])->name('clubs.members.update');
        Route::get('/clubs/{club}/members/{user}/permissions', [ClubController::class, 'memberPermissions'])->name('clubs.members.permissions.show');
        Route::put('/clubs/{club}/members/{user}/permissions', [ClubController::class, 'updateMemberPermissions'])->name('clubs.members.permissions.update');
        Route::get('/clubs/{club}/service-hours', [ClubServiceHourController::class, 'index'])->name('clubs.service-hours.index');
        Route::post('/clubs/{club}/service-hour-requirements', [ClubServiceHourController::class, 'storeRequirement'])->name('clubs.service-hour-requirements.store');
        Route::post('/clubs/{club}/service-hour-requirements/{requirement}/lock', [ClubServiceHourController::class, 'lockRequirement'])->name('clubs.service-hour-requirements.lock');
        Route::post('/clubs/{club}/service-hour-records', [ClubServiceHourController::class, 'storeRecord'])->name('clubs.service-hour-records.store');
        Route::post('/clubs/{club}/service-hour-records/{record}/confirm', [ClubServiceHourController::class, 'confirmRecord'])->name('clubs.service-hour-records.confirm');
        Route::post('/clubs/{club}/service-hour-records/{record}/corrections', [ClubServiceHourController::class, 'correctRecord'])->name('clubs.service-hour-records.corrections.store');
        Route::post('/clubs/{club}/service-hour-exemptions', [ClubServiceHourController::class, 'storeExemption'])->name('clubs.service-hour-exemptions.store');
        Route::get('/clubs/{club}/permission-delegations', [ClubPermissionDelegationController::class, 'index'])->name('clubs.permission-delegations.index');
        Route::post('/clubs/{club}/permission-delegations', [ClubPermissionDelegationController::class, 'store'])->name('clubs.permission-delegations.store');
        Route::post('/clubs/{club}/permission-delegations/{delegation}/revoke', [ClubPermissionDelegationController::class, 'revoke'])->name('clubs.permission-delegations.revoke');
        Route::get('/clubs/{club}/role-definitions', [ClubRoleDefinitionController::class, 'index'])->name('clubs.role-definitions.index');
        Route::post('/clubs/{club}/role-definitions', [ClubRoleDefinitionController::class, 'store'])->name('clubs.role-definitions.store');
        Route::put('/clubs/{club}/role-definitions/{roleDefinition}', [ClubRoleDefinitionController::class, 'update'])->name('clubs.role-definitions.update');
        Route::delete('/clubs/{club}/role-definitions/{roleDefinition}', [ClubRoleDefinitionController::class, 'destroy'])->name('clubs.role-definitions.destroy');
        Route::get('/clubs/{club}/members/{user}/role-definitions', [ClubRoleDefinitionController::class, 'memberAssignments'])->name('clubs.members.role-definitions.show');
        Route::put('/clubs/{club}/members/{user}/role-definitions', [ClubRoleDefinitionController::class, 'updateMemberAssignments'])->name('clubs.members.role-definitions.update');
        Route::get('/clubs/{club}/access-handover-reviews', [ClubAccessHandoverController::class, 'index'])->name('clubs.access-handover-reviews.index');
        Route::post('/clubs/{club}/access-handover-reviews/{review}/propose', [ClubAccessHandoverController::class, 'propose'])->name('clubs.access-handover-reviews.propose');
        Route::post('/clubs/{club}/access-handover-reviews/{review}/approve', [ClubAccessHandoverController::class, 'approve'])->name('clubs.access-handover-reviews.approve');
        Route::get('/clubs/{club}/master-data-change-requests', [ClubMasterDataChangeRequestController::class, 'index'])->name('clubs.master-data-change-requests.index');
        Route::post('/clubs/{club}/master-data-change-requests', [ClubMasterDataChangeRequestController::class, 'store'])->name('clubs.master-data-change-requests.store');
        Route::post('/clubs/{club}/master-data-change-requests/{changeRequest}/approve', [ClubMasterDataChangeRequestController::class, 'approve'])->name('clubs.master-data-change-requests.approve');
        Route::post('/clubs/{club}/master-data-change-requests/{changeRequest}/reject', [ClubMasterDataChangeRequestController::class, 'reject'])->name('clubs.master-data-change-requests.reject');
        Route::get('/clubs/{club}/member-qualifications', [ClubMemberQualificationController::class, 'index'])->name('clubs.member-qualifications.index');
        Route::post('/clubs/{club}/member-qualifications', [ClubMemberQualificationController::class, 'store'])->name('clubs.member-qualifications.store');
        Route::put('/clubs/{club}/member-qualifications/{qualification}', [ClubMemberQualificationController::class, 'update'])->name('clubs.member-qualifications.update');
        Route::delete('/clubs/{club}/member-qualifications/{qualification}', [ClubMemberQualificationController::class, 'destroy'])->name('clubs.member-qualifications.destroy');
        Route::get('/clubs/{club}/volunteer-profile', [ClubVolunteerProfileController::class, 'showMine'])->name('clubs.volunteer-profile.show');
        Route::put('/clubs/{club}/volunteer-profile', [ClubVolunteerProfileController::class, 'updateMine'])->name('clubs.volunteer-profile.update');
        Route::get('/clubs/{club}/members/{user}/volunteer-profile', [ClubVolunteerProfileController::class, 'showMember'])->name('clubs.members.volunteer-profile.show');
        Route::delete('/clubs/{club}/members/{user}', [ClubController::class, 'removeClubMember'])->name('clubs.members.destroy');
        Route::get('/clubs/{club}/members/{user}/relationships', [ClubMemberRelationshipController::class, 'index'])->name('clubs.members.relationships.index');
        Route::post('/clubs/{club}/members/{user}/relationships', [ClubMemberRelationshipController::class, 'store'])->name('clubs.members.relationships.store');
        Route::get('/clubs/{club}/members/{child}/guardians', [ClubGuardianRelationshipController::class, 'index'])->name('clubs.members.guardians.index');
        Route::post('/clubs/{club}/members/{child}/guardians', [ClubGuardianRelationshipController::class, 'store'])->name('clubs.members.guardians.store');
        Route::post('/clubs/{club}/members/{child}/guardians/{relationship}/accept', [ClubGuardianRelationshipController::class, 'accept'])->name('clubs.members.guardians.accept');
        Route::post('/clubs/{club}/members/{child}/guardians/{relationship}/decline', [ClubGuardianRelationshipController::class, 'decline'])->name('clubs.members.guardians.decline');
        Route::post('/clubs/{club}/members/{child}/guardians/{relationship}/revoke', [ClubGuardianRelationshipController::class, 'revoke'])->name('clubs.members.guardians.revoke');
        Route::post('/clubs/{club}/members/{child}/guardians/{relationship}/primary', [ClubGuardianRelationshipController::class, 'primary'])->name('clubs.members.guardians.primary');
        Route::post('/clubs/{club}/members/{user}/member-number', [ClubController::class, 'generateMemberNumber'])->name('clubs.members.member-number.store');
        Route::post('/clubs/{club}/members/{user}/invoices', [ClubController::class, 'createMemberInvoice'])->name('clubs.members.invoices.store');
        Route::post('/clubs/{club}/external-members/{externalMember}/invoices', [ClubController::class, 'createExternalMemberInvoice'])->name('clubs.external-members.invoices.store');
        Route::post('/clubs/{club}/membership-invoice-runs/preview', [ClubController::class, 'previewContributionInvoiceRun'])->name('clubs.membership-invoice-runs.preview');
        Route::post('/clubs/{club}/membership-invoice-runs', [ClubController::class, 'createContributionInvoiceRun'])->name('clubs.membership-invoice-runs.store');
        Route::put('/clubs/{club}/members/{user}/role', [ClubController::class, 'updateMemberRole'])->name('clubs.members.role.update');
        Route::prefix('/external')->name('external.')->group(function () {
            Route::get('/clubs/{club}/members', [ExternalClubMemberController::class, 'index'])->name('clubs.members.index');
            Route::post('/clubs/{club}/members', [ExternalClubMemberController::class, 'store'])->name('clubs.members.store');
            Route::get('/clubs/{club}/members/{externalMember}', [ExternalClubMemberController::class, 'show'])->name('clubs.members.show');
            Route::put('/clubs/{club}/members/{externalMember}', [ExternalClubMemberController::class, 'update'])->name('clubs.members.update');
        });
        Route::post('/clubs/{club}/external-members/{externalMember}/invite', [ClubController::class, 'inviteExternalMember'])->name('clubs.external-members.invite');
        Route::post('/clubs/{club}/external-members/{externalMember}/merge/{user}', [ClubController::class, 'mergeExternalMember'])->name('clubs.external-members.merge');
        Route::post('/clubs/{club}/member-timeline', [ClubMemberTimelineController::class, 'store'])->name('clubs.member-timeline.store');
        Route::delete('/clubs/{club}/member-timeline/{timelineEntry}', [ClubMemberTimelineController::class, 'destroy'])->name('clubs.member-timeline.destroy');
        Route::put('/clubs/{club}/external-members/{externalMember}', [ClubController::class, 'updateExternalMember'])->name('clubs.external-members.update');
        Route::delete('/clubs/{club}/external-members/{externalMember}', [ClubController::class, 'removeExternalMember'])->name('clubs.external-members.destroy');
        Route::get('/club-external-invitations/{token}', [ClubController::class, 'externalInvitationByToken'])->name('club-external-invitations.show');
        Route::get('/clubs/{club}/staff-scheduling', [ClubStaffSchedulingController::class, 'index'])->name('clubs.staff-scheduling.index');
        Route::post('/clubs/{club}/staff-scheduling/availabilities', [ClubStaffSchedulingController::class, 'storeAvailability'])->name('clubs.staff-scheduling.availabilities.store');
        Route::post('/clubs/{club}/staff-scheduling/assignments', [ClubStaffSchedulingController::class, 'storeAssignment'])->name('clubs.staff-scheduling.assignments.store');
        Route::post('/clubs/{club}/staff-scheduling/assignments/{assignment}/signup', [ClubStaffSchedulingController::class, 'signupAssignment'])->name('clubs.staff-scheduling.assignments.signup');
        Route::post('/clubs/{club}/staff-scheduling/assignments/{assignment}/waitlist', [ClubStaffSchedulingController::class, 'waitlistAssignment'])->name('clubs.staff-scheduling.assignments.waitlist');
        Route::post('/clubs/{club}/staff-scheduling/assignments/{assignment}/release', [ClubStaffSchedulingController::class, 'releaseAssignment'])->name('clubs.staff-scheduling.assignments.release');
        Route::post('/clubs/{club}/staff-scheduling/assignments/{assignment}/swap', [ClubStaffSchedulingController::class, 'swapAssignment'])->name('clubs.staff-scheduling.assignments.swap');
        Route::post('/clubs/{club}/staff-scheduling/assignments/{assignment}/substitute', [ClubStaffSchedulingController::class, 'substituteAssignment'])->name('clubs.staff-scheduling.assignments.substitute');
        Route::post('/clubs/{club}/staff-scheduling/conflicts', [ClubStaffSchedulingController::class, 'conflicts'])->name('clubs.staff-scheduling.conflicts');
        Route::post('/club-external-invitations/{token}/accept', [ClubController::class, 'acceptExternalInvitation'])->name('club-external-invitations.accept');
        Route::post('/club-external-invitations/{token}/decline', [ClubController::class, 'declineExternalInvitation'])->name('club-external-invitations.decline');
        Route::post('/clubs/{club}/leave', [ClubController::class, 'leaveClub'])->name('clubs.leave');
        Route::post('/clubs/{club}/removal-objections', [ClubController::class, 'objectToRemoval'])->name('clubs.removal-objections.store');
        Route::post('/clubs/{club}/pause-requests', [ClubController::class, 'storePauseRequest'])->name('clubs.pause-requests.store');
        Route::post('/clubs/{club}/membership-change-requests', [ClubController::class, 'requestMembershipChange'])->name('clubs.membership-change-requests.store');
        Route::post('/clubs/{club}/termination-requests', [ClubController::class, 'requestMembershipTermination'])->name('clubs.termination-requests.store');
        Route::get('/clubs/{club}/member-card', [ClubMemberCardController::class, 'show'])->name('clubs.member-card.show');
        Route::post('/clubs/{club}/member-card/rotate', [ClubMemberCardController::class, 'rotate'])->name('clubs.member-card.rotate');
        Route::put('/clubs/{club}/member-card/design', [ClubMemberCardController::class, 'updateDesign'])->name('clubs.member-card.design.update');
        Route::post('/clubs/{club}/member-card/verify', [ClubMemberCardController::class, 'verify'])->middleware('throttle:content-comments')->name('clubs.member-card.verify');
        Route::put('/clubs/{club}/membership/settings', [ClubController::class, 'updateMembershipSettings'])->name('clubs.membership.settings.update');
        Route::get('/clubs/{club}/sepa-batches', [ClubSepaBatchController::class, 'index'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.index');
        Route::post('/clubs/{club}/sepa-batches', [ClubSepaBatchController::class, 'store'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.store');
        Route::post('/clubs/{club}/sepa-batches/{batch}/approve', [ClubSepaBatchController::class, 'approve'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.approve');
        Route::post('/clubs/{club}/sepa-batches/{batch}/notice', [ClubSepaBatchController::class, 'notice'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.notice');
        Route::post('/clubs/{club}/sepa-batches/{batch}/notices', [ClubSepaBatchController::class, 'prepareNotices'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.notices.prepare');
        Route::post('/clubs/{club}/sepa-batches/{batch}/notices/send', [ClubSepaBatchController::class, 'sendNotices'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.notices.send');
        Route::post('/clubs/{club}/sepa-batches/{batch}/cancel', [ClubSepaBatchController::class, 'cancel'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.cancel');
        Route::post('/clubs/{club}/sepa-batches/{batch}/export', [ClubSepaBatchController::class, 'export'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.export');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/settle', [ClubSepaBatchController::class, 'settleItem'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.settle');
        Route::post('/clubs/{club}/sepa-batches/{batch}/returns/columns', [ClubSepaBatchController::class, 'returnColumns'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.returns.columns');
        Route::post('/clubs/{club}/sepa-batches/{batch}/returns/preview', [ClubSepaBatchController::class, 'previewReturns'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.returns.preview');
        Route::post('/clubs/{club}/sepa-batches/{batch}/returns/import', [ClubSepaBatchController::class, 'importReturns'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.returns.import');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/return', [ClubSepaBatchController::class, 'returnItem'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.return');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee', [ClubSepaBatchController::class, 'recordFee'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges', [ClubSepaBatchController::class, 'proposeFeeRecharge'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/approve', [ClubSepaBatchController::class, 'approveFeeRecharge'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges.approve');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/void-requests', [ClubSepaBatchController::class, 'requestFeeRechargeVoid'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges.void.request');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/void-requests/{voidRequest}/approve', [ClubSepaBatchController::class, 'approveFeeRechargeVoid'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges.void.approve');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/void-requests/{voidRequest}/withdraw', [ClubSepaBatchController::class, 'withdrawFeeRechargeVoid'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges.void.withdraw');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests', [ClubSepaBatchController::class, 'requestFeeRechargeCredit'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges.credit.request');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/approve', [ClubSepaBatchController::class, 'approveFeeRechargeCredit'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges.credit.approve');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/withdraw', [ClubSepaBatchController::class, 'withdrawFeeRechargeCredit'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges.credit.withdraw');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/refund', [ClubSepaBatchController::class, 'recordFeeRechargeRefund'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges.credit.refund');
        Route::get('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/document', [ClubSepaBatchController::class, 'downloadFeeRechargeCredit'])->name('clubs.sepa-batches.items.fee-recharges.credit.document');
        Route::get('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/document-link', [ClubSepaBatchController::class, 'feeRechargeCreditDocumentLink'])->name('clubs.sepa-batches.items.fee-recharges.credit.document-link');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/cancel', [ClubSepaBatchController::class, 'cancelFeeRecharge'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-recharges.cancel');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-corrections', [ClubSepaBatchController::class, 'correctFee'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.fee-corrections');
        Route::get('/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-options', [ClubSepaBatchController::class, 'feeOptions'])->name('clubs.sepa-batches.items.fee-options');
        Route::post('/clubs/{club}/sepa-batches/{batch}/items/{item}/retry', [ClubSepaBatchController::class, 'authorizeItemRetry'])->middleware('throttle:payment-actions')->name('clubs.sepa-batches.items.retry');
        Route::put('/clubs/{club}/membership/sepa-settings', [ClubController::class, 'updateSepaSettingsApi'])->name('clubs.membership.sepa-settings.update');
        Route::get('/clubs/{club}/membership/sepa-export', [ClubController::class, 'exportSepaDebit'])->name('clubs.membership.sepa-export');
        Route::put('/clubs/{club}/membership/datev-settings', [ClubController::class, 'updateDatevSettingsApi'])->name('clubs.membership.datev-settings.update');
        Route::get('/clubs/{club}/membership/datev-export', [ClubController::class, 'exportDatev'])->name('clubs.membership.datev-export');
        Route::post('/clubs/{club}/receipt-uploads', [ClubController::class, 'uploadReceipt'])->name('clubs.receipt-uploads.store');
        Route::post('/clubs/{club}/receipt-uploads/{receiptUpload}/confirm', [ClubController::class, 'confirmReceipt'])->name('clubs.receipt-uploads.confirm');
        Route::post('/clubs/{club}/membership/types', [ClubController::class, 'storeMembershipType'])->name('clubs.membership.types.store');
        Route::put('/clubs/{club}/membership/types/{membershipType}', [ClubController::class, 'updateMembershipType'])->name('clubs.membership.types.update');
        Route::post('/clubs/{club}/membership/contribution-rules', [ClubController::class, 'storeContributionRule'])->name('clubs.membership.contribution-rules.store');
        Route::put('/clubs/{club}/membership/contribution-rules/{contributionRule}', [ClubController::class, 'updateContributionRule'])->name('clubs.membership.contribution-rules.update');
        Route::post('/clubs/{club}/membership-invoices/{invoice}/payments', [ClubController::class, 'recordMembershipPayment'])->middleware('throttle:payment-actions')->name('clubs.membership-invoices.payments.store');
        Route::put('/clubs/{club}/membership-invoices/{invoice}/status', [ClubController::class, 'updateMembershipInvoiceStatus'])->name('clubs.membership-invoices.status.update');
        Route::post('/clubs/{club}/membership-invoices/{invoice}/replacement', [ClubController::class, 'replaceCancelledMembershipInvoice'])->middleware('throttle:payment-actions')->name('clubs.membership-invoices.replacement.store');
        Route::post('/clubs/{club}/membership-invoices/{invoice}/reminder', [ClubController::class, 'sendMembershipInvoiceReminder'])->name('clubs.membership-invoices.reminder.store');
        Route::post('/clubs/{club}/membership-invoices/{invoice}/download-authorizations', [ClubController::class, 'membershipInvoiceDownloadAuthorization'])->name('clubs.membership-invoices.download-authorizations.store');
        Route::get('/clubs/{club}/membership-invoices/{invoice}/download', [ClubController::class, 'downloadMembershipInvoice'])->name('clubs.membership-invoices.download');
        Route::get('/clubs/{club}/work-automation-jobs', [WorkAutomationJobController::class, 'index'])->name('clubs.work-automation-jobs.index');
        Route::post('/clubs/{club}/work-automation-jobs', [WorkAutomationJobController::class, 'store'])->name('clubs.work-automation-jobs.store');
        Route::post('/clubs/{club}/work-automation-jobs/{workAutomationJob}/retry', [WorkAutomationJobController::class, 'retry'])->name('clubs.work-automation-jobs.retry');
        Route::post('/clubs/{club}/donations', [ClubController::class, 'recordDonation'])->middleware('throttle:payment-actions')->name('clubs.donations.store');
        Route::post('/clubs/{club}/prepayments', [ClubController::class, 'recordPrepayment'])->middleware('throttle:payment-actions')->name('clubs.prepayments.store');
        Route::put('/clubs/{club}/payments/{payment}', [ClubController::class, 'updatePayment'])->middleware('throttle:payment-actions')->name('clubs.payments.update');
        Route::post('/clubs/{club}/finance-entries', [ClubController::class, 'storeFinanceEntry'])->name('clubs.finance-entries.store');
        Route::put('/clubs/{club}/finance-entries/{financeEntry}', [ClubController::class, 'updateFinanceEntry'])->name('clubs.finance-entries.update');
        Route::post('/clubs/{club}/bank-transactions/preview', [ClubController::class, 'previewBankTransactions'])->name('clubs.bank-transactions.preview');
        Route::post('/clubs/{club}/bank-transactions/import', [ClubController::class, 'importBankTransactions'])->name('clubs.bank-transactions.import');
        Route::post('/clubs/{club}/bank-transactions/{bankTransaction}/confirm', [ClubController::class, 'confirmBankTransaction'])->name('clubs.bank-transactions.confirm');
        Route::get('/clubs/{club}/billing', [ClubController::class, 'billing'])->name('clubs.billing');
        Route::get('/clubs/{club}/membership-requests', [ClubController::class, 'membershipRequests'])->name('clubs.membership-requests.index');
        Route::get('/clubs/{club}/membership-prospects', [ClubMembershipProspectController::class, 'index'])->name('clubs.membership-prospects.index');
        Route::post('/clubs/{club}/membership-prospects', [ClubMembershipProspectController::class, 'store'])->name('clubs.membership-prospects.store');
        Route::put('/clubs/{club}/membership-prospects/{prospect}', [ClubMembershipProspectController::class, 'update'])->name('clubs.membership-prospects.update');
        Route::post('/clubs/{club}/membership-prospects/{prospect}/archive', [ClubMembershipProspectController::class, 'archive'])->name('clubs.membership-prospects.archive');
        Route::post('/clubs/{club}/membership-requests', [ClubController::class, 'storeMembershipRequest'])->name('clubs.membership-requests.store');
        Route::delete('/clubs/{club}/membership-requests', [ClubController::class, 'withdrawMembershipRequest'])->name('clubs.membership-requests.withdraw');
        Route::post('/clubs/{club}/membership-requests/{membershipRequest}/approve', [ClubController::class, 'approveMembershipRequest'])->name('clubs.membership-requests.approve');
        Route::post('/clubs/{club}/membership-requests/{membershipRequest}/decline', [ClubController::class, 'declineMembershipRequest'])->name('clubs.membership-requests.decline');
        Route::post('/clubs/{club}/membership-requests/{membershipRequest}/request-information', [ClubController::class, 'requestMembershipInformation'])->name('clubs.membership-requests.request-information');
        Route::post('/clubs/{club}/membership-requests/{membershipRequest}/respond', [ClubController::class, 'respondToMembershipInformation'])->name('clubs.membership-requests.respond');
        Route::post('/clubs/{club}/membership-requests/{membershipRequest}/waitlist', [ClubController::class, 'waitlistMembershipRequest'])->name('clubs.membership-requests.waitlist');
        Route::post('/membership-applications', function (Request $request) {
            $data = $request->validate([
                'club_id' => ['required', 'exists:clubs,id'],
                'type' => ['nullable', 'in:membership,pause'],
            ]);

            $request->merge(['type' => $data['type'] ?? 'membership']);

            return app(ClubController::class)->storeMembershipRequest($request, Club::findOrFail($data['club_id']));
        })->name('membership-applications.store');
        Route::get('/membership-applications/{membershipRequest}', function (Request $request, ClubMembershipRequest $membershipRequest) {
            $membershipRequest->loadMissing(['club', 'user', 'membershipType']);
            abort_unless((int) $membershipRequest->user_id === (int) $request->user()->id || Gate::allows('update', $membershipRequest->club), 403);

            return new ClubMembershipRequestResource($membershipRequest);
        })->name('membership-applications.show');
        Route::post('/membership-applications/{membershipRequest}/withdraw', function (Request $request, ClubMembershipRequest $membershipRequest) {
            $membershipRequest->loadMissing(['club', 'user', 'membershipType']);
            abort_unless((int) $membershipRequest->user_id === (int) $request->user()->id, 403);
            abort_unless($membershipRequest->type === 'membership', 422, 'Nur Mitgliedschaftsanfragen können hier zurückgezogen werden.');
            abort_unless($membershipRequest->status === 'pending', 422, 'Diese Anfrage ist nicht mehr offen.');

            $membershipRequest->update([
                'status' => 'withdrawn',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return new ClubMembershipRequestResource($membershipRequest->fresh()->load(['club', 'user', 'membershipType']));
        })->name('membership-applications.withdraw');
        Route::post('/clubs/{club}/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancelClubSubscription'])->middleware('throttle:payment-actions')->name('clubs.subscriptions.cancel');
        Route::post('/clubs/{club}/subscriptions/{subscription}/renew', [SubscriptionController::class, 'renewClubSubscription'])->middleware('throttle:payment-actions')->name('clubs.subscriptions.renew');

        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('/teams/{team}/daily-life', function (Request $request, Team $team, TeamDailyLifeService $dailyLife) {
            Gate::authorize('view', $team);

            return response()->json([
                'data' => $dailyLife->forTeam($team, $request->user()),
            ]);
        })->name('teams.daily-life');
        Route::get('/teams/{team}/competitiveness/insights', [TeamCompetitivenessController::class, 'insights'])->name('teams.competitiveness.insights');
        Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
        Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('/teams/{team}/invite', [TeamController::class, 'invite'])->name('teams.invite');
        Route::post('/teams/{team}/join-requests', [TeamController::class, 'requestJoin'])->name('teams.join-requests.store');
        Route::post('/teams/{team}/transfer-requests', [TeamController::class, 'requestTransfer'])->name('teams.transfer-requests.store');
        Route::post('/teams/{team}/transfers', [TeamController::class, 'storeTransfer'])->name('teams.transfers.store');
        Route::post('/team-join-requests/{joinRequest}/approve', [TeamController::class, 'approveJoinRequestById'])->name('team-join-requests.approve');
        Route::post('/team-join-requests/{joinRequest}/decline', [TeamController::class, 'declineJoinRequestById'])->name('team-join-requests.decline');
        Route::post('/team-transfer-requests/{transferRequest}/approve', [TeamController::class, 'approveTransferRequest'])->name('team-transfer-requests.approve');
        Route::post('/team-transfer-requests/{transferRequest}/decline', [TeamController::class, 'declineTransferRequest'])->name('team-transfer-requests.decline');
        Route::get('/team-invitations', [TeamController::class, 'invitations'])->name('team-invitations.index');
        Route::get('/team-invitations/token/{token}', [TeamController::class, 'invitationByToken'])->name('team-invitations.token.show');
        Route::post('/team-invitations/token/{token}/accept', [TeamController::class, 'acceptInvitationByToken'])->name('team-invitations.token.accept');
        Route::post('/team-invitations/token/{token}/decline', [TeamController::class, 'declineInvitationByToken'])->name('team-invitations.token.decline');
        Route::get('/team-invitations/{invitation}', [TeamController::class, 'invitation'])->name('team-invitations.show');
        Route::post('/team-invitations/{invitation}/accept', [TeamController::class, 'acceptInvitation'])->name('team-invitations.accept');
        Route::post('/team-invitations/{invitation}/decline', [TeamController::class, 'declineInvitation'])->name('team-invitations.decline');
        Route::post('/teams/{team}/join-requests/{joinRequest}/approve', [TeamController::class, 'approveJoinRequest'])->name('teams.join-requests.approve');
        Route::post('/teams/{team}/join-requests/{joinRequest}/decline', [TeamController::class, 'declineJoinRequest'])->name('teams.join-requests.decline');
        Route::post('/teams/{team}/members', [TeamController::class, 'storeMember'])->name('teams.members.store');
        Route::put('/teams/{team}/members/{user}', [TeamController::class, 'updateMember'])->name('teams.members.update');
        Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.destroy');
        Route::get('/teams/{team}/attendance-stats', [TeamController::class, 'attendanceStats'])->name('teams.attendance-stats');
        Route::get('/teams/{team}/penalties', [TeamPenaltyController::class, 'index'])->name('teams.penalties.index');
        Route::post('/teams/{team}/penalty-rules', [TeamPenaltyController::class, 'storeRule'])->name('teams.penalty-rules.store');
        Route::put('/teams/{team}/penalty-rules/{penaltyRule}', [TeamPenaltyController::class, 'updateRule'])->name('teams.penalty-rules.update');
        Route::delete('/teams/{team}/penalty-rules/{penaltyRule}', [TeamPenaltyController::class, 'destroyRule'])->name('teams.penalty-rules.destroy');
        Route::post('/teams/{team}/penalty-fees', [TeamPenaltyController::class, 'storeFee'])->name('teams.penalty-fees.store');
        Route::post('/teams/{team}/penalty-fees/{fee}/paid', [TeamPenaltyController::class, 'markFeePaid'])->name('teams.penalty-fees.paid');
        Route::post('/teams/{team}/penalty-fees/{fee}/cancel', [TeamPenaltyController::class, 'cancelFee'])->name('teams.penalty-fees.cancel');

        Route::get('/uploads', [UploadController::class, 'index'])->name('uploads.index');
        Route::post('/uploads', [UploadController::class, 'store'])->middleware('throttle:file-uploads')->name('uploads.store');
        Route::post('/uploads/{file}/share', [UploadController::class, 'share'])->middleware('throttle:file-share')->name('uploads.share');
        Route::post('/uploads/{file}/public-share', [UploadController::class, 'publicShare'])->middleware('throttle:file-share')->name('uploads.public-share');
        Route::delete('/uploads/{file}/public-share', [UploadController::class, 'revokePublicShares'])->middleware('throttle:file-share')->name('uploads.public-share.destroy');
        Route::patch('/uploads/{file}', [UploadController::class, 'update'])->middleware('throttle:file-update')->name('uploads.update');
        Route::delete('/uploads/{file}', [UploadController::class, 'destroy'])->middleware('throttle:file-delete')->name('uploads.destroy');
        Route::get('/files', [UploadController::class, 'workspace'])->name('files.workspace');
        Route::get('/files/{file}/preview', [UploadController::class, 'preview'])->middleware('throttle:file-preview')->name('files.preview');
        Route::post('/files/upload-intents', [UploadController::class, 'uploadIntent'])->middleware('throttle:file-uploads')->name('files.upload-intents.store');
        Route::post('/files/folders', [UploadController::class, 'storeFolder'])->middleware('throttle:file-folder-create')->name('files.folders.store');
        Route::post('/files/folders/{folder}/share', [FolderController::class, 'shareApi'])->middleware('throttle:file-share')->name('files.folders.share');
        Route::patch('/files/folders/{folder}', [UploadController::class, 'updateFolder'])->middleware('throttle:file-folder-update')->name('files.folders.update');
        Route::delete('/files/folders/{folder}', [UploadController::class, 'destroyFolder'])->middleware('throttle:file-folder-delete')->name('files.folders.destroy');

        Route::get('/chat/conversations', [ChatController::class, 'index'])->name('chat.conversations.index');
        Route::get('/chat/conversation-invitations', [ChatController::class, 'invitations'])->name('chat.conversation-invitations.index');
        Route::post('/chat/conversations', [ChatController::class, 'store'])->middleware('throttle:chat-messages')->name('chat.conversations.store');
        Route::get('/chat/conversations/{conversation}', [ChatController::class, 'show'])->name('chat.conversations.show');
        Route::put('/chat/conversations/{conversation}', [ChatController::class, 'updateConversation'])->name('chat.conversations.update');
        Route::put('/chat/conversations/{conversation}/mute', [ChatController::class, 'muteConversation'])->name('chat.conversations.mute');
        Route::delete('/chat/conversations/{conversation}/leave', [ChatController::class, 'leaveConversation'])->name('chat.conversations.leave');
        Route::delete('/chat/conversations/{conversation}/clear', [ChatController::class, 'clearConversation'])->name('chat.conversations.clear');
        Route::post('/chat/conversations/{conversation}/members', [ChatController::class, 'inviteMembers'])->middleware('throttle:chat-messages')->name('chat.conversations.members.store');
        Route::delete('/chat/conversations/{conversation}/members/{user}', [ChatController::class, 'removeMember'])->name('chat.conversations.members.destroy');
        Route::put('/chat/conversations/{conversation}/owner', [ChatController::class, 'transferOwner'])->name('chat.conversations.owner.update');
        Route::put('/chat/conversations/{conversation}/members/{user}/role', [ChatController::class, 'updateMemberRole'])->name('chat.conversations.members.role.update');
        Route::delete('/chat/conversations/{conversation}', [ChatController::class, 'destroyConversation'])->name('chat.conversations.destroy');
        Route::post('/chat/conversation-invitations/{invitation}/accept', [ChatController::class, 'acceptInvitation'])->name('chat.conversation-invitations.accept');
        Route::post('/chat/conversation-invitations/{invitation}/decline', [ChatController::class, 'declineInvitation'])->name('chat.conversation-invitations.decline');
        Route::get('/chat/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages.index');
        Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])->middleware('throttle:chat-messages')->name('chat.messages.store');
        Route::post('/chat/conversations/{conversation}/read', [ChatController::class, 'markRead'])->name('chat.conversations.read');
        Route::get('/chat/messages/{message}', [ChatController::class, 'message'])->name('chat.messages.show');
        Route::post('/chat/messages/{message}/reactions', [ChatController::class, 'react'])->middleware('throttle:chat-messages')->name('chat.messages.reactions.store');
        Route::delete('/chat/messages/{message}/hide', [ChatController::class, 'hideForMe'])->name('chat.messages.hide');
        Route::delete('/chat/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.messages.destroy');
        Route::post('/chat/conversations/{conversation}/typing', [ChatController::class, 'typing'])->middleware('throttle:chat-presence')->name('chat.typing');

        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/challenges', [ChallengeController::class, 'index'])->name('challenges.index');
        Route::post('/challenges', [ChallengeController::class, 'store'])->name('challenges.store');
        Route::get('/challenges/{challenge}', [ChallengeController::class, 'show'])->name('challenges.show');
        Route::put('/challenges/{challenge}', [ChallengeController::class, 'update'])->name('challenges.update');
        Route::delete('/challenges/{challenge}', [ChallengeController::class, 'destroy'])->name('challenges.destroy');
        Route::post('/challenges/{challenge}/join', [ChallengeController::class, 'join'])->name('challenges.join');
        Route::put('/challenges/{challenge}/invitation', [ChallengeController::class, 'respond'])->name('challenges.invitation.update');
        Route::put('/challenges/{challenge}/check-ins/{date}', [ChallengeController::class, 'checkin'])->name('challenges.check-ins.update');
        Route::get('/challenges/{challenge}/comments', [ChallengeController::class, 'comments'])->name('challenges.comments.index');
        Route::post('/challenges/{challenge}/comments', [ChallengeController::class, 'comment'])->middleware('throttle:content-comments')->name('challenges.comments.store');
        Route::post('/challenges/{challenge}/cancel', [ChallengeController::class, 'cancel'])->name('challenges.cancel');
        Route::get('/sport-matching', [SportMatchingController::class, 'index'])->name('sport-matching.index');
        Route::post('/sport-matching', [SportMatchingController::class, 'store'])->name('sport-matching.store');
        Route::post('/sport-matching/{sportMatching}/apply', [SportMatchingController::class, 'apply'])->name('sport-matching.apply');
        Route::post('/sport-matching/{sportMatching}/withdraw', [SportMatchingController::class, 'withdraw'])->name('sport-matching.withdraw');
        Route::post('/sport-matching/{sportMatching}/dismiss', [SportMatchingController::class, 'dismiss'])->name('sport-matching.dismiss');
        Route::put('/sport-matching/{sportMatching}/applications/{application}', [SportMatchingController::class, 'decide'])->name('sport-matching.applications.update');
        Route::post('/sport-matching/{sportMatching}/cancel', [SportMatchingController::class, 'cancel'])->name('sport-matching.cancel');
        Route::put('/sport-matching/{sportMatching}/attendance', [SportMatchingController::class, 'updateAttendance'])->name('sport-matching.attendance.update');
        Route::post('/sport-matching/{sportMatching}/attendance/no-show', [SportMatchingController::class, 'reportNoShow'])->name('sport-matching.attendance.no-show');
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/comments', [EventController::class, 'comments'])->name('events.comments.index');
        Route::post('/events/{event}/comments', [EventController::class, 'comment'])->middleware('throttle:content-comments')->name('events.comments.store');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
        Route::post('/events/{event}/participation', [EventController::class, 'respond'])->name('events.participation.respond');
        Route::post('/events/{event}/participation/cancel', [EventController::class, 'cancelParticipation'])->middleware('throttle:payment-actions')->name('events.participation.cancel');
        Route::post('/events/{event}/participation/substitute', [EventController::class, 'substituteParticipation'])->middleware('throttle:payment-actions')->name('events.participation.substitute');
        Route::delete('/events/{event}/participation', [EventController::class, 'leave'])->name('events.participation.leave');
        Route::get('/events/{event}/attendance', [EventController::class, 'attendance'])->name('events.attendance.index');
        Route::put('/events/{event}/attendance', [EventController::class, 'recordAttendance'])->name('events.attendance.update');
        Route::get('/events/{event}/attendance/corrections', [EventController::class, 'attendanceCorrections'])->name('events.attendance.corrections.index');
        Route::post('/events/{event}/check-in-tokens', [EventController::class, 'issueCheckInToken'])->middleware('throttle:payment-actions')->name('events.check-in-tokens.store');
        Route::post('/events/{event}/check-in', [EventController::class, 'checkIn'])->middleware('throttle:payment-actions')->name('events.check-in.store');
        Route::get('/events/{event}/decisions', [EventCompetitivenessController::class, 'decisions'])->name('events.decisions.index');
        Route::post('/events/{event}/decisions', [EventCompetitivenessController::class, 'createDecision'])->name('events.decisions.store');
        Route::post('/events/{event}/decisions/{decision}/close', [EventCompetitivenessController::class, 'closeDecision'])->name('events.decisions.close');
        Route::post('/events/{event}/decisions/{decision}/vote', [EventCompetitivenessController::class, 'castVote'])->name('events.decisions.vote');

        Route::middleware('purpose:training')->group(function () {
            Route::get('/training/route-options', [TrainingController::class, 'routeOptions'])->name('training.route-options');
            Route::get('/training/sessions', [TrainingSessionController::class, 'index'])->name('training.sessions.index');
            Route::post('/training/sessions', [TrainingSessionController::class, 'store'])->name('training.sessions.store');
            Route::get('/training/sessions/{trainingSession}', [TrainingSessionController::class, 'show'])->name('training.sessions.show');
            Route::put('/training/sessions/{trainingSession}', [TrainingSessionController::class, 'update'])->name('training.sessions.update');
            Route::delete('/training/sessions/{trainingSession}', [TrainingSessionController::class, 'destroy'])->name('training.sessions.destroy');
            Route::get('/training/plans', [TrainingController::class, 'plans'])->name('training.plans.index');
            Route::get('/training/templates', [TrainingController::class, 'templates'])->name('training.templates.index');
            Route::post('/training/plans', [TrainingController::class, 'storePlan'])->name('training.plans.store');
            Route::get('/training/plans/{trainingPlan}', [TrainingController::class, 'showPlan'])->name('training.plans.show');
            Route::put('/training/plans/{trainingPlan}', [TrainingController::class, 'updatePlan'])->name('training.plans.update');
            Route::delete('/training/plans/{trainingPlan}', [TrainingController::class, 'destroyPlan'])->name('training.plans.destroy');
            Route::post('/training/plans/{trainingPlan}/publish', [TrainingController::class, 'publishPlan'])->name('training.plans.publish');
            Route::post('/training/plans/{trainingPlan}/duplicate', [TrainingController::class, 'duplicatePlan'])->name('training.plans.duplicate');
            Route::post('/training/plans/{trainingPlan}/handover', [TrainingController::class, 'handoverPlan'])->name('training.plans.handover');
            Route::post('/training/plans/{trainingPlan}/template', [TrainingController::class, 'createTemplate'])->name('training.plans.template');
            Route::post('/training/templates/{trainingPlan}/instantiate', [TrainingController::class, 'instantiateTemplate'])->name('training.templates.instantiate');
            Route::post('/training/plans/{trainingPlan}/items', [TrainingController::class, 'storePlanItem'])->name('training.plans.items.store');
            Route::put('/training/plans/{trainingPlan}/items/{trainingPlanItem}', [TrainingController::class, 'updatePlanItem'])->name('training.plans.items.update');
            Route::delete('/training/plans/{trainingPlan}/items/{trainingPlanItem}', [TrainingController::class, 'destroyPlanItem'])->name('training.plans.items.destroy');
            Route::post('/training/plans/{trainingPlan}/items/{trainingPlanItem}/duplicate', [TrainingController::class, 'duplicatePlanItem'])->name('training.plans.items.duplicate');
            Route::post('/training/plans/{trainingPlan}/items/{trainingPlanItem}/missed', [TrainingController::class, 'markPlanItemMissed'])->name('training.plans.items.missed');
            Route::post('/training/ai/plans/preview', [MobileTrainingAiController::class, 'previewAiTrainingPlan'])->name('training.ai.plans.preview');
            Route::post('/training/ai/plans', [MobileTrainingAiController::class, 'storeAiTrainingPlan'])->name('training.ai.plans.store');
            Route::get('/training/logs', [TrainingController::class, 'logs'])->name('training.logs.index');
            Route::post('/training/logs', [TrainingController::class, 'storeLog'])->name('training.logs.store');
            Route::get('/training/logs/{trainingLog}', [TrainingController::class, 'showLog'])->name('training.logs.show');
            Route::post('/training/logs/{trainingLog}/feedback', [TrainingFeedbackController::class, 'store'])
                ->middleware('throttle:content-comments')
                ->name('training.logs.feedback.store');
            Route::put('/training/logs/{trainingLog}', [TrainingController::class, 'updateLog'])->name('training.logs.update');
            Route::delete('/training/logs/{trainingLog}', [TrainingController::class, 'destroyLog'])->name('training.logs.destroy');
            Route::get('/training/analytics', [TrainingAnalyticsController::class, 'index'])->name('training.analytics.index');
            Route::get('/training/overload-indicators', [TrainingAnalyticsController::class, 'overloadIndicators'])->name('training.overload-indicators.index');
            Route::get('/training/availability', [TrainingAvailabilityController::class, 'index'])->name('training.availability.index');
            Route::post('/training/availability', [TrainingAvailabilityController::class, 'store'])->name('training.availability.store');
            Route::put('/training/availability/{trainingAvailabilityStatus}', [TrainingAvailabilityController::class, 'update'])->name('training.availability.update');
            Route::delete('/training/availability/{trainingAvailabilityStatus}', [TrainingAvailabilityController::class, 'destroy'])->name('training.availability.destroy');
            Route::get('/training/exercises', [TrainingExerciseController::class, 'index'])->name('training.exercises.index');
            Route::post('/training/exercises', [TrainingExerciseController::class, 'store'])->name('training.exercises.store');
            Route::get('/training/exercises/{trainingExercise}', [TrainingExerciseController::class, 'show'])->name('training.exercises.show');
            Route::put('/training/exercises/{trainingExercise}', [TrainingExerciseController::class, 'update'])->name('training.exercises.update');
            Route::delete('/training/exercises/{trainingExercise}', [TrainingExerciseController::class, 'destroy'])->name('training.exercises.destroy');
            Route::post('/training/exercises/{trainingExercise}/add-to-plan', [TrainingExerciseController::class, 'addToPlan'])->name('training.exercises.add-to-plan');
        });
        Route::get('/trainer-cockpit{slash}', [MobileTrainerCockpitController::class, 'index'])
            ->where('slash', '/?')
            ->middleware('rollout:coach_daily_control')
            ->name('trainer-cockpit.index');
        Route::post('/trainer-cockpit/logs/{trainingLog}/feedback', [TrainingFeedbackController::class, 'store'])
            ->middleware(['throttle:content-comments', 'rollout:coach_daily_control'])
            ->name('trainer-cockpit.logs.feedback.store');

        Route::get('/nutrition', [NutritionController::class, 'index'])->name('nutrition.index');
        Route::patch('/nutrition/goal', [NutritionController::class, 'updateGoal'])->name('nutrition.goal.update');
        Route::get('/nutrition/foods/search', [NutritionController::class, 'searchFoods'])->name('nutrition.foods.search');
        Route::get('/nutrition/foods/barcode', [NutritionController::class, 'lookupBarcode'])->name('nutrition.foods.barcode');
        Route::post('/nutrition/ai/meal-image', [NutritionController::class, 'analyzeMealImage'])->name('nutrition.ai.meal-image');
        Route::post('/nutrition/meals', [NutritionController::class, 'storeMeal'])->name('nutrition.meals.store');
        Route::post('/nutrition/water', [NutritionController::class, 'storeWater'])->name('nutrition.water.store');
        Route::patch('/nutrition/meals/{nutritionMeal}', [NutritionController::class, 'updateMeal'])->name('nutrition.meals.update');
        Route::delete('/nutrition/meals/{nutritionMeal}', [NutritionController::class, 'destroyMeal'])->name('nutrition.meals.destroy');

        Route::get('/sport-routes', [MobileSportMapController::class, 'routes'])->name('sport-routes.index');
        Route::post('/sport-route-proposals', [MobileSportMapController::class, 'generateRouteProposal'])->name('sport-route-proposals.store');
        Route::post('/sport-routes', [MobileSportMapController::class, 'storeRoute'])->name('sport-routes.store');
        Route::get('/sport-routes/{sportRoute}', [MobileSportMapController::class, 'showRoute'])->name('sport-routes.show');
        Route::patch('/sport-routes/{sportRoute}', [MobileSportMapController::class, 'updateRoute'])->name('sport-routes.update');
        Route::post('/sport-routes/{sportRoute}/duplicate', [MobileSportMapController::class, 'duplicateRoute'])->name('sport-routes.duplicate');
        Route::delete('/sport-routes/{sportRoute}', [MobileSportMapController::class, 'destroyRoute'])->name('sport-routes.destroy');
        Route::get('/sport-tracks', [MobileSportMapController::class, 'tracks'])->name('sport-tracks.index');
        Route::post('/sport-tracks', [MobileSportMapController::class, 'storeTrack'])->name('sport-tracks.store');
        Route::patch('/sport-tracks/{sportRouteTrack}', [MobileSportMapController::class, 'updateTrack'])->name('sport-tracks.update');
        Route::post('/sport-tracks/{sportRouteTrack}/points', [MobileSportMapController::class, 'appendTrackPoints'])->name('sport-tracks.points');
        Route::post('/sport-tracks/{sportRouteTrack}/complete', [MobileSportMapController::class, 'completeTrack'])->name('sport-tracks.complete');
        Route::delete('/sport-tracks/{sportRouteTrack}', [MobileSportMapController::class, 'destroyTrack'])->name('sport-tracks.destroy');
        Route::get('/sport-places', [MobileSportMapController::class, 'places'])->name('sport-places.index');
        Route::post('/sport-places', [MobileSportMapController::class, 'storePlace'])->name('sport-places.store');
        Route::get('/sport-places/{sportPlace}', [MobileSportMapController::class, 'showPlace'])->name('sport-places.show');
        Route::patch('/sport-places/{sportPlace}', [MobileSportMapController::class, 'updatePlace'])->name('sport-places.update');
        Route::delete('/sport-places/{sportPlace}', [MobileSportMapController::class, 'destroyPlace'])->name('sport-places.destroy');

        Route::get('/subscription-plans', [SubscriptionController::class, 'plans'])->name('subscription-plans.index');
        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/subscription-plans/{subscriptionPlan}/checkout', [SubscriptionController::class, 'startCheckout'])->middleware('throttle:payment-actions')->name('subscription-plans.checkout');
        Route::get('/subscription-checkouts/{checkout}', [SubscriptionController::class, 'checkout'])->name('subscription-checkouts.show');
        Route::post('/subscription-checkouts/{checkout}/cancel', [SubscriptionController::class, 'cancelCheckout'])->middleware('throttle:payment-actions')->name('subscription-checkouts.cancel');
        Route::post('/subscriptions/user/{subscription}/cancel', [SubscriptionController::class, 'cancelUserSubscription'])->middleware('throttle:payment-actions')->name('subscriptions.user.cancel');
        Route::post('/subscriptions/user/{subscription}/renew', [SubscriptionController::class, 'renewUserSubscription'])->middleware('throttle:payment-actions')->name('subscriptions.user.renew');

        Route::get('/commerce/products', [CommerceController::class, 'products'])->name('commerce.products.index');
        Route::get('/commerce/wishlist', [CommerceController::class, 'wishlist'])->name('commerce.wishlist.index');
        Route::get('/commerce/products/{product}/reviews', [CommerceController::class, 'reviews'])->name('commerce.products.reviews.index');
        Route::post('/commerce/products/{product}/reviews', [CommerceController::class, 'storeReview'])->middleware('throttle:content-comments')->name('commerce.products.reviews.store');
        Route::post('/commerce/products/{product}/wishlist', [CommerceController::class, 'storeWishlist'])->name('commerce.products.wishlist.store');
        Route::delete('/commerce/products/{product}/wishlist', [CommerceController::class, 'destroyWishlist'])->name('commerce.products.wishlist.destroy');
        Route::get('/commerce/products/{product}', [CommerceController::class, 'showProduct'])->name('commerce.products.show');
        Route::get('/commerce/cart', [MobileCommerceCheckoutController::class, 'cart'])->name('commerce.cart.show');
        Route::post('/commerce/cart/items/{product}', [MobileCommerceCheckoutController::class, 'addCartItem'])->name('commerce.cart.items.store');
        Route::patch('/commerce/cart/items/{item}', [MobileCommerceCheckoutController::class, 'updateCartItem'])->name('commerce.cart.items.update');
        Route::delete('/commerce/cart/items/{item}', [MobileCommerceCheckoutController::class, 'removeCartItem'])->name('commerce.cart.items.destroy');
        Route::post('/commerce/cart/checkout', [MobileCommerceCheckoutController::class, 'checkoutCart'])->middleware('throttle:payment-actions')->name('commerce.cart.checkout');
        Route::get('/commerce/orders', [CommerceController::class, 'orders'])->name('commerce.orders.index');
        Route::get('/commerce/orders/{order}', [CommerceController::class, 'showOrder'])->name('commerce.orders.show');
        Route::post('/commerce/orders/{order}/cancel', [MobileCommerceCheckoutController::class, 'cancelOrder'])->middleware('throttle:payment-actions')->name('commerce.orders.cancel');
        Route::post('/commerce/orders/{order}/issue', [MobileCommerceCheckoutController::class, 'reportOrderIssue'])->name('commerce.orders.issue');
        Route::post('/commerce/orders/{order}/returns', [MobileCommerceCheckoutController::class, 'requestReturn'])->name('commerce.orders.returns.store');
        Route::get('/commerce/orders/{order}/invoice', [MobileCommerceCheckoutController::class, 'downloadInvoice'])->name('commerce.orders.invoice');
        Route::get('/commerce/orders/{order}/credit-note', [MobileCommerceCheckoutController::class, 'downloadCreditNote'])->name('commerce.orders.credit-note');
        Route::get('/outfit-subscriptions', [MobileOutfitSubscriptionController::class, 'index'])->name('outfit-subscriptions.index');
        Route::put('/outfit-subscriptions/style-profile', [MobileOutfitSubscriptionController::class, 'updateProfile'])->name('outfit-subscriptions.profile.update');
        Route::post('/outfit-subscriptions/plans/{plan}', [MobileOutfitSubscriptionController::class, 'store'])->middleware('throttle:payment-actions')->name('outfit-subscriptions.store');
        Route::post('/outfit-subscriptions/{subscription}/pause', [MobileOutfitSubscriptionController::class, 'pause'])->name('outfit-subscriptions.pause');
        Route::post('/outfit-subscriptions/{subscription}/resume', [MobileOutfitSubscriptionController::class, 'resume'])->name('outfit-subscriptions.resume');
        Route::post('/outfit-subscriptions/{subscription}/cancel', [MobileOutfitSubscriptionController::class, 'cancel'])->name('outfit-subscriptions.cancel');
        Route::post('/outfit-deliveries/{delivery}/issue', [MobileOutfitSubscriptionController::class, 'requestDeliveryIssue'])->name('outfit-deliveries.issue.store');
        Route::get('/commerce/seller', [MobileCommerceCheckoutController::class, 'sellerDashboard'])->name('commerce.seller.show');
        Route::post('/commerce/seller/application', [MobileCommerceCheckoutController::class, 'storeSellerApplication'])->name('commerce.seller.application.store');
        Route::put('/commerce/seller/provider-profile', [MobileCommerceCheckoutController::class, 'storeProviderProfile'])->name('commerce.seller.provider-profile.update');
        Route::post('/commerce/seller/provider-locations', [MobileCommerceCheckoutController::class, 'storeProviderLocation'])->name('commerce.seller.provider-locations.store');
        Route::put('/commerce/seller/provider-locations/{location}', [MobileCommerceCheckoutController::class, 'updateProviderLocation'])->name('commerce.seller.provider-locations.update');
        Route::delete('/commerce/seller/provider-locations/{location}', [MobileCommerceCheckoutController::class, 'destroyProviderLocation'])->name('commerce.seller.provider-locations.destroy');
        Route::put('/commerce/seller/payout-profile', [MobileCommerceCheckoutController::class, 'storePayoutProfile'])->name('commerce.seller.payout-profile.update');
        Route::post('/commerce/seller/payouts', [MobileCommerceCheckoutController::class, 'requestPayout'])->middleware('throttle:payment-actions')->name('commerce.seller.payouts.store');
        Route::post('/commerce/seller/products', [MobileCommerceCheckoutController::class, 'storeOwnProduct'])->middleware('throttle:file-uploads')->name('commerce.seller.products.store');
        Route::put('/commerce/seller/products/{product}', [MobileCommerceCheckoutController::class, 'updateOwnProduct'])->middleware('throttle:file-uploads')->name('commerce.seller.products.update');
        Route::patch('/commerce/seller/products/{product}/status', [MobileCommerceCheckoutController::class, 'updateOwnProductStatus'])->name('commerce.seller.products.status');
        Route::delete('/commerce/seller/products/{product}', [MobileCommerceCheckoutController::class, 'destroyOwnProduct'])->name('commerce.seller.products.destroy');
        Route::post('/commerce/seller/campaigns', [MobileCommerceCheckoutController::class, 'storeOwnCampaign'])->middleware('throttle:payment-actions')->name('commerce.seller.campaigns.store');
        Route::put('/commerce/seller/campaigns/{campaign}', [MobileCommerceCheckoutController::class, 'updateOwnCampaign'])->name('commerce.seller.campaigns.update');
        Route::patch('/commerce/seller/campaigns/{campaign}/status', [MobileCommerceCheckoutController::class, 'updateOwnCampaignStatus'])->name('commerce.seller.campaigns.status');
        Route::delete('/commerce/seller/campaigns/{campaign}', [MobileCommerceCheckoutController::class, 'destroyOwnCampaign'])->name('commerce.seller.campaigns.destroy');
        Route::post('/commerce/seller/website-requests', [MobileCommerceCheckoutController::class, 'storeWebsiteRequest'])->name('commerce.seller.website-requests.store');

        Route::middleware(EnsurePlatformAdminTwoFactor::class)->group(function () {
            Route::get('/admin/system', [AdminSystemController::class, 'dashboard'])->name('admin.system.dashboard');
            Route::put('/admin/system/settings', [AdminSystemController::class, 'updateSettings'])->name('admin.system.settings.update');
            Route::get('/admin/mail', [AdminMailController::class, 'dashboard'])->name('admin.mail.dashboard');
            Route::put('/admin/mail/preferences', [AdminMailController::class, 'updatePreferences'])->name('admin.mail.preferences');
            Route::put('/admin/mail/senders/{category}', [AdminMailController::class, 'updateSender'])->name('admin.mail.senders.update');
            Route::post('/admin/mail/senders/{category}/test', [AdminMailController::class, 'testSender'])->name('admin.mail.senders.test');
            Route::post('/admin/mail/scheduled/preview', [AdminMailController::class, 'previewScheduled'])->name('admin.mail.scheduled.preview');
            Route::post('/admin/mail/scheduled', [AdminMailController::class, 'schedule'])->name('admin.mail.scheduled.store');
            Route::put('/admin/mail/scheduled/{mailDelivery}/cancel', [AdminMailController::class, 'cancelScheduled'])->name('admin.mail.scheduled.cancel');
            Route::post('/admin/mail/deliveries/{mailDelivery}/resend', [AdminMailController::class, 'resend'])->name('admin.mail.deliveries.resend');
            Route::put('/admin/mail/deliveries/{mailDelivery}/resolve', [AdminMailController::class, 'resolve'])->name('admin.mail.deliveries.resolve');
            Route::get('/admin/support/tickets', [SupportTicketController::class, 'adminIndex'])->name('admin.support.tickets.index');
            Route::patch('/admin/support/tickets/{supportTicket}', [SupportTicketController::class, 'adminUpdate'])->name('admin.support.tickets.update');
            Route::get('/admin/outfits', [AdminOutfitController::class, 'dashboard'])->name('admin.outfits.dashboard');
            Route::post('/admin/outfits/plans', [AdminOutfitController::class, 'storePlan'])->name('admin.outfits.plans.store');
            Route::put('/admin/outfits/plans/{outfitSubscriptionPlan}', [AdminOutfitController::class, 'updatePlan'])->name('admin.outfits.plans.update');
            Route::delete('/admin/outfits/plans/{outfitSubscriptionPlan}', [AdminOutfitController::class, 'destroyPlan'])->name('admin.outfits.plans.destroy');
            Route::post('/admin/outfits/visuals', [AdminOutfitController::class, 'updateVisuals'])->middleware('throttle:file-uploads')->name('admin.outfits.visuals.update');
            Route::post('/admin/outfits/subscriptions/{outfitSubscription}/mark-paid', [AdminOutfitController::class, 'markSubscriptionPaid'])->middleware('throttle:payment-actions')->name('admin.outfits.subscriptions.mark-paid');
            Route::post('/admin/outfits/subscriptions/{outfitSubscription}/mark-unpaid', [AdminOutfitController::class, 'markSubscriptionUnpaid'])->middleware('throttle:payment-actions')->name('admin.outfits.subscriptions.mark-unpaid');
            Route::put('/admin/outfits/subscriptions/{outfitSubscription}/shipping-address', [AdminOutfitController::class, 'updateShippingAddress'])->name('admin.outfits.subscriptions.shipping-address');
            Route::post('/admin/outfits/subscriptions/{outfitSubscription}/payment-reminder', [AdminOutfitController::class, 'remindPayment'])->name('admin.outfits.subscriptions.payment-reminder');
            Route::post('/admin/outfits/subscriptions/{outfitSubscription}/cancel', [AdminOutfitController::class, 'cancelSubscription'])->name('admin.outfits.subscriptions.cancel');
            Route::delete('/admin/outfits/subscriptions/{outfitSubscription}', [AdminOutfitController::class, 'destroySubscription'])->name('admin.outfits.subscriptions.destroy');
            Route::put('/admin/outfits/deliveries/{outfitDelivery}', [AdminOutfitController::class, 'updateDelivery'])->name('admin.outfits.deliveries.update');
            Route::put('/admin/outfits/deliveries/{outfitDelivery}/issue', [AdminOutfitController::class, 'updateDeliveryIssue'])->name('admin.outfits.deliveries.issue');
            Route::post('/admin/outfits/deliveries/{outfitDelivery}/shipped', [AdminOutfitController::class, 'markDeliveryShipped'])->name('admin.outfits.deliveries.shipped');
            Route::post('/admin/outfits/deliveries/{outfitDelivery}/delivered', [AdminOutfitController::class, 'markDeliveryDelivered'])->name('admin.outfits.deliveries.delivered');
            Route::delete('/admin/outfits/deliveries/{outfitDelivery}', [AdminOutfitController::class, 'destroyDelivery'])->name('admin.outfits.deliveries.destroy');
            Route::get('/admin/backoffice', [AdminBackofficeController::class, 'dashboard'])->name('admin.backoffice.dashboard');
            Route::patch('/admin/backoffice/plans/{subscriptionPlan}', [AdminBackofficeController::class, 'updatePlan'])->name('admin.backoffice.plans.update');
            Route::put('/admin/backoffice/users/{user}/subscription', [AdminBackofficeController::class, 'assignUserSubscription'])->name('admin.backoffice.users.subscription');
            Route::put('/admin/backoffice/clubs/{club}/subscription', [AdminBackofficeController::class, 'assignClubSubscription'])->name('admin.backoffice.clubs.subscription');
            Route::post('/admin/backoffice/user-subscriptions/{subscription}/cancel', [AdminBackofficeController::class, 'cancelUserSubscription'])->name('admin.backoffice.user-subscriptions.cancel');
            Route::post('/admin/backoffice/user-subscriptions/{subscription}/renew', [AdminBackofficeController::class, 'renewUserSubscription'])->name('admin.backoffice.user-subscriptions.renew');
            Route::post('/admin/backoffice/club-subscriptions/{subscription}/cancel', [AdminBackofficeController::class, 'cancelClubSubscription'])->name('admin.backoffice.club-subscriptions.cancel');
            Route::post('/admin/backoffice/club-subscriptions/{subscription}/renew', [AdminBackofficeController::class, 'renewClubSubscription'])->name('admin.backoffice.club-subscriptions.renew');
            Route::post('/admin/backoffice/transfers/{checkout}/mark-paid', [AdminBackofficeController::class, 'markTransferPaid'])->middleware('throttle:payment-actions')->name('admin.backoffice.transfers.mark-paid');
            Route::post('/admin/backoffice/subscription-invoices/{subscriptionInvoice}/mark-paid', [AdminBackofficeController::class, 'markSubscriptionInvoicePaid'])->middleware('throttle:payment-actions')->name('admin.backoffice.subscription-invoices.mark-paid');
            Route::post('/admin/backoffice/payments', [AdminBackofficeController::class, 'storePayment'])->middleware('throttle:payment-actions')->name('admin.backoffice.payments.store');
            Route::delete('/admin/backoffice/payments/{payment}', [AdminBackofficeController::class, 'destroyPayment'])->name('admin.backoffice.payments.destroy');
            Route::post('/admin/backoffice/invoices', [AdminBackofficeController::class, 'storeInvoice'])->name('admin.backoffice.invoices.store');
            Route::patch('/admin/backoffice/invoices/{invoice}/status', [AdminBackofficeController::class, 'updateInvoiceStatus'])->name('admin.backoffice.invoices.status');
            Route::delete('/admin/backoffice/invoices/{invoice}', [AdminBackofficeController::class, 'destroyInvoice'])->name('admin.backoffice.invoices.destroy');
            Route::post('/admin/backoffice/contracts', [AdminBackofficeController::class, 'storeContract'])->name('admin.backoffice.contracts.store');
            Route::patch('/admin/backoffice/contracts/{operatingContract}', [AdminBackofficeController::class, 'updateContract'])->name('admin.backoffice.contracts.update');
            Route::delete('/admin/backoffice/contracts/{operatingContract}', [AdminBackofficeController::class, 'destroyContract'])->name('admin.backoffice.contracts.destroy');
            Route::get('/admin/platform', [PlatformAdminController::class, 'dashboard'])->name('admin.platform.dashboard');
            Route::get('/admin/media-guidelines', [MediaGuidelineController::class, 'index'])->middleware('can:blog.view')->name('admin.media-guidelines');
            Route::post('/admin/media-guidelines/visuals', [MediaGuidelineController::class, 'updateVisuals'])->middleware(['can:blog.view', 'throttle:file-uploads'])->name('admin.media-guidelines.visuals');
            Route::get('/admin/members', [MemberController::class, 'index'])->middleware('can:users.view')->name('admin.members.index');
            Route::post('/admin/members/{user}/inactivity-notice', [MemberController::class, 'sendInactivityNotice'])->middleware('can:system.manage')->name('admin.members.inactivity-notice');
            Route::get('/admin/members/{user}', [MemberController::class, 'edit'])->middleware('can:users.edit')->name('admin.members.edit');
            Route::put('/admin/members/{user}', [MemberController::class, 'update'])->middleware('can:users.edit')->name('admin.members.update');
            Route::delete('/admin/members/{user}', [MemberController::class, 'destroy'])->middleware('can:users.delete')->name('admin.members.destroy');
            Route::get('/admin/clubs', [AdminClubController::class, 'index'])->middleware('can:system.manage')->name('admin.clubs.index');
            Route::delete('/admin/clubs/{club}', [AdminClubController::class, 'destroy'])->middleware('can:system.manage')->name('admin.clubs.destroy');
            Route::get('/admin/operations', [AdminInsightsController::class, 'operations'])->middleware('rollout:admin_operations')->name('admin.operations');
            Route::get('/admin/product-analytics', [AdminInsightsController::class, 'analytics'])->name('admin.product-analytics');
            Route::get('/admin/trainer-applications', [AccountRoleApplicationController::class, 'adminIndex'])->name('admin.trainer-applications');
            Route::put('/admin/trainer-applications/{application}/approve', [AccountRoleApplicationController::class, 'approve'])->name('admin.trainer-applications.approve');
            Route::put('/admin/trainer-applications/{application}/reject', [AccountRoleApplicationController::class, 'reject'])->name('admin.trainer-applications.reject');
            Route::post('/admin/platform/users', [PlatformAdminController::class, 'storeUser'])->name('admin.platform.users.store');
            Route::patch('/admin/platform/users/{user}/status', [PlatformAdminController::class, 'updateUserStatus'])->name('admin.platform.users.status');
            Route::patch('/admin/platform/clubs/{club}/approve', [PlatformAdminController::class, 'approveClub'])->name('admin.platform.clubs.approve');
            Route::patch('/admin/platform/clubs/{club}/verification-status', [AdminClubController::class, 'updateVerificationStatus'])->middleware('can:system.manage')->name('admin.platform.clubs.verification-status');
            Route::patch('/admin/platform/clubs/{club}/reject', [PlatformAdminController::class, 'rejectClub'])->name('admin.platform.clubs.reject');
            Route::post('/admin/platform/sports', [PlatformAdminController::class, 'storeSport'])->name('admin.platform.sports.store');
            Route::patch('/admin/platform/sports/{sport}', [PlatformAdminController::class, 'updateSport'])->name('admin.platform.sports.update');
            Route::delete('/admin/platform/sports/{sport}', [PlatformAdminController::class, 'destroySport'])->name('admin.platform.sports.destroy');
            Route::post('/admin/platform/badges', [PlatformAdminController::class, 'storeBadge'])->name('admin.platform.badges.store');
            Route::patch('/admin/platform/badges/{badge}', [PlatformAdminController::class, 'updateBadge'])->name('admin.platform.badges.update');
            Route::delete('/admin/platform/badges/{badge}', [PlatformAdminController::class, 'destroyBadge'])->name('admin.platform.badges.destroy');
            Route::post('/admin/platform/roles', [PlatformAdminController::class, 'storeRole'])->name('admin.platform.roles.store');
            Route::patch('/admin/platform/roles/{role}', [PlatformAdminController::class, 'updateRole'])->name('admin.platform.roles.update');
            Route::delete('/admin/platform/roles/{role}', [PlatformAdminController::class, 'destroyRole'])->name('admin.platform.roles.destroy');
            Route::post('/admin/platform/permissions', [PlatformAdminController::class, 'storePermission'])->name('admin.platform.permissions.store');
            Route::patch('/admin/platform/moderation/flags/{flag}', [PlatformAdminController::class, 'updateModerationFlag'])->name('admin.platform.moderation.flags.update');
            Route::patch('/admin/platform/moderation/reports/{report}', [PlatformAdminController::class, 'updateModerationReport'])->name('admin.platform.moderation.reports.update');
            Route::patch('/admin/platform/moderation/reports/{report}/appeal', [PlatformAdminController::class, 'decideModerationAppeal'])->name('admin.platform.moderation.reports.appeal');
            Route::patch('/admin/platform/gamification-rules/{gamificationRule}', [PlatformAdminController::class, 'updateGamificationRule'])->name('admin.platform.gamification-rules.update');
            Route::get('/admin/commerce', [MobileAdminCommerceController::class, 'dashboard'])->name('admin.commerce.dashboard');
            Route::get('/admin/commerce/catalog', [MobileAdminCommerceController::class, 'catalog'])->name('admin.commerce.catalog');
            Route::patch('/admin/commerce/public-contact-requests/{publicContactRequest}', [MobileAdminCommerceController::class, 'updatePublicContactRequest'])->name('admin.commerce.public-contact-requests.update');
            Route::get('/admin/commerce/export', [MobileAdminCommerceController::class, 'export'])->name('admin.commerce.export');
            Route::post('/admin/commerce/coupons', [MobileAdminCommerceController::class, 'storeCoupon'])->name('admin.commerce.coupons.store');
            Route::patch('/admin/commerce/coupons/{coupon}', [MobileAdminCommerceController::class, 'updateCoupon'])->name('admin.commerce.coupons.update');
            Route::post('/admin/commerce/addons', [MobileAdminCommerceController::class, 'storeAddon'])->name('admin.commerce.addons.store');
            Route::patch('/admin/commerce/addons/{addon}', [MobileAdminCommerceController::class, 'updateAddon'])->name('admin.commerce.addons.update');
            Route::post('/admin/commerce/tax-rates', [MobileAdminCommerceController::class, 'storeTaxRate'])->name('admin.commerce.tax-rates.store');
            Route::patch('/admin/commerce/tax-rates/{taxRate}', [MobileAdminCommerceController::class, 'updateTaxRate'])->name('admin.commerce.tax-rates.update');
            Route::post('/admin/commerce/shipping-rates', [MobileAdminCommerceController::class, 'storeShippingRate'])->name('admin.commerce.shipping-rates.store');
            Route::patch('/admin/commerce/shipping-rates/{shippingRate}', [MobileAdminCommerceController::class, 'updateShippingRate'])->name('admin.commerce.shipping-rates.update');
            Route::post('/admin/commerce/products', [MobileAdminCommerceController::class, 'storeProduct'])->name('admin.commerce.products.store');
            Route::put('/admin/commerce/products/{product}', [MobileAdminCommerceController::class, 'updateProduct'])->name('admin.commerce.products.update');
            Route::delete('/admin/commerce/products/{product}', [MobileAdminCommerceController::class, 'destroyProduct'])->name('admin.commerce.products.destroy');
            Route::post('/admin/commerce/products/{product}/stock', [MobileAdminCommerceController::class, 'adjustProductStock'])->name('admin.commerce.products.stock');
            Route::patch('/admin/commerce/products/{product}/status', [MobileAdminCommerceController::class, 'updateProductStatus'])->name('admin.commerce.products.status');
            Route::patch('/admin/commerce/seller-applications/{sellerApplication}', [MobileAdminCommerceController::class, 'updateSellerApplication'])->name('admin.commerce.seller-applications.update');
            Route::patch('/admin/commerce/website-requests/{websiteRequest}', [MobileAdminCommerceController::class, 'updateWebsiteRequest'])->name('admin.commerce.website-requests.update');
            Route::post('/admin/commerce/campaigns', [MobileAdminCommerceController::class, 'storeCampaign'])->name('admin.commerce.campaigns.store');
            Route::put('/admin/commerce/campaigns/{campaign}', [MobileAdminCommerceController::class, 'updateCampaign'])->name('admin.commerce.campaigns.update');
            Route::patch('/admin/commerce/campaigns/{campaign}/status', [MobileAdminCommerceController::class, 'updateCampaignStatus'])->name('admin.commerce.campaigns.status');
            Route::patch('/admin/commerce/orders/{order}/shipping', [MobileAdminCommerceController::class, 'updateOrderShipping'])->name('admin.commerce.orders.shipping');
            Route::post('/admin/commerce/orders/{order}/mark-paid', [MobileAdminCommerceController::class, 'markOrderPaid'])->middleware('throttle:payment-actions')->name('admin.commerce.orders.mark-paid');
            Route::patch('/admin/commerce/orders/{order}/issue', [MobileAdminCommerceController::class, 'updateOrderIssue'])->name('admin.commerce.orders.issue');
            Route::post('/admin/commerce/orders/{order}/issue/reply', [MobileAdminCommerceController::class, 'replyOrderIssue'])->name('admin.commerce.orders.issue.reply');
            Route::post('/admin/commerce/orders/{order}/refund', [MobileAdminCommerceController::class, 'refundOrder'])->middleware('throttle:payment-actions')->name('admin.commerce.orders.refund');
            Route::get('/admin/commerce/orders/{order}/documents', [MobileAdminCommerceController::class, 'orderDocuments'])->name('admin.commerce.orders.documents');
            Route::get('/admin/commerce/orders/{order}/invoice', [MobileAdminCommerceController::class, 'downloadInvoice'])->name('admin.commerce.orders.invoice');
            Route::get('/admin/commerce/orders/{order}/credit-note', [MobileAdminCommerceController::class, 'downloadCreditNote'])->name('admin.commerce.orders.credit-note');
            Route::patch('/admin/commerce/returns/{returnRequest}', [MobileAdminCommerceController::class, 'updateReturnRequest'])->name('admin.commerce.returns.update');
            Route::post('/admin/commerce/payouts/users/{user}', [MobileAdminCommerceController::class, 'createPayout'])->middleware('throttle:payment-actions')->name('admin.commerce.payouts.store');
            Route::patch('/admin/commerce/payouts/{payout}/paid', [MobileAdminCommerceController::class, 'markPayoutPaid'])->middleware('throttle:payment-actions')->name('admin.commerce.payouts.paid');
            Route::patch('/admin/commerce/payout-profiles/{profile}', [MobileAdminCommerceController::class, 'updatePayoutProfile'])->name('admin.commerce.payout-profiles.update');
            Route::put('/admin/commerce/marketplace-visuals', [MobileAdminCommerceController::class, 'updateMarketplaceVisuals'])->name('admin.commerce.marketplace-visuals.update');
            Route::put('/admin/commerce/marketplace-commissions', [MobileAdminCommerceController::class, 'updateMarketplaceCommissions'])->name('admin.commerce.marketplace-commissions.update');
            Route::put('/admin/commerce/settings', [MobileAdminCommerceController::class, 'updateCommerceSettings'])->name('admin.commerce.settings.update');
            Route::post('/admin/subscription-checkouts/{checkout}/mark-paid', [SubscriptionController::class, 'markCheckoutPaid'])->middleware('throttle:payment-actions')->name('admin.subscription-checkouts.mark-paid');
        });
    });
});
