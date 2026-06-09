<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ClubController;
use App\Http\Controllers\Api\V1\CommentController as MobileCommentController;
use App\Http\Controllers\Api\V1\CommerceController;
use App\Http\Controllers\Api\V1\AdminCommerceController as MobileAdminCommerceController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\MaturityController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MobileMetaController;
use App\Http\Controllers\Api\V1\NutritionController;
use App\Http\Controllers\Api\V1\NotificationController as MobileNotificationController;
use App\Http\Controllers\Api\V1\PostImageUploadController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StoryController as MobileStoryController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SportMapController as MobileSportMapController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TrainingController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\ContentReportController;
use App\Http\Controllers\GlobalSearchController;
use App\Models\Post;
use App\Models\Sport;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->name('api.v1.')->group(function () {
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/me', [MeController::class, 'show'])->name('me.show');
        Route::patch('/me/language', [MeController::class, 'updateLanguage'])->name('me.language');

        Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
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

        Route::get('/notifications', [MobileNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [MobileNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [MobileNotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::delete('/notifications/{notification}', [MobileNotificationController::class, 'destroy'])->name('notifications.destroy');

        Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');
        Route::post('/post-images', PostImageUploadController::class)->name('post-images.store');
        Route::post('/feed', [FeedController::class, 'store'])->name('feed.store');
        Route::put('/posts/{post}', [FeedController::class, 'update'])->name('posts.update');
        Route::post('/posts/{post}', [FeedController::class, 'update'])->name('posts.update.multipart');
        Route::get('/posts/{post}/comments', [MobileCommentController::class, 'index'])->name('posts.comments.index');
        Route::post('/posts/{post}/comments', [MobileCommentController::class, 'store'])->name('posts.comments.store');
        Route::post('/posts/{post}/like', [FeedController::class, 'toggleLike'])->name('posts.like');
        Route::post('/posts/{post}/helpful', [FeedController::class, 'toggleHelpful'])->name('posts.helpful');
        Route::post('/posts/{post}/delete', [FeedController::class, 'destroy'])->name('posts.destroy.post');
        Route::delete('/posts/{post}', [FeedController::class, 'destroy'])->name('posts.destroy');
        Route::put('/comments/{comment}', [MobileCommentController::class, 'update'])->name('comments.update');
        Route::delete('/comments/{comment}', [MobileCommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('/reports', [ContentReportController::class, 'store'])->name('reports.store');
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
        Route::get('/clubs/{club}', [ClubController::class, 'show'])->name('clubs.show');
        Route::get('/clubs/{club}/members', [ClubController::class, 'members'])->name('clubs.members.index');
        Route::get('/clubs/{club}/billing', [ClubController::class, 'billing'])->name('clubs.billing');
        Route::get('/clubs/{club}/membership-requests', [ClubController::class, 'membershipRequests'])->name('clubs.membership-requests.index');
        Route::post('/clubs/{club}/membership-requests', [ClubController::class, 'storeMembershipRequest'])->name('clubs.membership-requests.store');
        Route::delete('/clubs/{club}/membership-requests', [ClubController::class, 'withdrawMembershipRequest'])->name('clubs.membership-requests.withdraw');
        Route::post('/clubs/{club}/membership-requests/{membershipRequest}/approve', [ClubController::class, 'approveMembershipRequest'])->name('clubs.membership-requests.approve');
        Route::post('/clubs/{club}/membership-requests/{membershipRequest}/decline', [ClubController::class, 'declineMembershipRequest'])->name('clubs.membership-requests.decline');
        Route::post('/clubs/{club}/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancelClubSubscription'])->name('clubs.subscriptions.cancel');
        Route::post('/clubs/{club}/subscriptions/{subscription}/renew', [SubscriptionController::class, 'renewClubSubscription'])->name('clubs.subscriptions.renew');

        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');

        Route::get('/uploads', [UploadController::class, 'index'])->name('uploads.index');
        Route::post('/uploads', [UploadController::class, 'store'])->name('uploads.store');
        Route::patch('/uploads/{file}', [UploadController::class, 'update'])->name('uploads.update');
        Route::delete('/uploads/{file}', [UploadController::class, 'destroy'])->name('uploads.destroy');

        Route::get('/chat/conversations', [ChatController::class, 'index'])->name('chat.conversations.index');
        Route::get('/chat/conversations/{conversation}', [ChatController::class, 'show'])->name('chat.conversations.show');
        Route::get('/chat/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages.index');
        Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])->name('chat.messages.store');
        Route::post('/chat/conversations/{conversation}/typing', [ChatController::class, 'typing'])->name('chat.typing');

        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::post('/events/{event}/participation', [EventController::class, 'respond'])->name('events.participation.respond');
        Route::delete('/events/{event}/participation', [EventController::class, 'leave'])->name('events.participation.leave');

        Route::get('/training/plans', [TrainingController::class, 'plans'])->name('training.plans.index');
        Route::get('/training/plans/{trainingPlan}', [TrainingController::class, 'showPlan'])->name('training.plans.show');
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
        Route::post('/subscription-plans/{subscriptionPlan}/checkout', [SubscriptionController::class, 'startCheckout'])->name('subscription-plans.checkout');
        Route::get('/subscription-checkouts/{checkout}', [SubscriptionController::class, 'checkout'])->name('subscription-checkouts.show');
        Route::post('/subscription-checkouts/{checkout}/cancel', [SubscriptionController::class, 'cancelCheckout'])->name('subscription-checkouts.cancel');
        Route::post('/subscriptions/user/{subscription}/cancel', [SubscriptionController::class, 'cancelUserSubscription'])->name('subscriptions.user.cancel');
        Route::post('/subscriptions/user/{subscription}/renew', [SubscriptionController::class, 'renewUserSubscription'])->name('subscriptions.user.renew');

        Route::get('/commerce/products', [CommerceController::class, 'products'])->name('commerce.products.index');
        Route::get('/commerce/products/{product}', [CommerceController::class, 'showProduct'])->name('commerce.products.show');
        Route::get('/commerce/orders', [CommerceController::class, 'orders'])->name('commerce.orders.index');
        Route::get('/commerce/orders/{order}', [CommerceController::class, 'showOrder'])->name('commerce.orders.show');

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
        Route::post('/admin/commerce/orders/{order}/mark-paid', [MobileAdminCommerceController::class, 'markOrderPaid'])->name('admin.commerce.orders.mark-paid');
        Route::post('/admin/commerce/orders/{order}/refund', [MobileAdminCommerceController::class, 'refundOrder'])->name('admin.commerce.orders.refund');
        Route::get('/admin/commerce/orders/{order}/documents', [MobileAdminCommerceController::class, 'orderDocuments'])->name('admin.commerce.orders.documents');
        Route::get('/admin/commerce/orders/{order}/invoice', [MobileAdminCommerceController::class, 'downloadInvoice'])->name('admin.commerce.orders.invoice');
        Route::get('/admin/commerce/orders/{order}/credit-note', [MobileAdminCommerceController::class, 'downloadCreditNote'])->name('admin.commerce.orders.credit-note');
        Route::patch('/admin/commerce/payouts/{payout}/paid', [MobileAdminCommerceController::class, 'markPayoutPaid'])->name('admin.commerce.payouts.paid');
        Route::patch('/admin/commerce/payout-profiles/{profile}', [MobileAdminCommerceController::class, 'updatePayoutProfile'])->name('admin.commerce.payout-profiles.update');
        Route::post('/admin/subscription-checkouts/{checkout}/mark-paid', [SubscriptionController::class, 'markCheckoutPaid'])->name('admin.subscription-checkouts.mark-paid');
    });
});
