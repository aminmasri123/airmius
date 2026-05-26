<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Support\ClubRoles;
use Illuminate\Support\Str;
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
            ? \App\Models\MessageReceipt::where('user_id', $user->id)
                ->whereNull('read_at')
                ->count()
            : 0;
        $pendingFriendInvitationsCount = $user
            ? $user->receivedFriendInvitations()
                ->where('status', 'pending')
                ->count()
            : 0;
        $commerceCartCount = $user
            ? (int) \App\Models\CommerceCart::query()
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
            ? app(\App\Services\PlanFeatureService::class)->userStorageSummary($user)
            : null;
        $flash = [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
            'message' => $request->session()->get('message'),
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
                    'has_social_login' => $user->socialAccounts()->exists(),


                    // 🔥 HIER IST DER FIX
                    'roles' => $user->getRoleNames()->values()->all(),

                    'permissions' => $user->getAllPermissions()
                        ->pluck('name')
                        ->values()
                        ->all(),
                    'can' => $can,
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
        $hasFullAccess = $user->hasAnyRole(\App\Support\Roles::FULL_ACCESS);

        return [
            'dashboard.view' => true,
            'workspaces.view' => true,
            'settings.view' => true,
            'notifications.view' => true,
            'friends.view' => true,
            'profile.view' => true,
            'guardians.children.view' => $user->can('guardians.children.view'),
            'guardians.children.manage' => $user->can('guardians.children.manage'),

            'feed.view' => $user->can('viewAny', \App\Models\Post::class),
            'post.create' => $user->can('create', \App\Models\Post::class),
            'post.store' => $user->can('create', \App\Models\Post::class),
            'post.update' => $user->can('post.update'),
            'post.delete' => $user->can('post.delete'),

            'clubs.view' => $user->can('viewAny', \App\Models\Club::class),
            'clubs.create' => $user->can('create', \App\Models\Club::class),
            'club.index' => $user->can('viewAny', \App\Models\Club::class),
            'club.create' => $user->can('create', \App\Models\Club::class),
            'club.store' => $user->can('create', \App\Models\Club::class),
            'club.update' => $user->can('clubs.edit') || $user->can('org.manage'),
            'club.delete' => $user->can('clubs.delete'),
            'club.jobs.manage' => $user->can('club.jobs.manage'),
            'club-memberships.view' => $user->hasAnyRole(\App\Support\Roles::FULL_ACCESS)
                || tap($user->clubs(), fn ($query) => ClubRoles::whereAny($query, ClubRoles::ELEVATED))->exists(),

            'teams.view' => $user->can('viewAny', \App\Models\Team::class),
            'teams.create' => $user->can('create', \App\Models\Team::class),
            'team.index' => $user->can('viewAny', \App\Models\Team::class),
            'team.create' => $user->can('create', \App\Models\Team::class),
            'team.store' => $user->can('create', \App\Models\Team::class),
            'team.update' => $user->can('team.update') || $user->can('teams.edit'),
            'team.delete' => $user->can('team.delete') || $user->can('teams.delete'),
            'team.invite' => $user->can('team.invite') || $user->can('teams.manage_players'),
            'team.kick' => $user->can('team.kick'),

            'events.view' => $user->can('viewAny', \App\Models\Event::class),
            'event.index' => $user->can('viewAny', \App\Models\Event::class),
            'event.create' => $user->can('create', \App\Models\Event::class),
            'event.store' => $user->can('create', \App\Models\Event::class),
            'event.update' => $user->can('event.update'),
            'event.delete' => $user->can('event.delete'),
            'event.join' => $user->can('event.join'),

            'files.view' => $user->can('viewAny', \App\Models\File::class),
            'file.index' => $user->can('viewAny', \App\Models\File::class),
            'file.upload' => $user->can('file.upload'),
            'file.store' => $user->can('file.upload'),
            'file.delete' => $user->can('file.delete'),

            'chat.view' => $user->teams()->exists() || $user->clubs()->exists(),
            'rides.view' => $user->can('viewAny', \App\Models\Ride::class),

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
            'subscriptions.view' => $user->can('subscriptions.manage') || $user->can('system.manage'),
            'outfit-subscriptions.view' => true,
            'outfit-subscriptions.manage' => $hasFullAccess || $user->can('outfit-subscriptions.manage'),
            'sponsors.view' => $user->can('finance.view') || $user->can('org.manage'),
            'system.manage' => $user->can('system.manage'),
            'admin.moderation.view' => $user->can('system.manage'),
            'admin.mail-center.view' => $user->can('system.manage'),
            'admin.settings.view' => $user->can('system.manage'),
        ];
    }

    private function loginImages(): array
    {
        $fallback = [
            '/img/login/bild1.png',
            '/img/login/bild2.png',
            '/img/login/bild3.png',
            '/img/login/bild4.png',
        ];

        $stored = \App\Models\Setting::valueFor('login_visual_slider');
        $decoded = is_string($stored) ? json_decode($stored, true) : null;
        $sources = is_array($decoded) && count(array_filter($decoded))
            ? $decoded
            : collect($fallback)
                ->map(fn (string $source, int $index) => \App\Models\Setting::valueFor('login_visual_slide_'.($index + 1), $source))
                ->all();

        return collect($sources)
            ->map(fn ($source) => trim((string) $source))
            ->filter()
            ->values()
            ->map(fn (string $source, int $index) => [
                'src' => \App\Support\UploadStorage::url($source),
                'alt' => 'Airmius Login-Slider Bild '.($index + 1),
            ])
            ->all();
    }
}
