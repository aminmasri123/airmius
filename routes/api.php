<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AccountDeletionController as MobileAccountDeletionController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ClubController;
use App\Http\Controllers\Api\V1\CommentController as MobileCommentController;
use App\Http\Controllers\Api\V1\CommerceController;
use App\Http\Controllers\Api\V1\AdminCommerceController as MobileAdminCommerceController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\MaturityController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MobileDeepLinkController;
use App\Http\Controllers\Api\V1\MobileMetaController;
use App\Http\Controllers\Api\V1\MobilePushDeviceController;
use App\Http\Controllers\Api\V1\MobileSyncController;
use App\Http\Controllers\Api\V1\NutritionController;
use App\Http\Controllers\Api\V1\NotificationController as MobileNotificationController;
use App\Http\Controllers\Api\V1\PostImageUploadController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StoryController as MobileStoryController;
use App\Http\Controllers\Api\V1\SportProfileController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SportMapController as MobileSportMapController;
use App\Http\Controllers\Api\V1\TeamCompetitivenessController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamPenaltyController;
use App\Http\Controllers\Api\V1\TrainingController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\ContentReportController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Middleware\EnsureApiCorsHeaders;
use App\Http\Middleware\EnsurePlatformAdminTwoFactor;
use App\Http\Resources\Api\V1\ClubMembershipRequestResource;
use App\Http\Resources\Api\V1\ClubResource;
use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\Post;
use App\Models\Sport;
use App\Models\Team;
use App\Services\TeamDailyLifeService;
use App\Services\ClubService;
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
    Route::options('/{any}', fn () => response('', 204))
        ->middleware(EnsureApiCorsHeaders::class)
        ->where('any', '.*')
        ->name('options');

    Route::get('/meta', MobileMetaController::class)->name('meta');
    Route::get('/posts/{post}/image', function (Post $post) {
        abort_unless($post->image, 404);

        if (str_starts_with($post->image, 'http://') || str_starts_with($post->image, 'https://')) {
            return redirect()->away($post->image);
        }

        try {
            return Storage::disk(UploadStorage::disk())->response($post->image);
        } catch (\Throwable $error) {
            $url = UploadStorage::url($post->image);
            abort_unless($url, 404);

            return redirect()->away($url);
        }
    })->name('posts.image');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::get('/auth/register/email', [AuthController::class, 'registrationEmail'])
        ->middleware('throttle:20,1')
        ->name('auth.register.email');

    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('auth.register');

    Route::post('/teams', [TeamController::class, 'storeWithToken'])
        ->middleware(['throttle:30,1', EnsureApiCorsHeaders::class])
        ->name('teams.store.token');

    Route::post('/clubs/{club}/members/invite-token', [ClubController::class, 'inviteMemberWithToken'])
        ->middleware(['throttle:30,1', EnsureApiCorsHeaders::class])
        ->name('clubs.members.invite.token');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/account/deletion-code', [MobileAccountDeletionController::class, 'sendCode'])
            ->middleware('throttle:5,1')
            ->name('account.deletion-code');
        Route::delete('/account', [MobileAccountDeletionController::class, 'destroy'])
            ->middleware('throttle:5,1')
            ->name('account.destroy');

        Route::get('/me', [MeController::class, 'show'])->name('me.show');
        Route::put('/me/profile', [MeController::class, 'updateProfile'])->name('me.profile.update');
        Route::patch('/me/language', [MeController::class, 'updateLanguage'])->name('me.language');

        Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/privacy/export', [PrivacyController::class, 'export'])->name('privacy.export');
        Route::patch('/privacy/correction', [PrivacyController::class, 'correct'])->name('privacy.correct');
        Route::post('/privacy/withdraw-consents', [PrivacyController::class, 'withdrawConsents'])->name('privacy.withdraw-consents');
        Route::get('/billing/invoices', [SettingsController::class, 'invoices'])->name('billing.invoices.index');
        Route::get('/billing/invoices/{invoice}', [SettingsController::class, 'invoice'])->whereNumber('invoice')->name('billing.invoices.show');
        Route::get('/dashboard/daily-flow', [DashboardController::class, 'dailyFlow'])->name('dashboard.daily-flow');
        Route::match(['get', 'post'], '/mobile/sync', MobileSyncController::class)->name('mobile.sync');
        Route::post('/mobile/push-devices', [MobilePushDeviceController::class, 'store'])->name('mobile.push-devices.store');
        Route::delete('/mobile/push-devices/{deviceId}', [MobilePushDeviceController::class, 'destroy'])->name('mobile.push-devices.destroy');
        Route::post('/mobile/deep-links/resolve', [MobileDeepLinkController::class, 'resolve'])->name('mobile.deep-links.resolve');
        Route::get('/search', GlobalSearchController::class)->name('search');
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

        Route::get('/users/me/sport-cv', [SportProfileController::class, 'me'])->name('users.me.sport-cv');
        Route::get('/users/{user}/sport-cv', [SportProfileController::class, 'show'])->name('users.sport-cv.show');
        Route::get('/sport-profiles/scout-search', [SportProfileController::class, 'scoutSearch'])->name('sport-profiles.scout-search');

        Route::get('/notifications', [MobileNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{notification}', [MobileNotificationController::class, 'show'])->name('notifications.show');
        Route::post('/notifications/read-all', [MobileNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [MobileNotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/{notification}/unread', [MobileNotificationController::class, 'markAsUnread'])->name('notifications.unread');
        Route::delete('/notifications/{notification}', [MobileNotificationController::class, 'destroy'])->name('notifications.destroy');

        Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');
        Route::post('/post-images', PostImageUploadController::class)->middleware('throttle:file-uploads')->name('post-images.store');
        Route::post('/feed', [FeedController::class, 'store'])->name('feed.store');
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
        Route::put('/clubs/{club}', function (Request $request, Club $club, ClubService $clubs) {
            Gate::authorize('update', $club);

            $data = $request->validate(ClubProfileRules::update());

            $clubs->update($club, $data);
            $club->refresh()->loadCount(['users', 'teams']);

            return response()->json([
                'message' => 'Verein aktualisiert.',
                'data' => (new ClubResource($club))->resolve($request),
            ]);
        })->name('clubs.update');
        Route::get('/clubs/{club}/members', [ClubController::class, 'members'])->name('clubs.members.index');
        Route::post('/clubs/{club}/members/invite', [ClubController::class, 'inviteMember'])->name('clubs.members.invite');
        Route::put('/clubs/{club}/members/{user}/role', [ClubController::class, 'updateMemberRole'])->name('clubs.members.role.update');
        Route::put('/clubs/{club}/membership/settings', [ClubController::class, 'updateMembershipSettings'])->name('clubs.membership.settings.update');
        Route::post('/clubs/{club}/membership/types', [ClubController::class, 'storeMembershipType'])->name('clubs.membership.types.store');
        Route::put('/clubs/{club}/membership/types/{membershipType}', [ClubController::class, 'updateMembershipType'])->name('clubs.membership.types.update');
        Route::post('/clubs/{club}/membership/contribution-rules', [ClubController::class, 'storeContributionRule'])->name('clubs.membership.contribution-rules.store');
        Route::put('/clubs/{club}/membership/contribution-rules/{contributionRule}', [ClubController::class, 'updateContributionRule'])->name('clubs.membership.contribution-rules.update');
        Route::post('/clubs/{club}/membership-invoices/{invoice}/payments', [ClubController::class, 'recordMembershipPayment'])->middleware('throttle:payment-actions')->name('clubs.membership-invoices.payments.store');
        Route::post('/clubs/{club}/donations', [ClubController::class, 'recordDonation'])->middleware('throttle:payment-actions')->name('clubs.donations.store');
        Route::post('/clubs/{club}/prepayments', [ClubController::class, 'recordPrepayment'])->middleware('throttle:payment-actions')->name('clubs.prepayments.store');
        Route::put('/clubs/{club}/payments/{payment}', [ClubController::class, 'updatePayment'])->middleware('throttle:payment-actions')->name('clubs.payments.update');
        Route::post('/clubs/{club}/finance-entries', [ClubController::class, 'storeFinanceEntry'])->name('clubs.finance-entries.store');
        Route::put('/clubs/{club}/finance-entries/{financeEntry}', [ClubController::class, 'updateFinanceEntry'])->name('clubs.finance-entries.update');
        Route::get('/clubs/{club}/billing', [ClubController::class, 'billing'])->name('clubs.billing');
        Route::get('/clubs/{club}/membership-requests', [ClubController::class, 'membershipRequests'])->name('clubs.membership-requests.index');
        Route::post('/clubs/{club}/membership-requests', [ClubController::class, 'storeMembershipRequest'])->name('clubs.membership-requests.store');
        Route::delete('/clubs/{club}/membership-requests', [ClubController::class, 'withdrawMembershipRequest'])->name('clubs.membership-requests.withdraw');
        Route::post('/clubs/{club}/membership-requests/{membershipRequest}/approve', [ClubController::class, 'approveMembershipRequest'])->name('clubs.membership-requests.approve');
        Route::post('/clubs/{club}/membership-requests/{membershipRequest}/decline', [ClubController::class, 'declineMembershipRequest'])->name('clubs.membership-requests.decline');
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
            abort_unless($membershipRequest->type === 'membership', 422, 'Nur Mitgliedschaftsanfragen koennen hier zurueckgezogen werden.');
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
        Route::post('/team-join-requests/{joinRequest}/approve', [TeamController::class, 'approveJoinRequestById'])->name('team-join-requests.approve');
        Route::post('/team-join-requests/{joinRequest}/decline', [TeamController::class, 'declineJoinRequestById'])->name('team-join-requests.decline');
        Route::get('/team-invitations', [TeamController::class, 'invitations'])->name('team-invitations.index');
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
        Route::patch('/uploads/{file}', [UploadController::class, 'update'])->middleware('throttle:file-update')->name('uploads.update');
        Route::delete('/uploads/{file}', [UploadController::class, 'destroy'])->middleware('throttle:file-delete')->name('uploads.destroy');
        Route::get('/files', [UploadController::class, 'workspace'])->name('files.workspace');
        Route::post('/files/upload-intents', [UploadController::class, 'uploadIntent'])->middleware('throttle:file-uploads')->name('files.upload-intents.store');
        Route::post('/files/folders', [UploadController::class, 'storeFolder'])->middleware('throttle:file-folder-create')->name('files.folders.store');
        Route::patch('/files/folders/{folder}', [UploadController::class, 'updateFolder'])->middleware('throttle:file-folder-update')->name('files.folders.update');
        Route::delete('/files/folders/{folder}', [UploadController::class, 'destroyFolder'])->middleware('throttle:file-folder-delete')->name('files.folders.destroy');

        Route::get('/chat/conversations', [ChatController::class, 'index'])->name('chat.conversations.index');
        Route::post('/chat/conversations', [ChatController::class, 'store'])->middleware('throttle:chat-messages')->name('chat.conversations.store');
        Route::get('/chat/conversations/{conversation}', [ChatController::class, 'show'])->name('chat.conversations.show');
        Route::get('/chat/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages.index');
        Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])->middleware('throttle:chat-messages')->name('chat.messages.store');
        Route::post('/chat/conversations/{conversation}/read', [ChatController::class, 'markRead'])->name('chat.conversations.read');
        Route::post('/chat/messages/{message}/reactions', [ChatController::class, 'react'])->middleware('throttle:chat-messages')->name('chat.messages.reactions.store');
        Route::delete('/chat/messages/{message}/hide', [ChatController::class, 'hideForMe'])->name('chat.messages.hide');
        Route::delete('/chat/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.messages.destroy');
        Route::post('/chat/conversations/{conversation}/typing', [ChatController::class, 'typing'])->middleware('throttle:chat-presence')->name('chat.typing');

        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
        Route::post('/events/{event}/participation', [EventController::class, 'respond'])->name('events.participation.respond');
        Route::delete('/events/{event}/participation', [EventController::class, 'leave'])->name('events.participation.leave');
        Route::put('/events/{event}/attendance', [EventController::class, 'recordAttendance'])->name('events.attendance.update');

        Route::get('/training/plans', [TrainingController::class, 'plans'])->name('training.plans.index');
        Route::post('/training/plans', [TrainingController::class, 'storePlan'])->name('training.plans.store');
        Route::get('/training/plans/{trainingPlan}', [TrainingController::class, 'showPlan'])->name('training.plans.show');
        Route::put('/training/plans/{trainingPlan}', [TrainingController::class, 'updatePlan'])->name('training.plans.update');
        Route::delete('/training/plans/{trainingPlan}', [TrainingController::class, 'destroyPlan'])->name('training.plans.destroy');
        Route::post('/training/plans/{trainingPlan}/items', [TrainingController::class, 'storePlanItem'])->name('training.plans.items.store');
        Route::put('/training/plans/{trainingPlan}/items/{trainingPlanItem}', [TrainingController::class, 'updatePlanItem'])->name('training.plans.items.update');
        Route::delete('/training/plans/{trainingPlan}/items/{trainingPlanItem}', [TrainingController::class, 'destroyPlanItem'])->name('training.plans.items.destroy');
        Route::get('/training/logs', [TrainingController::class, 'logs'])->name('training.logs.index');
        Route::get('/training/logs/{trainingLog}', [TrainingController::class, 'showLog'])->name('training.logs.show');

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
        Route::get('/commerce/orders', [CommerceController::class, 'orders'])->name('commerce.orders.index');
        Route::get('/commerce/orders/{order}', [CommerceController::class, 'showOrder'])->name('commerce.orders.show');

        Route::middleware(EnsurePlatformAdminTwoFactor::class)->group(function () {
            Route::get('/admin/commerce', [MobileAdminCommerceController::class, 'dashboard'])->name('admin.commerce.dashboard');
            Route::get('/admin/commerce/catalog', [MobileAdminCommerceController::class, 'catalog'])->name('admin.commerce.catalog');
            Route::get('/admin/commerce/export', [MobileAdminCommerceController::class, 'export'])->name('admin.commerce.export');
            Route::post('/admin/commerce/coupons', [MobileAdminCommerceController::class, 'storeCoupon'])->name('admin.commerce.coupons.store');
            Route::patch('/admin/commerce/coupons/{coupon}', [MobileAdminCommerceController::class, 'updateCoupon'])->name('admin.commerce.coupons.update');
            Route::post('/admin/commerce/addons', [MobileAdminCommerceController::class, 'storeAddon'])->name('admin.commerce.addons.store');
            Route::patch('/admin/commerce/addons/{addon}', [MobileAdminCommerceController::class, 'updateAddon'])->name('admin.commerce.addons.update');
            Route::post('/admin/commerce/tax-rates', [MobileAdminCommerceController::class, 'storeTaxRate'])->name('admin.commerce.tax-rates.store');
            Route::patch('/admin/commerce/tax-rates/{taxRate}', [MobileAdminCommerceController::class, 'updateTaxRate'])->name('admin.commerce.tax-rates.update');
            Route::post('/admin/commerce/shipping-rates', [MobileAdminCommerceController::class, 'storeShippingRate'])->name('admin.commerce.shipping-rates.store');
            Route::patch('/admin/commerce/shipping-rates/{shippingRate}', [MobileAdminCommerceController::class, 'updateShippingRate'])->name('admin.commerce.shipping-rates.update');
            Route::patch('/admin/commerce/products/{product}/status', [MobileAdminCommerceController::class, 'updateProductStatus'])->name('admin.commerce.products.status');
            Route::patch('/admin/commerce/seller-applications/{sellerApplication}', [MobileAdminCommerceController::class, 'updateSellerApplication'])->name('admin.commerce.seller-applications.update');
            Route::patch('/admin/commerce/website-requests/{websiteRequest}', [MobileAdminCommerceController::class, 'updateWebsiteRequest'])->name('admin.commerce.website-requests.update');
            Route::patch('/admin/commerce/campaigns/{campaign}/status', [MobileAdminCommerceController::class, 'updateCampaignStatus'])->name('admin.commerce.campaigns.status');
            Route::patch('/admin/commerce/orders/{order}/shipping', [MobileAdminCommerceController::class, 'updateOrderShipping'])->name('admin.commerce.orders.shipping');
            Route::post('/admin/commerce/orders/{order}/mark-paid', [MobileAdminCommerceController::class, 'markOrderPaid'])->middleware('throttle:payment-actions')->name('admin.commerce.orders.mark-paid');
            Route::post('/admin/commerce/orders/{order}/refund', [MobileAdminCommerceController::class, 'refundOrder'])->middleware('throttle:payment-actions')->name('admin.commerce.orders.refund');
            Route::get('/admin/commerce/orders/{order}/documents', [MobileAdminCommerceController::class, 'orderDocuments'])->name('admin.commerce.orders.documents');
            Route::get('/admin/commerce/orders/{order}/invoice', [MobileAdminCommerceController::class, 'downloadInvoice'])->name('admin.commerce.orders.invoice');
            Route::get('/admin/commerce/orders/{order}/credit-note', [MobileAdminCommerceController::class, 'downloadCreditNote'])->name('admin.commerce.orders.credit-note');
            Route::patch('/admin/commerce/payouts/{payout}/paid', [MobileAdminCommerceController::class, 'markPayoutPaid'])->middleware('throttle:payment-actions')->name('admin.commerce.payouts.paid');
            Route::patch('/admin/commerce/payout-profiles/{profile}', [MobileAdminCommerceController::class, 'updatePayoutProfile'])->name('admin.commerce.payout-profiles.update');
            Route::post('/admin/subscription-checkouts/{checkout}/mark-paid', [SubscriptionController::class, 'markCheckoutPaid'])->middleware('throttle:payment-actions')->name('admin.subscription-checkouts.mark-paid');
        });
    });
});
