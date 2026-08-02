<?php

namespace App\Http\Middleware;

use App\Http\Controllers\TrainerCockpitController;
use App\Models\Club;
use App\Models\CommerceCart;
use App\Models\Event;
use App\Models\File;
use App\Models\MessageReceipt;
use App\Models\Post;
use App\Models\Ride;
use App\Models\Setting;
use App\Models\Team;
use App\Services\PlanFeatureService;
use App\Support\ClubRoles;
use App\Support\NavigationModules;
use App\Support\Roles;
use App\Support\UploadStorage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        $response->headers->set('Vary', $this->appendVaryHeader($response->headers->get('Vary')));

        if ($request->headers->has('X-Inertia') || $response->headers->has('X-Inertia') || $request->is('marketplace*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');
        }

        return $response;
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    private function appendVaryHeader(?string $vary): string
    {
        $values = collect(explode(',', (string) $vary))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->push('X-Inertia')
            ->unique()
            ->values();

        return $values->implode(', ');
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $can = $user ? $this->permissionsFor($user) : [];
        $unreadNotificationsCount = $user
            ? $user->appNotifications()->where('read', false)->where('type', '!=', 'chat.message')->count()
            : 0;
        $unreadChatsCount = $user
            ? MessageReceipt::where('user_id', $user->id)
                ->whereNull('read_at')
                ->count()
            : 0;
        $pendingFriendInvitationsCount = $user
            ? $user->receivedFriendInvitations()
                ->where('status', 'pending')
                ->count()
            : 0;
        $commerceCartCount = $user
            ? (int) CommerceCart::query()
                ->where('user_id', $user->id)
                ->withSum('items as items_quantity_sum', 'quantity')
                ->first()?->items_quantity_sum
            : 0;
        $latestNotifications = $user
            ? $user->appNotifications()
                ->where('type', '!=', 'chat.message')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read' => $notification->read,
                    'created_at' => $notification->created_at,
                ])
            : [];
        $activeUserSubscriptions = $user
            ? $user->subscriptions()
                ->with('plan:id,slug,name,target_actor')
                ->whereIn('status', ['active', 'trialing'])
                ->get()
                ->filter(fn ($subscription) => $subscription->plan)
                ->groupBy(fn ($subscription) => $subscription->plan->target_actor ?: 'default')
                ->map(fn ($subscriptions) => $subscriptions->sortByDesc('id')->first())
                ->values()
            : collect();
        $currentUserSubscription = $activeUserSubscriptions
            ->sortByDesc(fn ($subscription) => $subscription->current_period_ends_at?->timestamp ?? 0)
            ->first();
        $storageUsage = $user
            ? app(PlanFeatureService::class)->userStorageSummary($user)
            : null;
        $flash = [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
            'message' => $request->session()->get('message'),
            'import_report' => $request->session()->get('import_report'),
        ];
        $flashId = $request->session()->get('flash_id');

        if (! $flashId && collect($flash)->filter(fn ($value) => filled($value))->isNotEmpty()) {
            $flashId = (string) Str::uuid();
        }

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'country' => $user->country,
                    'athlete_license_number' => $user->athlete_license_number,
                    'bio' => $user->bio,
                    'profile_visibility' => $user->profile_visibility,
                    'status' => $user->status,
                    'profile_photo_path' => $user->profile_photo_path,
                    'profile_photo_url' => $user->profile_photo_url,
                    'profile_photo_thumb' => $user->profile_photo_thumb,
                    'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                    'ads_personalization_consent' => (bool) $user->ads_personalization_consent,
                    'ads_measurement_consent' => (bool) $user->ads_measurement_consent,
                    'has_social_login' => $user->socialAccounts()->exists(),

                    // 🔥 HIER IST DER FIX
                    'roles' => $user->getRoleNames()->values()->all(),

                    'permissions' => $user->getAllPermissions()
                        ->pluck('name')
                        ->values()
                        ->all(),
                    'can' => $can,
                    'navigation_modules' => NavigationModules::payload($user),
                    'unread_notifications_count' => $unreadNotificationsCount,
                    'active_subscription_plan_ids' => $activeUserSubscriptions
                        ->pluck('subscription_plan_id')
                        ->values()
                        ->all(),
                    'active_subscription_plan_slugs' => $activeUserSubscriptions
                        ->pluck('plan.slug')
                        ->filter()
                        ->values()
                        ->all(),
                    'current_subscription' => $currentUserSubscription ? [
                        'id' => $currentUserSubscription->id,
                        'status' => $currentUserSubscription->status,
                        'current_period_ends_at' => $currentUserSubscription->current_period_ends_at,
                        'plan' => $currentUserSubscription->plan ? [
                            'id' => $currentUserSubscription->plan->id,
                            'slug' => $currentUserSubscription->plan->slug,
                            'name' => $currentUserSubscription->plan->name,
                            'target_actor' => $currentUserSubscription->plan->target_actor,
                        ] : null,
                    ] : null,
                    'storage_usage' => $storageUsage,
                    'realtime' => [
                        'notification_channel' => 'notifications.user.'.$user->id,
                        'team_event_channels' => $user->teams()
                            ->pluck('teams.id')
                            ->map(fn ($id) => 'events.team.'.$id)
                            ->values()
                            ->all(),
                        'club_event_channels' => $user->clubs()
                            ->pluck('clubs.id')
                            ->map(fn ($id) => 'events.club.'.$id)
                            ->values()
                            ->all(),
                    ],
                ] : null,
            ],

            'notificationCenter' => [
                'unread_count' => $unreadNotificationsCount,
                'latest' => $latestNotifications,
            ],

            'unreadChatsCount' => $unreadChatsCount,
            'commerceCartCount' => $commerceCartCount,
            'friendCenter' => [
                'pending_received_count' => $pendingFriendInvitationsCount,
            ],

            'privacyConsent' => [
                'ads_personalization_consent' => (bool) $user?->ads_personalization_consent,
                'ads_measurement_consent' => (bool) $user?->ads_measurement_consent,
                'source' => $user ? 'user_settings' : 'none',
            ],

            'uploads' => [
                'disk' => config('filesystems.uploads_disk'),
                'url' => config('filesystems.uploads_url'),
            ],

            'loginImages' => fn () => $this->loginImages(),

            'flash' => [
                'id' => $flashId,
                'success' => $flash['success'],
                'error' => $flash['error'],
                'message' => $flash['message'],
                'import_report' => $flash['import_report'],
            ],

            'locale' => $user?->language
                ?? session('locale')
                ?? app()->getLocale(),
            'direction' => in_array($user?->language ?? session('locale') ?? app()->getLocale(), ['ar'], true)
                ? 'rtl'
                : 'ltr',
        ]);
    }

    private function permissionsFor($user): array
    {
        $hasFullAccess = $user->hasAnyRole(Roles::FULL_ACCESS);

        return [
            'dashboard.view' => true,
            'workspaces.view' => true,
            'settings.view' => true,
            'notifications.view' => true,
            'friends.view' => true,
            'profile.view' => true,
            'guardians.children.view' => $user->can('guardians.children.view'),
            'guardians.children.manage' => $user->can('guardians.children.manage'),
            'sponsor.workspace.view' => $user->hasAnyRole(['sponsor', 'sponsor_manager'])
                || $user->can('sponsor.workspace.view')
                || $hasFullAccess
                || $user->can('system.manage'),
            'sponsor.profile.edit' => $user->can('sponsor.profile.edit')
                || $user->hasRole('sponsor'),
            'trainer-cockpit.view' => TrainerCockpitController::userCanView($user),

            'feed.view' => $user->can('viewAny', Post::class),
            'post.create' => $user->can('create', Post::class),
            'post.store' => $user->can('create', Post::class),
            'post.update' => $user->can('post.update'),
            'post.delete' => $user->can('post.delete'),

            'clubs.view' => $user->can('viewAny', Club::class),
            'clubs.create' => $user->can('create', Club::class),
            'club.index' => $user->can('viewAny', Club::class),
            'club.create' => $user->can('create', Club::class),
            'club.store' => $user->can('create', Club::class),
            'club.update' => $user->can('clubs.edit') || $user->can('org.manage'),
            'club.delete' => $user->can('clubs.delete'),
            'club.jobs.manage' => $user->can('club.jobs.manage'),
            'club-memberships.view' => $user->hasAnyRole(Roles::FULL_ACCESS)
                || tap($user->clubs(), fn ($query) => ClubRoles::whereAny($query, ClubRoles::ELEVATED))->exists(),
            'club-cockpit.view' => $user->hasAnyRole(Roles::FULL_ACCESS)
                || $user->hasAnyRole(Roles::CLUB_ADMIN)
                || $user->can('org.manage')
                || tap($user->clubs(), fn ($query) => ClubRoles::whereAny($query, ClubRoles::ELEVATED))->exists(),

            'teams.view' => $user->can('viewAny', Team::class),
            'teams.create' => $user->can('create', Team::class),
            'team.index' => $user->can('viewAny', Team::class),
            'team.create' => $user->can('create', Team::class),
            'team.store' => $user->can('create', Team::class),
            'team.update' => $user->can('team.update') || $user->can('teams.edit'),
            'team.delete' => $user->can('team.delete') || $user->can('teams.delete'),
            'team.invite' => $user->can('team.invite') || $user->can('teams.manage_players'),
            'team.kick' => $user->can('team.kick'),

            'events.view' => $user->can('viewAny', Event::class),
            'event.index' => $user->can('viewAny', Event::class),
            'event.create' => $user->can('create', Event::class),
            'event.store' => $user->can('create', Event::class),
            'event.update' => $user->can('event.update'),
            'event.delete' => $user->can('event.delete'),
            'event.join' => $user->can('event.join'),

            'files.view' => $user->can('viewAny', File::class),
            'file.index' => $user->can('viewAny', File::class),
            'file.upload' => $user->can('file.upload'),
            'file.store' => $user->can('file.upload'),
            'file.delete' => $user->can('file.delete'),

            'chat.view' => $user->teams()->exists() || $user->clubs()->exists(),
            'rides.view' => $user->can('viewAny', Ride::class),

            'users.view' => $user->can('users.view'),
            'users.create' => $user->can('users.create'),
            'users.edit' => $user->can('users.edit'),
            'users.delete' => $user->can('users.delete'),
            'roles.manage' => $user->can('users.assign_roles'),

            'blog.view' => $user->can('blog.view'),
            'blog.create' => $user->can('blog.create'),
            'blog.update' => $user->can('blog.update'),
            'blog.delete' => $user->can('blog.delete'),
            'blog.manage' => $user->can('blog.manage'),

            'payments.view' => $user->can('billing.manage'),
            'invoices.view' => $user->can('billing.manage'),
            'operating-contracts.view' => $user->can('finance.view') || $user->can('finance.edit') || $user->can('billing.manage') || $user->can('system.manage'),
            'operating-contracts.manage' => $user->can('finance.edit') || $user->can('system.manage'),
            'subscriptions.view' => $user->can('subscriptions.manage') || $user->can('system.manage'),
            'outfit-subscriptions.view' => true,
            'outfit-subscriptions.manage' => $hasFullAccess || $user->can('outfit-subscriptions.manage'),
            'sponsors.view' => $hasFullAccess
                || $user->hasRole('sponsor_manager')
                || $user->can('system.manage'),
            'system.manage' => $user->can('system.manage'),
            'admin.moderation.view' => $user->can('system.manage'),
            'admin.mail-center.view' => $user->can('system.manage'),
            'admin.settings.view' => $user->can('system.manage'),
        ];
    }

    private function loginImages(): array
    {
        $fallback = $this->defaultLoginSliderSources();
        $legacySourceMap = $this->legacyLoginSliderSourceMap();

        $stored = Setting::valueFor('login_visual_slider');
        $decoded = is_string($stored) ? json_decode($stored, true) : null;
        $sources = is_array($decoded) && count(array_filter($decoded))
            ? $decoded
            : collect($fallback)
                ->map(fn (string $source, int $index) => Setting::valueFor('login_visual_slide_'.($index + 1), $source))
                ->all();

        return collect($sources)
            ->map(fn ($source) => trim((string) $source))
            ->filter()
            ->map(fn (string $source) => $legacySourceMap[$source] ?? $source)
            ->values()
            ->map(fn (string $source, int $index) => [
                'src' => UploadStorage::url($source),
                'alt' => 'Airmius Login-Slider Bild '.($index + 1),
            ])
            ->all();
    }

    private function defaultLoginSliderSources(): array
    {
        return [
            '/img/login/airmius-auth-team-platform.png',
            '/img/login/airmius-auth-club-operations.png',
            '/img/login/airmius-auth-community-events.png',
            '/img/login/airmius-auth-marketplace-services.png',
        ];
    }

    private function legacyLoginSliderSourceMap(): array
    {
        return array_combine([
            '/img/login/bild1.png',
            '/img/login/bild2.png',
            '/img/login/bild3.png',
            '/img/login/bild4.png',
        ], $this->defaultLoginSliderSources());
    }
}
