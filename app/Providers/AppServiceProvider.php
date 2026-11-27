<?php

namespace App\Providers;

use App\Events\DomainEventPublished;
use App\Listeners\AwardTrainingCompletionXp;
use App\Models\Club;
use App\Models\Comment;
use App\Models\CommerceOrder;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\File;
use App\Models\Folder;
use App\Models\LearningEnrollment;
use App\Models\Like;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Ride;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\LoginLockoutNotification;
use App\Support\LoginActivity;
use App\Observers\CommerceOrderObserver;
use App\Observers\LearningEnrollmentObserver;
use App\Observers\TrainingLogObserver;
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
use App\Policies\UserSubscriptionPolicy;
use App\Support\Performance\RequestPerformanceTracker;
use App\Support\ProgressiveLoginLockout;
use App\Support\Roles;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Notification::class => NotificationPolicy::class,
        User::class => UserPolicy::class,
        UserSubscription::class => UserSubscriptionPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RequestPerformanceTracker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        EventFacade::listen(\Illuminate\Mail\Events\MessageSending::class, [\App\Support\DailyMailQuota::class, 'reserve']);

        EventFacade::listen(ConnectionEstablished::class, static function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() !== 'sqlite') {
                return;
            }
            $configure = static function (\PDO $pdo): \PDO {
                $functions = [
                    'acos' => static fn ($value) => $value === null ? null : acos(max(-1.0, min(1.0, (float) $value))),
                    'cos' => static fn ($value) => $value === null ? null : cos((float) $value),
                    'sin' => static fn ($value) => $value === null ? null : sin((float) $value),
                    'radians' => static fn ($value) => $value === null ? null : deg2rad((float) $value),
                ];
                foreach ($functions as $name => $function) {
                    try {
                        $pdo->query('SELECT '.$name.'(1)');
                    } catch (\PDOException) {
                        $register = method_exists($pdo, 'createFunction') ? 'createFunction' : 'sqliteCreateFunction';
                        $pdo->{$register}($name, $function, 1);
                    }
                }

                return $pdo;
            };
            // Package discovery may resolve a connection before a database exists.
            $pdo = $event->connection->getRawPdo();
            if ($pdo instanceof \Closure) {
                $event->connection->setPdo(static fn (): \PDO => $configure($pdo()));
            } elseif ($pdo instanceof \PDO) {
                $configure($pdo);
            }
        });

        if ((bool) config('airmius_monitoring.performance.enabled', false)) {
            EventFacade::listen(QueryExecuted::class, function (QueryExecuted $query): void {
                app(RequestPerformanceTracker::class)->record($query);
            });
        }

        CommerceOrder::observe(CommerceOrderObserver::class);
        LearningEnrollment::observe(LearningEnrollmentObserver::class);
        TrainingLog::observe(TrainingLogObserver::class);

        EventFacade::listen(
            DomainEventPublished::class,
            AwardTrainingCompletionXp::class,
        );

        Gate::before(function (User $user, string $ability, array $arguments = []) {
            $file = $arguments[0] ?? null;
            if ($file instanceof File && $file->posts()->where('visibility', 'private')->where('user_id', '!=', $user->id)->exists()) {
                return false;
            }
            if ($file instanceof File && $file->posts()->where('visibility', 'friends')->get()->contains(fn ($post) => ! $user->can('view', $post))) {
                return false;
            }
            $post = $arguments[0] ?? null;
            if ($post instanceof Post && $post->visibility === 'private' && $post->user_id !== $user->id) {
                return false;
            }
            if ($post instanceof Post && $post->visibility === 'friends' && $post->user_id !== $user->id && ! $user->isFriendsWith($post->user)) {
                return false;
            }

            return $user->hasAnyRole(Roles::FULL_ACCESS) ? true : null;
        });

        Gate::define('moderation.manage', fn (User $user): bool => $user->can('system.manage')
            || $user->can('security.manage')
            || $user->can('community.moderate'));

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        RateLimiter::for('ride-geocoding', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('chat-messages', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('chat-presence', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('file-uploads', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('file-downloads', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('file-preview', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('file-shared-download', function (Request $request) {
            $token = (string) $request->route('token');

            $bucket = $token !== '' ? 'file-shared-download:'.sha1($token) : 'file-shared-download:'.$request->ip();

            return Limit::perMinute(60)->by($bucket.'|'.$request->ip());
        });

        RateLimiter::for('file-share', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('file-update', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('file-delete', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('file-folder-create', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('file-folder-update', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('file-folder-delete', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('content-comments', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('content-reports', function (Request $request) {
            return Limit::perMinute(12)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('global-search', function (Request $request) {
            return Limit::perMinute(90)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('public-content', function (Request $request) {
            return Limit::perMinute(120)->by('public:'.$request->ip());
        });

        RateLimiter::for('public-self-service', function (Request $request) {
            $target = $request->route('club')?->getKey()
                ?? $request->route('course')?->getKey()
                ?? 'global';

            return [
                Limit::perMinute(6)->by('public-self-service:'.$request->ip()),
                Limit::perMinute(20)->by('public-self-service:target:'.$target),
            ];
        });

        RateLimiter::for('public-status', function (Request $request) {
            return Limit::perMinute(30)->by('public-status:'.$request->ip());
        });

        RateLimiter::for('payment-actions', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        foreach ([
            'code' => 3,
            'review' => 3,
            'financial-export' => 6,
            'archive-confirmation' => 3,
            'request' => 5,
        ] as $action => $perMinute) {
            RateLimiter::for('club-deletion-'.$action, function (Request $request) use ($action, $perMinute) {
                return Limit::perMinute($perMinute)
                    ->by('club-deletion:'.$action.':'.($request->user()?->id ?: $request->ip()));
            });
        }

        RateLimiter::for('admin-area', function (Request $request) {
            $key = $request->user()?->id
                ? 'admin:user:'.$request->user()->id
                : 'admin:ip:'.$request->ip();

            return $request->isMethodSafe()
                ? Limit::perMinute(600)->by($key)
                : Limit::perMinute(45)->by($key);
        });

        EventFacade::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });

        EventFacade::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->forceFill([
                'last_login_at' => now(),
                'last_seen_at' => now(),
                'privacy_status' => $event->user->privacy_status === 'anonymized' ? 'anonymized' : 'active',
                'inactivity_first_warning_sent_at' => null,
                'inactivity_second_warning_sent_at' => null,
                'deletion_scheduled_at' => null,
            ])->save();

            $request = request();
            if (! $request->is('api/*')) {
                LoginActivity::record($event->user, $request, true);
            }

        });

        EventFacade::listen(Failed::class, function (Failed $event): void {
            $request = request();
            if (! $request->routeIs('login.store', 'api.v1.auth.login')) {
                return;
            }
            $email = mb_strtolower(trim((string) ($event->credentials['email'] ?? $request->input('email'))));

            if ($email === '') {
                return;
            }

            $lockout = app(ProgressiveLoginLockout::class);
            $account = $event->user ?? User::where('email', $email)->first();
            if ($account instanceof User) {
                LoginActivity::record($account, $request, false);
            }
            if ($lockout->recordFailure($email) && $account instanceof User
                && Cache::add('login-failure-alert:'.$account->id, true, now()->addHour())) {
                try {
                    $account->notify(new LoginLockoutNotification(
                        $request->ip(),
                        substr((string) $request->userAgent(), 0, 500),
                        now()->format('d.m.Y H:i')
                    ));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
            $lockout->ensureAllowed($email);
        });

        EventFacade::listen(PasswordReset::class, function ($event): void {
            app(ProgressiveLoginLockout::class)->reset($event->user->email);
        });
        EventFacade::listen(\Laravel\Fortify\Events\TwoFactorAuthenticationFailed::class, function ($event): void {
            if ($event->user instanceof User) {
                LoginActivity::record($event->user, request(), false);
            }
        });

        foreach ([
            PasswordReset::class => 'Dein Konto wurde über die Passwortwiederherstellung wiederhergestellt.',
            \Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed::class => 'Deine Zwei-Faktor-Authentifizierung wurde bestätigt.',
            \Laravel\Fortify\Events\TwoFactorAuthenticationDisabled::class => 'Deine Zwei-Faktor-Authentifizierung wurde deaktiviert.',
            \Laravel\Fortify\Events\RecoveryCodesGenerated::class => 'Für dein Konto wurden neue Wiederherstellungscodes erstellt.',
        ] as $eventClass => $message) {
            EventFacade::listen($eventClass, fn ($event) => $event->user->notify(new \App\Notifications\AccountSecurityNotice($message)));
        }

        Inertia::share([
            'theme' => fn () => auth()->user()?->theme ?? 'air',
            'loginActivity' => fn () => request()->routeIs('profile.show') && auth()->check() ? LoginActivity::recent(auth()->user()) : [],

        ]);
    }

}
