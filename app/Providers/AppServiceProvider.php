<?php

namespace App\Providers;

use App\Models\Club;
use App\Models\Comment;
use App\Models\Event;
use App\Models\File;
use App\Models\Message;
use App\Models\Post;
use App\Models\Ride;
use App\Models\Team;
use App\Policies\ClubPolicy;
use App\Policies\CommentPolicy;
use App\Policies\EventPolicy;
use App\Policies\FilePolicy;
use App\Policies\MessagePolicy;
use App\Policies\PostPolicy;
use App\Policies\RidePolicy;
use App\Policies\TeamPolicy;
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
        File::class => FilePolicy::class,
        Ride::class => RidePolicy::class,
        Message::class => MessagePolicy::class,
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
        Inertia::share([
            'theme' => fn () => auth()->user()?->theme ?? 'air',
        ]);
    }


}
