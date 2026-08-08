<?php

use App\Http\Controllers\AccountRoleApplicationController;
use App\Http\Controllers\AdminCommerceController;
use App\Http\Controllers\AdminOutfitSubscriptionPlanController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\BlogCategoryController;
use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\ClubVerificationController;
use App\Http\Controllers\GamificationRuleController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LearningStudioController;
use App\Http\Controllers\MailCenterController;
use App\Http\Controllers\MediaGuidelineController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\OperatingContractController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductAnalyticsController;
use App\Http\Controllers\ProviderCostController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SponsorController;
use App\Http\Controllers\SportAdminController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionInvoiceController;
use App\Http\Controllers\SubscriptionPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'admin.harden',
    'verified',
    'throttle:admin-area',
])->group(function () {

    // Users
    Route::get('/admin/users', [MemberController::class, 'index'])->middleware('can:users.view')->name('users.index');
    Route::get('/admin/users/create', [MemberController::class, 'create'])->middleware('can:users.create')->name('users.create');
    Route::get('/admin/users/{user}/edit', [MemberController::class, 'edit'])->middleware('can:users.edit')->name('users.edit');
    Route::post('/admin/users', [MemberController::class, 'store'])->middleware('can:users.create')->name('users.store');
    Route::put('/admin/users/{user}', [MemberController::class, 'update'])->middleware('can:users.edit')->name('users.update');
    Route::delete('/admin/users/{user}', [MemberController::class, 'destroy'])->middleware('can:users.delete')->name('users.destroy');
    Route::post('/admin/members/{user}/inactivity-notice', [MemberController::class, 'sendInactivityNotice'])->middleware('can:system.manage')->name('admin.members.inactivity-notice');
    Route::redirect('/admin/inactive-users', '/admin/members?tab=inactivity')->middleware('can:system.manage')->name('admin.inactive-users.index');

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

    // TRAINER APPLICATIONS
    Route::get('/admin/trainer-applications', [AccountRoleApplicationController::class, 'index'])->middleware('can:system.manage')->name('admin.trainer-applications.index');
    Route::put('/admin/trainer-applications/{application}/approve', [AccountRoleApplicationController::class, 'approve'])->middleware('can:system.manage')->name('admin.trainer-applications.approve');
    Route::put('/admin/trainer-applications/{application}/reject', [AccountRoleApplicationController::class, 'reject'])->middleware('can:system.manage')->name('admin.trainer-applications.reject');

    // MODERATION
    Route::get('/admin/moderation', [ModerationController::class, 'index'])->middleware('can:system.manage')->name('admin.moderation.index');
    Route::put('/admin/moderation/flags/{flag}', [ModerationController::class, 'updateFlag'])->middleware('can:system.manage')->name('admin.moderation.flags.update');
    Route::put('/admin/moderation/reports/{report}', [ModerationController::class, 'updateReport'])->middleware('can:system.manage')->name('admin.moderation.reports.update');
    Route::put('/admin/moderation/reports/{report}/appeal', [ModerationController::class, 'decideReportAppeal'])->middleware('can:system.manage')->name('admin.moderation.reports.appeal.update');

    // BLOG CMS
    Route::get('/admin/blogs', [BlogPostController::class, 'index'])->middleware('can:blog.view')->name('blogs.index');
    Route::post('/admin/blogs', [BlogPostController::class, 'store'])->middleware('can:blog.create')->name('blogs.store');
    Route::post('/admin/blogs/content-images', [BlogPostController::class, 'uploadContentImage'])->middleware('can:blog.view')->name('blogs.content-images.store');
    Route::get('/admin/blogs/{blogPost}/preview', [BlogPostController::class, 'preview'])->middleware('can:blog.view')->name('blogs.preview');
    Route::put('/admin/blogs/{blogPost}', [BlogPostController::class, 'update'])->middleware('can:blog.update')->name('blogs.update');
    Route::delete('/admin/blogs/{blogPost}', [BlogPostController::class, 'destroy'])->middleware('can:blog.delete')->name('blogs.destroy');
    Route::get('/admin/blog-categories', [BlogCategoryController::class, 'index'])->middleware('can:blog.manage')->name('blog-categories.index');
    Route::post('/admin/blog-categories', [BlogCategoryController::class, 'store'])->middleware('can:blog.manage')->name('blog-categories.store');
    Route::put('/admin/blog-categories/{blogCategory}', [BlogCategoryController::class, 'update'])->middleware('can:blog.manage')->name('blog-categories.update');
    Route::delete('/admin/blog-categories/{blogCategory}', [BlogCategoryController::class, 'destroy'])->middleware('can:blog.manage')->name('blog-categories.destroy');
    Route::get('/admin/media-guidelines', [MediaGuidelineController::class, 'index'])->middleware('can:blog.view')->name('admin.media-guidelines.index');
    Route::post('/admin/media-guidelines/visuals', [MediaGuidelineController::class, 'updateVisuals'])->middleware('can:blog.view')->name('admin.media-guidelines.visuals.update');

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
    Route::post('/admin/subscription-invoices/{subscriptionInvoice}/mark-paid', [SubscriptionInvoiceController::class, 'markPaid'])->middleware('can:subscriptions.manage')->name('admin.subscription-invoices.mark-paid');
    Route::get('/admin/subscription-invoices/{subscriptionInvoice}/download', [SubscriptionInvoiceController::class, 'download'])->middleware('can:subscriptions.manage')->name('admin.subscription-invoices.download');
    Route::get('/admin/commerce', [AdminCommerceController::class, 'index'])->middleware('can:subscriptions.manage')->name('admin.commerce.index');
    Route::post('/admin/commerce/coupons', [AdminCommerceController::class, 'storeCoupon'])->middleware('can:subscriptions.manage')->name('admin.commerce.coupons.store');
    Route::put('/admin/commerce/coupons/{coupon}', [AdminCommerceController::class, 'updateCoupon'])->middleware('can:subscriptions.manage')->name('admin.commerce.coupons.update');
    Route::post('/admin/commerce/addons', [AdminCommerceController::class, 'storeAddon'])->middleware('can:subscriptions.manage')->name('admin.commerce.addons.store');
    Route::put('/admin/commerce/addons/{addon}', [AdminCommerceController::class, 'updateAddon'])->middleware('can:subscriptions.manage')->name('admin.commerce.addons.update');
    Route::post('/admin/commerce/products', [AdminCommerceController::class, 'storeProduct'])->middleware('can:subscriptions.manage')->name('admin.commerce.products.store');
    Route::put('/admin/commerce/products/{product}', [AdminCommerceController::class, 'updateProduct'])->middleware('can:subscriptions.manage')->name('admin.commerce.products.update');
    Route::delete('/admin/commerce/products/{product}', [AdminCommerceController::class, 'destroyProduct'])->middleware('can:subscriptions.manage')->name('admin.commerce.products.destroy');
    Route::post('/admin/commerce/products/{product}/stock', [AdminCommerceController::class, 'adjustProductStock'])->middleware('can:subscriptions.manage')->name('admin.commerce.products.stock.adjust');
    Route::put('/admin/commerce/seller-applications/{sellerApplication}', [AdminCommerceController::class, 'updateSellerApplication'])->middleware('can:subscriptions.manage')->name('admin.commerce.seller-applications.update');
    Route::post('/admin/commerce/marketplace-visuals', [AdminCommerceController::class, 'updateMarketplaceVisuals'])->middleware('can:subscriptions.manage')->name('admin.commerce.marketplace-visuals.update');
    Route::put('/admin/commerce/marketplace-commissions', [AdminCommerceController::class, 'updateMarketplaceCommissions'])->middleware('can:subscriptions.manage')->name('admin.commerce.marketplace-commissions.update');
    Route::put('/admin/commerce/settings', [AdminCommerceController::class, 'updateCommerceSettings'])->middleware('can:subscriptions.manage')->name('admin.commerce.settings.update');
    Route::post('/admin/commerce/tax-rates', [AdminCommerceController::class, 'storeTaxRate'])->middleware('can:subscriptions.manage')->name('admin.commerce.tax-rates.store');
    Route::put('/admin/commerce/tax-rates/{taxRate}', [AdminCommerceController::class, 'updateTaxRate'])->middleware('can:subscriptions.manage')->name('admin.commerce.tax-rates.update');
    Route::post('/admin/commerce/shipping-rates', [AdminCommerceController::class, 'storeShippingRate'])->middleware('can:subscriptions.manage')->name('admin.commerce.shipping-rates.store');
    Route::put('/admin/commerce/shipping-rates/{shippingRate}', [AdminCommerceController::class, 'updateShippingRate'])->middleware('can:subscriptions.manage')->name('admin.commerce.shipping-rates.update');
    Route::post('/admin/commerce/campaigns', [AdminCommerceController::class, 'storeCampaign'])->middleware('can:subscriptions.manage')->name('admin.commerce.campaigns.store');
    Route::put('/admin/commerce/campaigns/{campaign}/status', [AdminCommerceController::class, 'updateCampaignStatus'])->middleware('can:subscriptions.manage')->name('admin.commerce.campaigns.status.update');
    Route::put('/admin/commerce/campaigns/{campaign}', [AdminCommerceController::class, 'updateCampaign'])->middleware('can:subscriptions.manage')->name('admin.commerce.campaigns.update');
    Route::post('/admin/commerce/orders/{order}/mark-paid', [AdminCommerceController::class, 'markOrderPaid'])->middleware('can:subscriptions.manage')->name('admin.commerce.orders.mark-paid');
    Route::put('/admin/commerce/orders/{order}/issue', [AdminCommerceController::class, 'updateOrderIssue'])->middleware('can:subscriptions.manage')->name('admin.commerce.orders.issue');
    Route::post('/admin/commerce/orders/{order}/issue/reply', [AdminCommerceController::class, 'replyOrderIssue'])->middleware('can:subscriptions.manage')->name('admin.commerce.orders.issue.reply');
    Route::put('/admin/commerce/orders/{order}/shipping', [AdminCommerceController::class, 'updateShipping'])->middleware('can:subscriptions.manage')->name('admin.commerce.orders.shipping');
    Route::post('/admin/commerce/orders/{order}/refund', [AdminCommerceController::class, 'refundOrder'])->middleware('can:subscriptions.manage')->name('admin.commerce.orders.refund');
    Route::get('/admin/commerce/orders/{order}/invoice', [AdminCommerceController::class, 'downloadInvoice'])->middleware('can:subscriptions.manage')->name('admin.commerce.orders.invoice');
    Route::get('/admin/commerce/orders/{order}/credit-note', [AdminCommerceController::class, 'downloadCreditNote'])->middleware('can:subscriptions.manage')->name('admin.commerce.orders.credit-note');
    Route::get('/admin/commerce/export.csv', [AdminCommerceController::class, 'exportCsv'])->middleware('can:subscriptions.manage')->name('admin.commerce.export.csv');
    Route::put('/admin/learning/courses/{course}/quality', [LearningStudioController::class, 'updateQuality'])->middleware('can:subscriptions.manage')->name('admin.learning.courses.quality.update');
    Route::put('/admin/commerce/returns/{returnRequest}', [AdminCommerceController::class, 'updateReturnRequest'])->middleware('can:subscriptions.manage')->name('admin.commerce.returns.update');
    Route::put('/admin/commerce/website-requests/{websiteRequest}', [AdminCommerceController::class, 'updateWebsiteRequest'])->middleware('can:subscriptions.manage')->name('admin.commerce.website-requests.update');
    Route::post('/admin/commerce/payouts/{user}', [AdminCommerceController::class, 'createPayout'])->middleware('can:subscriptions.manage')->name('admin.commerce.payouts.create');
    Route::put('/admin/commerce/payouts/{payout}/paid', [AdminCommerceController::class, 'markPayoutPaid'])->middleware('can:subscriptions.manage')->name('admin.commerce.payouts.paid');
    Route::put('/admin/commerce/payout-profiles/{profile}', [AdminCommerceController::class, 'updatePayoutProfile'])->middleware('can:subscriptions.manage')->name('admin.commerce.payout-profiles.update');

    // SPORT CLOTHING SUBSCRIPTIONS
    Route::get('/admin/outfit-subscriptions', [AdminOutfitSubscriptionPlanController::class, 'index'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscriptions.index');
    Route::post('/admin/outfit-subscriptions/{subscription}/mark-paid', [AdminOutfitSubscriptionPlanController::class, 'markSubscriptionPaid'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscriptions.mark-paid');
    Route::post('/admin/outfit-subscriptions/{subscription}/mark-unpaid', [AdminOutfitSubscriptionPlanController::class, 'markSubscriptionUnpaid'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscriptions.mark-unpaid');
    Route::put('/admin/outfit-subscriptions/{subscription}/shipping-address', [AdminOutfitSubscriptionPlanController::class, 'updateShippingAddress'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscriptions.shipping-address.update');
    Route::post('/admin/outfit-subscriptions/{subscription}/payment-reminder', [AdminOutfitSubscriptionPlanController::class, 'remindPayment'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscriptions.payment-reminder');
    Route::post('/admin/outfit-subscriptions/{subscription}/cancel', [AdminOutfitSubscriptionPlanController::class, 'cancelSubscription'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscriptions.cancel');
    Route::delete('/admin/outfit-subscriptions/{subscription}', [AdminOutfitSubscriptionPlanController::class, 'destroySubscription'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscriptions.destroy');
    Route::post('/admin/outfit-subscriptions/visuals', [AdminOutfitSubscriptionPlanController::class, 'updateVisuals'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscriptions.visuals.update');
    Route::put('/admin/outfit-deliveries/{delivery}', [AdminOutfitSubscriptionPlanController::class, 'updateDelivery'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-deliveries.update');
    Route::put('/admin/outfit-deliveries/{delivery}/issue', [AdminOutfitSubscriptionPlanController::class, 'updateDeliveryIssue'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-deliveries.issue.update');
    Route::post('/admin/outfit-deliveries/{delivery}/shipped', [AdminOutfitSubscriptionPlanController::class, 'markDeliveryShipped'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-deliveries.shipped');
    Route::post('/admin/outfit-deliveries/{delivery}/delivered', [AdminOutfitSubscriptionPlanController::class, 'markDeliveryDelivered'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-deliveries.delivered');
    Route::delete('/admin/outfit-deliveries/{delivery}', [AdminOutfitSubscriptionPlanController::class, 'destroyDelivery'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-deliveries.destroy');
    Route::post('/admin/outfit-subscription-plans', [AdminOutfitSubscriptionPlanController::class, 'store'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscription-plans.store');
    Route::put('/admin/outfit-subscription-plans/{plan}', [AdminOutfitSubscriptionPlanController::class, 'update'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscription-plans.update');
    Route::delete('/admin/outfit-subscription-plans/{plan}', [AdminOutfitSubscriptionPlanController::class, 'destroy'])->middleware('can:outfit-subscriptions.manage')->name('admin.outfit-subscription-plans.destroy');

    // INVOICES
    Route::get('/admin/invoices', [InvoiceController::class, 'index'])->middleware('can:billing.manage')->name('invoices.index');
    Route::post('/admin/invoices', [InvoiceController::class, 'store'])->middleware('can:billing.manage')->name('invoices.store');
    Route::put('/admin/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->middleware('can:billing.manage')->name('invoices.status.update');
    Route::delete('/admin/invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('can:billing.manage')->name('invoices.destroy');

    // SPONSORS
    Route::get('/admin/sponsors', [SponsorController::class, 'index'])->name('sponsors.index');
    Route::post('/admin/sponsors', [SponsorController::class, 'store'])->name('sponsors.store');
    Route::put('/admin/sponsors/{sponsor}', [SponsorController::class, 'update'])->name('sponsors.update');
    Route::delete('/admin/sponsors/{sponsor}', [SponsorController::class, 'destroy'])->name('sponsors.destroy');

    // OPERATING CONTRACTS
    Route::get('/admin/operating-contracts', [OperatingContractController::class, 'index'])->name('admin.operating-contracts.index');
    Route::post('/admin/operating-contracts', [OperatingContractController::class, 'store'])->name('admin.operating-contracts.store');
    Route::put('/admin/operating-contracts/{operatingContract}', [OperatingContractController::class, 'update'])->name('admin.operating-contracts.update');
    Route::delete('/admin/operating-contracts/{operatingContract}', [OperatingContractController::class, 'destroy'])->name('admin.operating-contracts.destroy');

    // SETTINGS
    Route::get('/admin/mail-center', [MailCenterController::class, 'index'])->middleware('can:system.manage')->name('admin.mail-center.index');
    Route::get('/admin/provider-costs', [ProviderCostController::class, 'index'])->middleware('can:system.manage')->name('admin.provider-costs.index');
    Route::get('/admin/product-analytics', [ProductAnalyticsController::class, 'index'])->middleware('can:analytics.view')->name('admin.product-analytics.index');
    Route::put('/admin/mail-center/preferences', [MailCenterController::class, 'updatePreferences'])->middleware('can:system.manage')->name('admin.mail-center.preferences.update');
    Route::put('/admin/mail-center/senders/{category}', [MailCenterController::class, 'updateSender'])->middleware('can:system.manage')->name('admin.mail-center.senders.update');
    Route::post('/admin/mail-center/senders/{category}/test', [MailCenterController::class, 'testSender'])->middleware('can:system.manage')->name('admin.mail-center.senders.test');
    Route::post('/admin/mail-center/{mailDelivery}/resend', [MailCenterController::class, 'resend'])->middleware('can:system.manage')->name('admin.mail-center.resend');
    Route::put('/admin/mail-center/{mailDelivery}/resolve', [MailCenterController::class, 'resolve'])->middleware('can:system.manage')->name('admin.mail-center.resolve');
    Route::get('/admin/settings', [SettingController::class, 'index'])->middleware('can:system.manage')->name('admin.settings.index');
    Route::put('/admin/settings', [SettingController::class, 'update'])->middleware('can:system.manage')->name('admin.settings.update');

});
