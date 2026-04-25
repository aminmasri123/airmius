<?php

use App\Http\Controllers\ClubController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RideController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {

    // DASHBOARD


    Route::get('/dashboard', [DashboardController::class, 'index'])->name('auth.dashboard');

    // PROFILE
    Route::get('/profile', [UserController::class, 'show']);
    Route::put('/profile', [UserController::class, 'update']);
    Route::delete('/profile', [UserController::class, 'destroy']);

    //SETTINGS
    Route::get('/settings', [UserSettingsController::class, 'index'])->name('auth.settings');
    Route::put('/settings', [UserSettingsController::class, 'update'])->name('auth.settings.update');







    // CLUBS
    Route::get('/clubs', [ClubController::class, 'index'])->middleware('club');
    Route::get('/clubs/{club}', [ClubController::class, 'show'])->middleware('club');
    Route::post('/clubs', [ClubController::class, 'store'])->middleware('club');
    Route::put('/clubs/{club}', [ClubController::class, 'update'])->middleware('club');
    Route::delete('/clubs/{club}', [ClubController::class, 'destroy'])->middleware('club');

    // TEAMS
    Route::get('/teams', [TeamController::class, 'index'])->name('auth.teams.index');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('auth.teams.show');
    Route::post('/teams', [TeamController::class, 'store'])->name('auth.teams.store');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('auth.teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('auth.teams.destroy');

    // EVENTS
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{event}', [EventController::class, 'show']);
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{event}', [EventController::class, 'update']);
    Route::delete('/events/{event}', [EventController::class, 'destroy']);

    // EVENT PARTICIPATION
    Route::post('/events/{event}/join', [EventController::class, 'join']);
    Route::post('/events/{event}/leave', [EventController::class, 'leave']);

    // POSTS
    Route::get('/posts', [PostController::class, 'index']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{post}', [PostController::class, 'update']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    // COMMENTS
    Route::post('/posts/{post}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    // LIKES
    Route::post('/likes', [LikeController::class, 'toggle']);

    // CHAT
    Route::get('/conversations', [ConversationController::class, 'index'])->name('auth.conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations', [ConversationController::class, 'store']);

    Route::post('/messages', [MessageController::class, 'store']);

    // FILES
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    Route::delete('/files/{file}', [FileController::class, 'destroy']);

    // FOLDERS
    Route::post('/folders', [FolderController::class, 'store']);
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy']);

    // RIDES
    Route::get('/rides', [RideController::class, 'index']);
    Route::post('/rides', [RideController::class, 'store']);
    Route::post('/rides/{ride}/join', [RideController::class, 'join']);
    Route::post('/rides/{ride}/leave', [RideController::class, 'leave']);
    Route::delete('/rides/{ride}', [RideController::class, 'destroy']);

    // NOTIFICATIONS
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);


});
