<?php

namespace App\Providers;

use App\Models\Club;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\File;
use App\Models\Folder;
use App\Models\Like;
use App\Models\Message;
use App\Models\Post;
use App\Models\Ride;
use App\Models\Team;
use App\Models\User;
use App\Policies\ClubPolicy;
use App\Policies\CommentPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\EventPolicy;
use App\Policies\FilePolicy;
use App\Policies\FolderPolicy;
use App\Policies\LikePolicy;
use App\Policies\MessagePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PostPolicy;
use App\Policies\RidePolicy;
use App\Policies\TeamPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Club::class => ClubPolicy::class,
        Team::class => TeamPolicy::class,
        Event::class => EventPolicy::class,
        Post::class => PostPolicy::class,
        Comment::class => CommentPolicy::class,
        Conversation::class => ConversationPolicy::class,
        File::class => FilePolicy::class,
        Folder::class => FolderPolicy::class,
        Like::class => LikePolicy::class,
        Ride::class => RidePolicy::class,
        Message::class => MessagePolicy::class,
        \App\Models\Notification::class => NotificationPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Inertia::share([
            'theme' => fn () => auth()->user()?->theme ?? 'air',

            
        ]);
    }
}
