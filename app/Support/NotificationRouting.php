<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

final class NotificationRouting
{
    public const PRIORITIES = ['low', 'normal', 'high', 'critical'];

    public const CATEGORIES = ['system', 'security', 'chat', 'social', 'training', 'events', 'club', 'billing', 'marketing'];

    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return [
            'push' => false,
            'email' => true,
            'chat' => true,
            'club' => true,
            'billing' => true,
            'marketing' => false,
        ];
    }

    /** @return array<string, bool> */
    public static function preferencesFor(User $user): array
    {
        return array_replace(
            self::defaults(),
            is_array($user->notification_channels) ? $user->notification_channels : [],
        );
    }

    /** @return list<string> */
    public static function preferenceKeys(): array
    {
        return array_keys(self::defaults());
    }

    public static function categoryFor(string $type): string
    {
        $type = Str::lower($type);

        return match (true) {
            Str::startsWith($type, ['security.', 'auth.', 'privacy.']) => 'security',
            Str::startsWith($type, 'chat.') || Str::contains($type, 'message') => 'chat',
            Str::startsWith($type, ['commerce.', 'marketplace.', 'outfit.', 'invoice.', 'subscription.', 'payment.'])
                || Str::contains($type, ['billing', 'payout', 'refund']) => 'billing',
            Str::startsWith($type, ['ads.', 'marketing.', 'sponsor.', 'agency.']) => 'marketing',
            Str::startsWith($type, 'training.') => 'training',
            Str::startsWith($type, 'event.') || Str::contains($type, 'participation') => 'events',
            Str::startsWith($type, ['club.', 'team.', 'member.', 'guardian.']) => 'club',
            Str::startsWith($type, ['friend.', 'social.', 'feed.', 'post.', 'user.follow']) => 'social',
            default => 'system',
        };
    }

    public static function priorityFor(string $type): string
    {
        $type = Str::lower($type);

        return match (true) {
            Str::startsWith($type, ['security.', 'auth.security.', 'privacy.breach.']) => 'critical',
            Str::contains($type, ['payment.failed', 'payment.expired', 'dunning', 'account.suspended']) => 'high',
            Str::startsWith($type, ['chat.', 'event.reminder', 'training.']) => 'normal',
            default => 'normal',
        };
    }

    public static function normalizeCategory(?string $category, string $type): string
    {
        $category = Str::lower(trim((string) $category));

        return in_array($category, self::CATEGORIES, true) ? $category : self::categoryFor($type);
    }

    public static function normalizePriority(?string $priority, string $type): string
    {
        $priority = Str::lower(trim((string) $priority));

        return in_array($priority, self::PRIORITIES, true) ? $priority : self::priorityFor($type);
    }

    public static function topicEnabled(User $user, string $type, ?string $category = null, ?string $priority = null): bool
    {
        $priority = self::normalizePriority($priority, $type);
        if ($priority === 'critical') {
            return true;
        }

        $preferenceKey = match (self::normalizeCategory($category, $type)) {
            'chat' => 'chat',
            'billing' => 'billing',
            'marketing' => 'marketing',
            'club', 'social', 'training', 'events' => 'club',
            default => null,
        };

        if ($preferenceKey === null || ! is_array($user->notification_channels)) {
            return true;
        }

        return ! array_key_exists($preferenceKey, $user->notification_channels)
            || (bool) $user->notification_channels[$preferenceKey];
    }

    public static function transportEnabled(User $user, string $transport): bool
    {
        if (! is_array($user->notification_channels) || ! array_key_exists($transport, $user->notification_channels)) {
            return true;
        }

        return (bool) $user->notification_channels[$transport];
    }

    public static function bypassesQuietHours(string $priority, array $data = []): bool
    {
        return $priority === 'critical' || data_get($data, 'routing.bypass_quiet_hours') === true;
    }

    /** @return array<string, mixed> */
    public static function normalizeActionData(string $type, array $data): array
    {
        if (in_array($type, ['admin.ai_token.problem', 'admin.ai_token.expiring'], true)) {
            return self::withAction($data, '/admin/settings', 'airmius://admin/settings');
        }

        if (in_array($type, ['post.like', 'post.comment'], true)) {
            $postId = self::positiveIdentifier($data['post_id'] ?? null);
            if ($postId !== null) {
                return self::withAction($data, '/feed?post='.$postId, 'airmius://feed/'.$postId);
            }
        }

        if (in_array($type, ['club.membership_request_created', 'club.membership_request_withdrawn'], true)) {
            $clubId = self::positiveIdentifier($data['club_id'] ?? null);
            if ($clubId !== null) {
                return self::withAction(
                    $data,
                    '/club-memberships?tab=requests&club_id='.$clubId,
                    'airmius://clubs/'.$clubId.'/membership-requests',
                );
            }
        }

        if (in_array($type, ['club.membership_request_approved', 'club.membership_request_declined'], true)) {
            $requestId = self::positiveIdentifier($data['membership_request_id'] ?? $data['request_id'] ?? null);
            if ($requestId !== null) {
                return self::withAction($data, '/notifications', 'airmius://membership-applications/'.$requestId);
            }
        }

        $entityActions = [
            ['training_plan_id', '/training?plan=', 'airmius://training/plans/'],
            ['training_log_id', '/training/logs/', 'airmius://training/logs/'],
            ['event_id', '/events/', 'airmius://events/'],
            ['conversation_id', '/chat?conversation=', 'airmius://chat/'],
            ['team_id', '/teams/', 'airmius://teams/'],
            ['club_id', '/clubs/', 'airmius://clubs/'],
            ['order_id', '/marketplace/orders/', 'airmius://marketplace/orders/'],
        ];

        foreach ($entityActions as [$key, $webPrefix, $mobilePrefix]) {
            $identifier = self::positiveIdentifier($data[$key] ?? null);
            if ($identifier !== null) {
                return self::withAction($data, $webPrefix.$identifier, $mobilePrefix.$identifier);
            }
        }

        if (Str::startsWith($type, ['friend.', 'user.follow', 'profile.'])) {
            $profileId = self::positiveIdentifier($data['profile_id'] ?? $data['user_id'] ?? $data['actor_id'] ?? null);
            if ($profileId !== null) {
                return self::withAction($data, '/users/'.$profileId, 'airmius://profile/'.$profileId);
            }
        }

        if (self::hasMobileAction($data)) {
            return $data;
        }

        // Every in-app notification must resolve to a native screen. Feature
        // areas without a dedicated mobile route stay accessible through the
        // notification detail instead of opening an unknown web-path fallback.
        $data['mobile_url'] = 'airmius://notifications';
        $data['deep_link'] = 'airmius://notifications';

        return $data;
    }

    /** @param array<string, mixed> $data */
    private static function withAction(array $data, string $webUrl, string $mobileUrl): array
    {
        $resolvedWebUrl = self::existingWebAction($data) ?? $webUrl;
        $resolvedMobileUrl = self::existingMobileAction($data) ?? $mobileUrl;

        $data['url'] = $resolvedWebUrl;
        $data['action_url'] = $resolvedWebUrl;
        $data['mobile_url'] = $resolvedMobileUrl;
        $data['deep_link'] = $resolvedMobileUrl;

        return $data;
    }

    /** @param array<string, mixed> $data */
    private static function existingWebAction(array $data): ?string
    {
        foreach (['action_url', 'url'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $path = (string) (parse_url($value, PHP_URL_PATH) ?: $value);
            $query = parse_url($value, PHP_URL_QUERY);
            if ($query === null && in_array($path, [
                '/feed',
                '/club-memberships',
                '/notifications',
                '/training',
                '/chat',
                '/teams',
                '/clubs',
                '/events',
                '/marketplace/orders',
            ], true)) {
                continue;
            }

            if (Str::startsWith($value, '/') && ! Str::startsWith($value, '//')) {
                return $value;
            }

            $scheme = parse_url($value, PHP_URL_SCHEME);
            $host = parse_url($value, PHP_URL_HOST);
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if (in_array($scheme, ['http', 'https'], true) && $host !== null && $host === $appHost) {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    private static function existingMobileAction(array $data): ?string
    {
        foreach (['mobile_url', 'deep_link'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if (Str::startsWith($value, ['airmius://', 'https://app.airmius.com/'])) {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    private static function hasMobileAction(array $data): bool
    {
        foreach (['mobile_url', 'deep_link'] as $key) {
            if (Str::startsWith(trim((string) ($data[$key] ?? '')), ['airmius://', 'https://app.airmius.com/'])) {
                return true;
            }
        }

        return false;
    }

    private static function positiveIdentifier(mixed $value): ?int
    {
        if (! is_int($value) && (! is_string($value) || ! ctype_digit($value))) {
            return null;
        }

        $identifier = (int) $value;

        return $identifier > 0 ? $identifier : null;
    }
}
