<?php

use App\Http\Controllers\ClubController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RideController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\UserStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {

    // DASHBOARD


    Route::get('/dashboard', [DashboardController::class, 'index'])->name('auth.dashboard');

    // PROFILE
    Route::get('/users/{user}', [UserController::class, 'show'])->name('auth.users.show');
    Route::post('/users/{user}/follow', [FollowController::class, 'store'])->name('auth.users.follow');
    Route::delete('/users/{user}/follow', [FollowController::class, 'destroy'])->name('auth.users.unfollow');

    //SETTINGS
    Route::get('/settings', [UserSettingsController::class, 'index'])->name('auth.settings');
    Route::put('/settings', [UserSettingsController::class, 'update'])->name('auth.settings.update');
    Route::put('/user/status', [UserStatusController::class, 'update'])->name('auth.user.status.update');




    // CLUBS
    Route::get('/clubs', [ClubController::class, 'index'])->middleware('club');
    Route::get('/clubs/{club}', [ClubController::class, 'show'])->middleware('club');
    Route::post('/clubs', [ClubController::class, 'store']);
    Route::put('/clubs/{club}', [ClubController::class, 'update'])->middleware('club');
    Route::delete('/clubs/{club}', [ClubController::class, 'destroy'])->middleware('club');

    // TEAMS
    Route::get('/teams', [TeamController::class, 'index'])->name('auth.teams.index');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('auth.teams.show');
    Route::post('/teams', [TeamController::class, 'store'])->name('auth.teams.store');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('auth.teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('auth.teams.destroy');
    Route::post('/teams/{team}/invite', [TeamController::class, 'invite'])->name('auth.teams.invite');
    Route::post('/teams/{team}/join-requests', [TeamController::class, 'requestJoin'])->name('auth.teams.join-requests.store');
    Route::post('/team-invitations/{invitation}/accept', [TeamController::class, 'acceptInvitation'])
        ->name('auth.team-invitations.accept');
    Route::post('/team-join-requests/{joinRequest}/approve', [TeamController::class, 'approveJoinRequest'])
        ->name('auth.team-join-requests.approve');
    Route::post('/team-join-requests/{joinRequest}/decline', [TeamController::class, 'declineJoinRequest'])
        ->name('auth.team-join-requests.decline');
    Route::put('/teams/{team}/members/{user}', [TeamController::class, 'updateMember'])->name('auth.teams.members.update');
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('auth.teams.members.destroy');

    // EVENTS
    Route::get('/events', [EventController::class, 'index'])->name('auth.events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('auth.events.show');
    Route::post('/events', [EventController::class, 'store'])->name('auth.events.store');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('auth.events.update');
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

    // COMMENTS
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('auth.comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('auth.comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('auth.comments.destroy');

    // LIKES
    Route::post('/posts/{post}/like', [LikeController::class, 'togglePost'])->name('auth.posts.like');

    // CHAT
    Route::get('/conversations', [ConversationController::class, 'index'])->name('auth.conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('auth.conversations.show');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('auth.conversations.store');
    Route::post('/conversations/{conversation}/typing', [ConversationController::class, 'typing'])->name('auth.conversations.typing');

    Route::post('/messages', [MessageController::class, 'store'])->name('auth.messages.store');
    Route::post('/messages/read', [MessageController::class, 'markAsRead'])->name('auth.messages.read');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('auth.messages.destroy');
    Route::post('/messages/{message}/reactions', [MessageController::class, 'react'])->name('auth.messages.reactions.store');

    // FRIENDS
    Route::get('/friends', [FriendController::class, 'index'])->name('auth.friends.index');
    Route::post('/friends/invitations', [FriendController::class, 'store'])->name('auth.friends.invitations.store');
    Route::post('/friends/invitations/{invitation}/accept', [FriendController::class, 'accept'])->name('auth.friends.invitations.accept');
    Route::post('/friends/invitations/{invitation}/decline', [FriendController::class, 'decline'])->name('auth.friends.invitations.decline');

    // FILES
    Route::get('/files', [FileController::class, 'index'])->name('auth.files.index');
    Route::post('/files', [FileController::class, 'store'])->name('auth.files.store');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('auth.files.download');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('auth.files.destroy');

    // FOLDERS
    Route::post('/folders', [FolderController::class, 'store'])->name('auth.folders.store');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('auth.folders.destroy');

    // RIDES
    Route::get('/rides', [RideController::class, 'index'])->name('auth.rides.index');
    Route::post('/rides', [RideController::class, 'store'])->name('auth.rides.store');
    Route::post('/rides/{ride}/join', [RideController::class, 'join'])->name('auth.rides.join');
    Route::post('/rides/{ride}/leave', [RideController::class, 'leave'])->name('auth.rides.leave');
    Route::delete('/rides/{ride}', [RideController::class, 'destroy'])->name('auth.rides.destroy');

    // NOTIFICATIONS
    Route::get('/notifications', [NotificationController::class, 'index'])->name('auth.notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('auth.notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('auth.notifications.read');


});
