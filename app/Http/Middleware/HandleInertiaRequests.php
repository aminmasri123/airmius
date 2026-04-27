<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $unreadNotificationsCount = $user
            ? $user->appNotifications()->where('read', false)->where('type', '!=', 'chat.message')->count()
            : 0;
        $unreadChatsCount = $user
            ? \App\Models\MessageReceipt::where('user_id', $user->id)
                ->whereNull('delivered_at')
                ->count()
            : 0;
        $latestNotifications = $user
            ? $user->appNotifications()
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

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'bio' => $user->bio,
                    'profile_visibility' => $user->profile_visibility,
                    'status' => $user->status,
                    'profile_photo_path' => $user->profile_photo_path,
                    'profile_photo_url' => $user->profile_photo_url,

                    // 🔥 HIER IST DER FIX
                    'roles' => $user->getRoleNames()->values()->all(),

                    'permissions' => $user->getAllPermissions()
                        ->pluck('name')
                        ->values()
                        ->all(),
                    'unread_notifications_count' => $unreadNotificationsCount,
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

            'locale' => $user?->language
                ?? session('locale')
                ?? app()->getLocale(),
        ]);
    }
}
