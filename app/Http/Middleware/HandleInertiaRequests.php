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
            ? $user->appNotifications()->where('read', false)->count()
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
                    'profile_photo_url' => $user->profile_photo_url,

                    // 🔥 HIER IST DER FIX
                    'roles' => $user->getRoleNames()->values()->all(),

                    'permissions' => $user->getAllPermissions()
                        ->pluck('name')
                        ->values()
                        ->all(),
                    'unread_notifications_count' => $unreadNotificationsCount,
                ] : null,
            ],

            'notificationCenter' => [
                'unread_count' => $unreadNotificationsCount,
                'latest' => $latestNotifications,
            ],

            'locale' => $user?->language
                ?? session('locale')
                ?? app()->getLocale(),
        ]);
    }
}
