<?php

use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\AdminCommerceController;
use App\Http\Controllers\AdminOutfitSubscriptionPlanController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\ClubVerificationController;
use App\Http\Controllers\GamificationRuleController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MediaGuidelineController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SportAdminController;
use App\Http\Controllers\SponsorController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionInvoiceController;
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
    Route::get('/admin/badges', [BadgeController::class, 'index'])->middleware('can:system.manage')->name('admin.badges.index');
    Route::post('/admin/badges', [BadgeController::class, 'store'])->middleware('can:system.manage')->name('admin.badges.store');
    Route::put('/admin/badges/{badge}', [BadgeController::class, 'update'])->middleware('can:system.manage')->name('admin.badges.update');
    Route::delete('/admin/badges/{badge}', [BadgeController::class, 'destroy'])->middleware('can:system.manage')->name('admin.badges.destroy');

    // SPORTS
    Route::get('/admin/sports', [SportAdminController::class, 'index'])->middleware('can:system.manage')->name('admin.sports.index');
    Route::post('/admin/sports', [SportAdminController::class, 'store'])->middleware('can:system.manage')->name('admin.sports.store');
    Route::put('/admin/sports/{sport}', [SportAdminController::class, 'update'])->middleware('can:system.manage')->name('admin.sports.update');
    Route::delete('/admin/sports/{sport}', [SportAdminController::class, 'destroy'])->middleware('can:system.manage')->name('admin.sports.destroy');

    // CLUB VERIFICATION
    Route::get('/admin/club-verifications', [ClubVerificationController::class, 'index'])->middleware('can:system.manage')->name('admin.club-verifications.index');
    Route::put('/admin/club-verifications/{club}/approve', [ClubVerificationController::class, 'approve'])->middleware('can:system.manage')->name('admin.club-verifications.approve');
    Route::put('/admin/club-verifications/{club}/reject', [ClubVerificationController::class, 'reject'])->middleware('can:system.manage')->name('admin.club-verifications.reject');

    // MODERATION
    Route::get('/admin/moderation', [ModerationController::class, 'index'])->middleware('can:system.manage')->name('admin.moderation.index');
    Route::put('/admin/moderation/flags/{flag}', [ModerationController::class, 'updateFlag'])->middleware('can:system.manage')->name('admin.moderation.flags.update');
    Route::put('/admin/moderation/reports/{report}', [ModerationController::class, 'updateReport'])->middleware('can:system.manage')->name('admin.moderation.reports.update');

    // BLOG CMS
    Route::get('/admin/blogs', [BlogPostController::class, 'index'])->middleware('can:blog.view')->name('blogs.index');
    Route::post('/admin/blogs', [BlogPostController::class, 'store'])->middleware('can:blog.create')->name('blogs.store');
    Route::put('/admin/blogs/{blogPost}', [BlogPostController::class, 'update'])->middleware('can:blog.update')->name('blogs.update');
    Route::delete('/admin/blogs/{blogPost}', [BlogPostController::class, 'destroy'])->middleware('can:blog.delete')->name('blogs.destroy');
    Route::get('/admin/media-guidelines', [MediaGuidelineController::class, 'index'])->middleware('can:blog.view')->name('admin.media-guidelines.index');

    // PAYMENTS
    Route::get('/admin/payments', [PaymentController::class, 'index'])->middleware('can:billing.manage')->name('payments.index');
    Route::post('/admin/payments', [PaymentController::class, 'store'])->middleware('can:billing.manage')->name('payments.store');
    Route::delete('/admin/payments/{payment}', [PaymentController::class, 'destroy'])->middleware('can:billing.manage')->name('payments.destroy');

    // SUBSCRIPTIONS
    Route::get('/admin/subscriptions', [SubscriptionPlanController::class, 'index'])->middleware('can:subscriptions.manage')->name('admin.subscriptions.index');
    Route::put('/admin/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update'])->middleware('can:subscriptions.manage')->name('admin.subscription-plans.update');
    Route::put('/admin/clubs/{club}/subscription', [SubscriptionPlanController::class, 'assignClub'])->middleware('can:subscriptions.manage')->name('admin.clubs.subscription.update');
    Route::put('/admin/users/{user}/subscription', [SubscriptionPlanController::class, 'assignUser'])->middleware('can:subscriptions.manage')->name('admin.users.subscription.update');
    Route::post('/admin/club-subscriptions/{subscription}/cancel', [SubscriptionPlanController::class, 'cancelClub'])->middleware('can:subscriptions.manage')->name('admin.club-subscriptions.cancel');
    Route::post('/admin/club-subscriptions/{subscription}/renew', [SubscriptionPlanController::class, 'renewClub'])->middleware('can:subscriptions.manage')->name('admin.club-subscriptions.renew');
    Route::post('/admin/user-subscriptions/{subscription}/cancel', [SubscriptionPlanController::class, 'cancelUser'])->middleware('can:subscriptions.manage')->name('admin.user-subscriptions.cancel');
    Route::post('/admin/user-subscriptions/{subscription}/renew', [SubscriptionPlanController::class, 'renewUser'])->middleware('can:subscriptions.manage')->name('admin.user-subscriptions.renew');
    Route::post('/admin/subscription-checkouts/{checkout}/mark-paid', [SubscriptionCheckoutController::class, 'markBankTransferPaid'])->middleware('can:subscriptions.manage')->name('admin.subscription-checkouts.mark-paid');
    Route::get('/admin/subscription-invoices', [SubscriptionInvoiceController::class, 'index'])->middleware('can:subscriptions.manage')->name('admin.subscription-invoices.index');
    Route::get('/admin/subscription-invoices/{subscriptionInvoice}/download', [SubscriptionInvoiceController::class, 'download'])->middleware('can:subscriptions.manage')->name('admin.subscription-invoices.download');
    Route::get('/admin/commerce', [AdminCommerceController::class, 'index'])->middleware('can:subscriptions.manage')->name('admin.commerce.index');
    Route::post('/admin/commerce/coupons', [AdminCommerceController::class, 'storeCoupon'])->middleware('can:subscriptions.manage')->name('admin.commerce.coupons.store');
    Route::put('/admin/commerce/coupons/{coupon}', [AdminCommerceController::class, 'updateCoupon'])->middleware('can:subscriptions.manage')->name('admin.commerce.coupons.update');
    Route::post('/admin/commerce/addons', [AdminCommerceController::class, 'storeAddon'])->middleware('can:subscriptions.manage')->name('admin.commerce.addons.store');
    Route::put('/admin/commerce/addons/{addon}', [AdminCommerceController::class, 'updateAddon'])->middleware('can:subscriptions.manage')->name('admin.commerce.addons.update');
    Route::post('/admin/commerce/products', [AdminCommerceController::class, 'storeProduct'])->middleware('can:subscriptions.manage')->name('admin.commerce.products.store');
    Route::put('/admin/commerce/products/{product}', [AdminCommerceController::class, 'updateProduct'])->middleware('can:subscriptions.manage')->name('admin.commerce.products.update');
    Route::post('/admin/commerce/products/{product}/stock', [AdminCommerceController::class, 'adjustProductStock'])->middleware('can:subscriptions.manage')->name('admin.commerce.products.stock.adjust');
    Route::post('/admin/commerce/marketplace-visuals', [AdminCommerceController::class, 'updateMarketplaceVisuals'])->middleware('can:subscriptions.manage')->name('admin.commerce.marketplace-visuals.update');
    Route::put('/admin/commerce/settings', [AdminCommerceController::class, 'updateCommerceSettings'])->middleware('can:subscriptions.manage')->name('admin.commerce.settings.update');
    Route::post('/admin/commerce/tax-rates', [AdminCommerceController::class, 'storeTaxRate'])->middleware('can:subscriptions.manage')->name('admin.commerce.tax-rates.store');
    Route::put('/admin/commerce/tax-rates/{taxRate}', [AdminCommerceController::class, 'updateTaxRate'])->middleware('can:subscriptions.manage')->name('admin.commerce.tax-rates.update');
    Route::post('/admin/commerce/shipping-rates', [AdminCommerceController::class, 'storeShippingRate'])->middleware('can:subscriptions.manage')->name('admin.commerce.shipping-rates.store');
    Route::put('/admin/commerce/shipping-rates/{shippingRate}', [AdminCommerceController::class, 'updateShippingRate'])->middleware('can:subscriptions.manage')->name('admin.commerce.shipping-rates.update');
    Route::post('/admin/commerce/campaigns', [AdminCommerceController::class, 'storeCampaign'])->middleware('can:subscriptions.manage')->name('admin.commerce.campaigns.store');
    Route::put('/admin/commerce/campaigns/{campaign}', [AdminCommerceController::class, 'updateCampaign'])->middleware('can:subscriptions.manage')->name('admin.commerce.campaigns.update');
    Route::post('/admin/commerce/orders/{order}/mark-paid', [AdminCommerceController::class, 'markOrderPaid'])->middleware('can:subscriptions.manage')->name('admin.commerce.orders.mark-paid');
    Route::put('/admin/commerce/orders/{order}/issue', [AdminCommerceController::class, 'updateOrderIssue'])->middleware('can:subscriptions.manage')->name('admin.commerce.orders.issue');
    Route::put('/admin/commerce/returns/{returnRequest}', [AdminCommerceController::class, 'updateReturnRequest'])->middleware('can:subscriptions.manage')->name('admin.commerce.returns.update');
    Route::put('/admin/commerce/website-requests/{websiteRequest}', [AdminCommerceController::class, 'updateWebsiteRequest'])->middleware('can:subscriptions.manage')->name('admin.commerce.website-requests.update');
    Route::post('/admin/commerce/payouts/{user}', [AdminCommerceController::class, 'createPayout'])->middleware('can:subscriptions.manage')->name('admin.commerce.payouts.create');
    Route::put('/admin/commerce/payouts/{payout}/paid', [AdminCommerceController::class, 'markPayoutPaid'])->middleware('can:subscriptions.manage')->name('admin.commerce.payouts.paid');
    Route::put('/admin/commerce/payout-profiles/{profile}', [AdminCommerceController::class, 'updatePayoutProfile'])->middleware('can:subscriptions.manage')->name('admin.commerce.payout-profiles.update');

    // SPORT CLOTHING SUBSCRIPTIONS
    Route::get('/admin/outfit-subscriptions', [AdminOutfitSubscriptionPlanController::class, 'index'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscriptions.index');
    Route::post('/admin/outfit-subscription-plans', [AdminOutfitSubscriptionPlanController::class, 'store'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscription-plans.store');
    Route::put('/admin/outfit-subscription-plans/{plan}', [AdminOutfitSubscriptionPlanController::class, 'update'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscription-plans.update');
    Route::delete('/admin/outfit-subscription-plans/{plan}', [AdminOutfitSubscriptionPlanController::class, 'destroy'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscription-plans.destroy');

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
