<?php

use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubMembershipController;
use App\Http\Controllers\CommerceCheckoutController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContentReportController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationJobController;
use App\Http\Controllers\OutfitSubscriptionController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostHelpfulController;
use App\Http\Controllers\ProfileCompletionController;
use App\Http\Controllers\ProfileGamificationController;
use App\Http\Controllers\RideController;
use App\Http\Controllers\RoleWorkspaceController;
use App\Http\Controllers\SportIntegrationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\SubscriptionInvoiceController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserBadgeController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\UserStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {

    // DASHBOARD

    Route::get('/profile-completion', [ProfileCompletionController::class, 'edit'])->name('auth.profile-completion.edit');
    Route::put('/profile-completion', [ProfileCompletionController::class, 'update'])->name('auth.profile-completion.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('auth.dashboard');
    Route::get('/workspaces', [RoleWorkspaceController::class, 'index'])->name('auth.workspaces.index');

    // PROFILE
    Route::get('/users/{user}', [UserController::class, 'show'])->name('auth.users.show');
    Route::post('/users/{user}/follow', [FollowController::class, 'store'])->name('auth.users.follow');
    Route::delete('/users/{user}/follow', [FollowController::class, 'destroy'])->name('auth.users.unfollow');
    Route::post('/users/{user}/block', [UserController::class, 'block'])->name('auth.users.block');
    Route::delete('/users/{user}/block', [UserController::class, 'unblock'])->name('auth.users.unblock');
    Route::post('/profile/sports', [ProfileGamificationController::class, 'storeSport'])->name('auth.profile.sports.store');
    Route::put('/profile/skills/{userSportSkill}', [ProfileGamificationController::class, 'updateSkill'])->name('auth.profile.skills.update');
    Route::post('/users/{user}/skills/{userSportSkill}/endorse', [ProfileGamificationController::class, 'endorse'])->name('auth.users.skills.endorse');
    Route::post('/users/{user}/recommendations', [ProfileGamificationController::class, 'recommend'])->name('auth.users.recommendations.store');
    Route::put('/profile/recommendations/{profileRecommendation}/approve', [ProfileGamificationController::class, 'approveRecommendation'])->name('auth.profile.recommendations.approve');
    Route::put('/profile/recommendations/{profileRecommendation}/reject', [ProfileGamificationController::class, 'rejectRecommendation'])->name('auth.profile.recommendations.reject');

    //SETTINGS
    Route::get('/settings', [UserSettingsController::class, 'index'])->name('auth.settings');
    Route::put('/settings', [UserSettingsController::class, 'update'])->name('auth.settings.update');
    Route::get('/badges', [UserBadgeController::class, 'index'])->name('auth.badges.index');
    Route::get('/badges/{userBadge}', [UserBadgeController::class, 'show'])->name('auth.badges.show');
    Route::get('/subscription-invoices/{subscriptionInvoice}/download', [SubscriptionInvoiceController::class, 'download'])->name('auth.subscription-invoices.download');
    Route::post('/user-subscriptions/{subscription}/cancel', [SubscriptionPlanController::class, 'cancelOwnUserSubscription'])->name('auth.user-subscriptions.cancel');
    Route::post('/user-subscriptions/{subscription}/provider-portal', [SubscriptionPlanController::class, 'providerPortal'])->name('auth.user-subscriptions.provider-portal');
    Route::put('/user/status', [UserStatusController::class, 'update'])->name('auth.user.status.update');
    Route::get('/commerce', [CommerceCheckoutController::class, 'index'])->name('auth.commerce.index');
    Route::get('/commerce/products/{product}', [CommerceCheckoutController::class, 'showProduct'])->name('auth.commerce.products.show');
    Route::post('/commerce/addons/{addon}', [CommerceCheckoutController::class, 'storeAddon'])->name('auth.commerce.addons.checkout');
    Route::post('/commerce/products/{product}', [CommerceCheckoutController::class, 'storeProduct'])->name('auth.commerce.products.checkout');
    Route::post('/commerce/cart/items/{product}', [CommerceCheckoutController::class, 'addCartItem'])->name('auth.commerce.cart.items.store');
    Route::put('/commerce/cart/items/{item}', [CommerceCheckoutController::class, 'updateCartItem'])->name('auth.commerce.cart.items.update');
    Route::delete('/commerce/cart/items/{item}', [CommerceCheckoutController::class, 'removeCartItem'])->name('auth.commerce.cart.items.destroy');
    Route::post('/commerce/cart/checkout', [CommerceCheckoutController::class, 'checkoutCart'])->name('auth.commerce.cart.checkout');
    Route::post('/commerce/orders/{order}/issue', [CommerceCheckoutController::class, 'reportOrderIssue'])->name('auth.commerce.orders.issue');
    Route::post('/commerce/orders/{order}/returns', [CommerceCheckoutController::class, 'requestReturn'])->name('auth.commerce.orders.returns.store');
    Route::get('/commerce/orders/{order}/invoice', [CommerceCheckoutController::class, 'downloadInvoice'])->name('auth.commerce.orders.invoice');
    Route::get('/commerce/orders/{order}/credit-note', [CommerceCheckoutController::class, 'downloadCreditNote'])->name('auth.commerce.orders.credit-note');
    Route::post('/commerce/products', [CommerceCheckoutController::class, 'storeOwnProduct'])->name('auth.commerce.products.store');
    Route::post('/commerce/campaigns', [CommerceCheckoutController::class, 'storeOwnCampaign'])->name('auth.commerce.campaigns.store');
    Route::post('/commerce/website-requests', [CommerceCheckoutController::class, 'storeWebsiteRequest'])->name('auth.commerce.website-requests.store');
    Route::post('/commerce/payout-profile', [CommerceCheckoutController::class, 'storePayoutProfile'])->name('auth.commerce.payout-profile.store');
    Route::get('/outfit-subscriptions', [OutfitSubscriptionController::class, 'index'])->name('auth.outfit-subscriptions.index');
    Route::put('/outfit-subscriptions/style-profile', [OutfitSubscriptionController::class, 'updateProfile'])->name('auth.outfit-subscriptions.profile.update');
    Route::post('/outfit-subscriptions/plans/{plan}', [OutfitSubscriptionController::class, 'store'])->name('auth.outfit-subscriptions.store');
    Route::post('/outfit-subscriptions/{subscription}/pause', [OutfitSubscriptionController::class, 'pause'])->name('auth.outfit-subscriptions.pause');
    Route::post('/outfit-subscriptions/{subscription}/resume', [OutfitSubscriptionController::class, 'resume'])->name('auth.outfit-subscriptions.resume');
    Route::post('/outfit-subscriptions/{subscription}/cancel', [OutfitSubscriptionController::class, 'cancel'])->name('auth.outfit-subscriptions.cancel');
    Route::get('/settings/sport-integrations/{provider}/connect', [SportIntegrationController::class, 'redirect'])
        ->name('auth.sport-integrations.connect');
    Route::get('/settings/sport-integrations/{provider}/callback', [SportIntegrationController::class, 'callback'])
        ->name('auth.sport-integrations.callback');
    Route::post('/settings/sport-integrations/{account}/sync', [SportIntegrationController::class, 'sync'])
        ->name('auth.sport-integrations.sync');
    Route::delete('/settings/sport-integrations/{account}', [SportIntegrationController::class, 'destroy'])
        ->name('auth.sport-integrations.destroy');
    Route::get('/search', GlobalSearchController::class)->name('auth.search');




    // CLUBS
    Route::get('/clubs', [ClubController::class, 'index'])->middleware('club');
    Route::get('/clubs/{club}', [ClubController::class, 'show'])->name('auth.clubs.show');
    Route::post('/clubs', [ClubController::class, 'store']);
    Route::put('/clubs/{club}', [ClubController::class, 'update'])->middleware('club')->name('auth.clubs.update');
    Route::put('/clubs/{club}/members/{user}', [ClubController::class, 'updateMember'])->name('auth.clubs.members.update');
    Route::post('/clubs/{club}/images', [ClubController::class, 'updateImages'])->name('auth.clubs.images.update');
    Route::post('/clubs/{club}/jobs', [OrganizationJobController::class, 'store'])->name('auth.clubs.jobs.store');
    Route::delete('/clubs/{club}', [ClubController::class, 'destroy'])->middleware('club')->name('auth.clubs.destroy');
    Route::put('/organization-jobs/{organizationJob}', [OrganizationJobController::class, 'update'])->name('auth.organization-jobs.update');
    Route::delete('/organization-jobs/{organizationJob}', [OrganizationJobController::class, 'destroy'])->name('auth.organization-jobs.destroy');
    Route::get('/club-memberships', [ClubMembershipController::class, 'index'])->name('auth.club-memberships.index');
    Route::get('/club-memberships/import-template', [ClubMembershipController::class, 'downloadImportTemplate'])->name('auth.club-memberships.import-template');
    Route::post('/clubs/{club}/membership/email-members', [ClubMembershipController::class, 'storeEmailMember'])->name('auth.club-memberships.email-members.store');
    Route::post('/clubs/{club}/membership/email-members/import', [ClubMembershipController::class, 'importEmailMembers'])->name('auth.club-memberships.email-members.import');
    Route::put('/clubs/{club}/membership/sepa-settings', [ClubMembershipController::class, 'updateSepaSettings'])->name('auth.club-memberships.sepa-settings.update');
    Route::put('/clubs/{club}/membership/settings', [ClubMembershipController::class, 'updateMembershipSettings'])->name('auth.club-memberships.settings.update');
    Route::post('/clubs/{club}/membership/types', [ClubMembershipController::class, 'storeMembershipType'])->name('auth.club-memberships.types.store');
    Route::post('/clubs/{club}/membership/contribution-rules', [ClubMembershipController::class, 'storeContributionRule'])->name('auth.club-memberships.contribution-rules.store');
    Route::post('/clubs/{club}/membership-requests', [ClubMembershipController::class, 'storeMembershipRequest'])->name('auth.club-membership-requests.store');
    Route::post('/clubs/{club}/membership-pause-requests', [ClubMembershipController::class, 'storePauseRequest'])->name('auth.club-membership-pause-requests.store');
    Route::post('/clubs/{club}/membership/leave', [ClubMembershipController::class, 'leaveClub'])->name('auth.club-memberships.leave');
    Route::post('/clubs/{club}/membership/removal-objection', [ClubMembershipController::class, 'objectToRemoval'])->name('auth.club-memberships.removal-objection');
    Route::post('/club-membership-requests/{membershipRequest}/approve', [ClubMembershipController::class, 'approveClubRequest'])->name('auth.club-membership-requests.approve');
    Route::post('/club-membership-requests/{membershipRequest}/decline', [ClubMembershipController::class, 'declineClubRequest'])->name('auth.club-membership-requests.decline');
    Route::get('/clubs/{club}/membership/sepa-export', [ClubMembershipController::class, 'exportSepaDebit'])->name('auth.club-memberships.sepa-export');
    Route::post('/club-external-members/{externalMember}/invite', [ClubMembershipController::class, 'inviteEmailMember'])->name('auth.club-memberships.email-members.invite');
    Route::get('/club-member-invitations/token/{token}/accept', [ClubMembershipController::class, 'acceptExternalInvitation'])->name('auth.club-member-invitations.accept');
    Route::put('/clubs/{club}/membership/{user}', [ClubMembershipController::class, 'updateMember'])->name('auth.club-memberships.members.update');
    Route::delete('/clubs/{club}/membership/{user}', [ClubMembershipController::class, 'removeMember'])->name('auth.club-memberships.members.destroy');
    Route::post('/clubs/{club}/membership/{user}/member-number', [ClubMembershipController::class, 'generateMemberNumber'])->name('auth.club-memberships.members.member-number');
    Route::post('/clubs/{club}/membership/{user}/invoices', [ClubMembershipController::class, 'storeInvoice'])->name('auth.club-memberships.invoices.store');
    Route::put('/membership-invoices/{invoice}', [ClubMembershipController::class, 'updateInvoiceStatus'])->name('auth.club-memberships.invoices.update');
    Route::post('/membership-invoices/{invoice}/payments', [ClubMembershipController::class, 'recordPayment'])->name('auth.club-memberships.invoices.payments.store');
    Route::post('/membership-invoices/{invoice}/reminder', [ClubMembershipController::class, 'sendReminder'])->name('auth.club-memberships.invoices.reminder');
    Route::post('/clubs/{club}/membership/bank-transactions/import', [ClubMembershipController::class, 'importBankTransactions'])->name('auth.club-memberships.bank-transactions.import');
    Route::post('/membership-bank-transactions/{bankTransaction}/confirm', [ClubMembershipController::class, 'confirmBankTransaction'])->name('auth.club-memberships.bank-transactions.confirm');
    Route::put('/clubs/{club}/membership/datev-settings', [ClubMembershipController::class, 'updateDatevSettings'])->name('auth.club-memberships.datev-settings.update');
    Route::get('/clubs/{club}/membership/datev-export', [ClubMembershipController::class, 'exportDatev'])->name('auth.club-memberships.datev-export');

    // TEAMS
    Route::get('/teams', [TeamController::class, 'index'])->name('auth.teams.index');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('auth.teams.show');
    Route::post('/teams', [TeamController::class, 'store'])->name('auth.teams.store');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('auth.teams.update');
    Route::post('/teams/{team}/images', [TeamController::class, 'updateImages'])->name('auth.teams.images.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('auth.teams.destroy');
    Route::post('/teams/{team}/invite', [TeamController::class, 'invite'])->name('auth.teams.invite');
    Route::post('/teams/{team}/join-requests', [TeamController::class, 'requestJoin'])->name('auth.teams.join-requests.store');
    Route::post('/team-invitations/{invitation}/accept', [TeamController::class, 'acceptInvitation'])
        ->name('auth.team-invitations.accept');
    Route::post('/team-invitations/{invitation}/decline', [TeamController::class, 'declineInvitation'])
        ->name('auth.team-invitations.decline');
    Route::get('/team-invitations/token/{token}/accept', [TeamController::class, 'acceptInvitationByToken'])
        ->name('auth.team-invitations.accept-by-token');
    Route::post('/team-join-requests/{joinRequest}/approve', [TeamController::class, 'approveJoinRequest'])
        ->name('auth.team-join-requests.approve');
    Route::post('/team-join-requests/{joinRequest}/decline', [TeamController::class, 'declineJoinRequest'])
        ->name('auth.team-join-requests.decline');
    Route::put('/teams/{team}/members/{user}', [TeamController::class, 'updateMember'])->name('auth.teams.members.update');
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('auth.teams.members.destroy');

    // EVENTS
    Route::get('/events', [EventController::class, 'index'])->name('auth.events.index');
    Route::put('/events/default-filters', [EventController::class, 'saveDefaultFilters'])->name('auth.events.default-filters.update');
    Route::post('/events', [EventController::class, 'store'])->name('auth.events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('auth.events.show');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('auth.events.update');
    Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('auth.events.cancel');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('auth.events.destroy');

    // EVENT PARTICIPATION
    Route::post('/events/{event}/join', [EventController::class, 'join'])->name('auth.events.join');
    Route::post('/events/{event}/leave', [EventController::class, 'leave'])->name('auth.events.leave');
    Route::post('/events/{event}/comments', [EventController::class, 'comment'])->name('auth.events.comments.store');
    Route::get('/events/{event}/chat', [EventController::class, 'chat'])->name('auth.events.chat');

    // POSTS
    Route::get('/feed', [PostController::class, 'index'])->name('auth.feed.index');
    Route::get('/posts', [PostController::class, 'index'])->name('auth.posts.index');
    Route::post('/posts', [PostController::class, 'store'])->name('auth.posts.store');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('auth.posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('auth.posts.destroy');
    Route::post('/posts/{post}/helpful', [PostHelpfulController::class, 'toggle'])->name('auth.posts.helpful');

    // COMMENTS
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('auth.comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('auth.comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('auth.comments.destroy');

    // LIKES
    Route::post('/posts/{post}/like', [LikeController::class, 'togglePost'])->name('auth.posts.like');

    // CONTENT REPORTS
    Route::post('/reports', [ContentReportController::class, 'store'])->name('auth.reports.store');

    // CHAT
    Route::get('/conversations', [ConversationController::class, 'index'])->name('auth.conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('auth.conversations.show');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('auth.conversations.store');
    Route::delete('/conversations/{conversation}/leave', [ConversationController::class, 'leave'])->name('auth.conversations.leave');
    Route::post('/conversations/{conversation}/members', [ConversationController::class, 'addMembers'])->name('auth.conversations.members.store');
    Route::post('/conversations/{conversation}/typing', [ConversationController::class, 'typing'])->name('auth.conversations.typing');

    Route::get('/messages', fn () => redirect()->route('auth.conversations.index'))->name('auth.messages.index');
    Route::post('/messages', [MessageController::class, 'store'])->name('auth.messages.store');
    Route::post('/messages/read', [MessageController::class, 'markAsRead'])->name('auth.messages.read');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('auth.messages.destroy');
    Route::post('/messages/{message}/reactions', [MessageController::class, 'react'])->name('auth.messages.reactions.store');

    // FRIENDS
    Route::get('/friends', [FriendController::class, 'index'])->name('auth.friends.index');
    Route::post('/friends/invitations', [FriendController::class, 'store'])->name('auth.friends.invitations.store');
    Route::post('/friends/invitations/{invitation}/accept', [FriendController::class, 'accept'])->name('auth.friends.invitations.accept');
    Route::post('/friends/invitations/{invitation}/decline', [FriendController::class, 'decline'])->name('auth.friends.invitations.decline');
    Route::get('/friends/invitations/token/{token}/accept', [FriendController::class, 'acceptByToken'])->name('auth.friends.invitations.accept-by-token');
    Route::delete('/friends/{user}', [FriendController::class, 'destroy'])->name('auth.friends.destroy');

    // FILES
    Route::get('/files', [FileController::class, 'index'])->name('auth.files.index');
    Route::post('/files', [FileController::class, 'store'])->name('auth.files.store');
    Route::post('/files/{file}/share', [FileController::class, 'share'])->name('auth.files.share');
    Route::put('/files/{file}', [FileController::class, 'update'])->name('auth.files.update');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('auth.files.download');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('auth.files.destroy');

    // FOLDERS
    Route::post('/folders', [FolderController::class, 'store'])->name('auth.folders.store');
    Route::post('/folders/{folder}/share', [FolderController::class, 'share'])->name('auth.folders.share');
    Route::put('/folders/{folder}', [FolderController::class, 'update'])->name('auth.folders.update');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('auth.folders.destroy');

    // RIDES
    Route::get('/rides', [RideController::class, 'index'])->name('auth.rides.index');
    Route::post('/rides', [RideController::class, 'store'])->name('auth.rides.store');
    Route::put('/rides/{ride}', [RideController::class, 'update'])->name('auth.rides.update');
    Route::post('/rides/{ride}/join', [RideController::class, 'join'])->name('auth.rides.join');
    Route::post('/rides/{ride}/requests/{user}/approve', [RideController::class, 'approveRequest'])->name('auth.rides.requests.approve');
    Route::post('/rides/{ride}/requests/{user}/reject', [RideController::class, 'rejectRequest'])->name('auth.rides.requests.reject');
    Route::delete('/rides/{ride}/members/{user}', [RideController::class, 'removeMember'])->name('auth.rides.members.destroy');
    Route::post('/rides/{ride}/leave', [RideController::class, 'leave'])->name('auth.rides.leave');
    Route::delete('/rides/{ride}', [RideController::class, 'destroy'])->name('auth.rides.destroy');

    // NOTIFICATIONS
    Route::get('/notifications', [NotificationController::class, 'index'])->name('auth.notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('auth.notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('auth.notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('auth.notifications.destroy');


});
