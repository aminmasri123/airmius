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
        if (! in_array($type, ['club.member_linked', 'club.member.role_updated'], true)) {
            return $data;
        }

        $clubId = self::positiveIdentifier($data['club_id'] ?? null);
        if ($clubId === null) {
            return $data;
        }

        $data['url'] = '/clubs/'.$clubId;
        $data['action_url'] = '/clubs/'.$clubId;
        $data['mobile_url'] = 'airmius://clubs/'.$clubId;
        $data['deep_link'] = 'airmius://clubs/'.$clubId;

        return $data;
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
