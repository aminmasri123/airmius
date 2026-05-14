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
use App\Notifications\LoginLockoutNotification;
use App\Notifications\LoginSuccessfulNotification;
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
use App\Support\Roles;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;
use Throwable;

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
        Gate::before(function (User $user, string $ability) {
            return $user->hasAnyRole(Roles::FULL_ACCESS) ? true : null;
        });

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        EventFacade::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });

        EventFacade::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $request = request();
            $ipAddress = $request->ip();
            $userAgent = substr((string) $request->userAgent(), 0, 500);
            $notificationKey = $this->successfulLoginNotificationKey($event->user, $ipAddress, $userAgent);

            if (Cache::add($notificationKey, true, now()->addDay())) {
                try {
                    $event->user->notify(new LoginSuccessfulNotification(
                        $ipAddress,
                        $userAgent,
                        now()->format('d.m.Y H:i')
                    ));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            Cache::forget($this->failedLoginNotificationKey($event->user->email, $ipAddress));
        });

        EventFacade::listen(Failed::class, function (Failed $event): void {
            $request = request();
            $email = strtolower((string) ($event->credentials['email'] ?? $request->input('email')));

            if ($email === '') {
                return;
            }

            $key = $this->failedLoginNotificationKey($email, $request->ip());
            $attempts = Cache::increment($key);

            if ($attempts === 1) {
                Cache::put($key, 1, now()->addMinutes(15));
            }

            if ($attempts !== 5) {
                return;
            }

            $user = User::where('email', $email)->first();

            if (! $user) {
                return;
            }

            try {
                $user->notify(new LoginLockoutNotification(
                    $request->ip(),
                    substr((string) $request->userAgent(), 0, 500),
                    now()->format('d.m.Y H:i')
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        Inertia::share([
            'theme' => fn () => auth()->user()?->theme ?? 'air',

            
        ]);
    }

    private function failedLoginNotificationKey(string $email, ?string $ipAddress): string
    {
        return 'login-failed-notification:'.sha1(strtolower($email).'|'.($ipAddress ?: 'unknown'));
    }

    private function successfulLoginNotificationKey(User $user, ?string $ipAddress, ?string $userAgent): string
    {
        return 'login-success-notification:'.sha1($user->id.'|'.($ipAddress ?: 'unknown').'|'.($userAgent ?: 'unknown'));
    }
}
