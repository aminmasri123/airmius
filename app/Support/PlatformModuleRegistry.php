<?php

namespace App\Support;

use App\Support\Privacy\DataClassification;
use App\Support\Privacy\ProcessingPurpose;

final class PlatformModuleRegistry
{
    public static function definitions(): array
    {
        return [
            'account' => self::module('identity', 'modules.account', 'auth.home', ProcessingPurpose::ProductOperation, DataClassification::Sensitive, ['me', 'settings', 'privacy', 'account'], []),
            'notifications' => self::module('identity', 'modules.notifications', 'auth.notifications.index', ProcessingPurpose::ProductOperation, DataClassification::Personal, ['notifications', 'mobile/push-devices'], ['account'], ['realtime', 'partial']),
            'feed_story' => self::module('community', 'modules.feed_story', 'auth.feed.index', ProcessingPurpose::ProductOperation, DataClassification::Personal, ['posts', 'stories', 'feed'], ['account'], ['realtime', 'partial']),
            'chat' => self::module('community', 'modules.chat', 'auth.conversations.index', ProcessingPurpose::ProductOperation, DataClassification::Sensitive, ['conversations', 'messages', 'chat'], ['account'], ['realtime', 'offline_queue']),
            'sport_matching' => self::module('community', 'modules.sport_matching', 'auth.sport-matching.index', ProcessingPurpose::ProductOperation, DataClassification::Personal, ['sport-matching'], ['account'], ['partial']),
            'training' => self::module('sport_performance', 'modules.training', 'auth.training.index', ProcessingPurpose::Training, DataClassification::HighlySensitive, ['training', 'training-availability'], ['account'], ['partial', 'offline_queue', 'outbox']),
            'nutrition' => self::module('sport_performance', 'modules.nutrition', 'auth.nutrition.index', ProcessingPurpose::Training, DataClassification::HighlySensitive, ['nutrition'], ['training'], ['partial', 'offline_queue']),
            'routes' => self::module('sport_performance', 'modules.routes', 'auth.sport-map.index', ProcessingPurpose::Training, DataClassification::Sensitive, ['sport-routes', 'sport-tracks', 'sport-places', 'sport-map', 'sport-integrations'], ['account'], ['partial', 'offline_queue']),
            'events' => self::module('organization', 'modules.events', 'auth.events.index', ProcessingPurpose::Organization, DataClassification::Personal, ['events'], ['account'], ['realtime', 'partial', 'outbox']),
            'teams' => self::module('organization', 'modules.teams', 'auth.teams.index', ProcessingPurpose::Organization, DataClassification::Personal, ['teams'], ['account'], ['realtime', 'partial', 'outbox']),
            'members' => self::module('organization', 'modules.members', 'auth.club-memberships.index', ProcessingPurpose::Organization, DataClassification::Sensitive, ['clubs', 'club-memberships', 'guardian'], ['teams'], ['partial', 'outbox']),
            'files' => self::module('organization', 'modules.files', 'auth.files.index', ProcessingPurpose::Organization, DataClassification::Sensitive, ['uploads', 'files', 'folders'], ['account'], ['partial', 'offline_queue']),
            'recruiting' => self::module('organization', 'modules.recruiting', 'auth.teams.index', ProcessingPurpose::Organization, DataClassification::Personal, ['organization-jobs', 'recruiting'], ['teams'], ['partial']),
            'learning' => self::module('content_learning', 'modules.learning', 'auth.learning.my-courses.index', ProcessingPurpose::ProductOperation, DataClassification::Personal, ['learning', 'learning-studio'], ['account'], ['partial', 'offline_queue']),
            'blog' => self::module('content_learning', 'modules.blog', 'guest.blog.index', ProcessingPurpose::ProductOperation, DataClassification::Public, ['blog'], [], ['partial']),
            'leveling' => self::module('content_learning', 'modules.leveling', 'auth.badges.index', ProcessingPurpose::ProductOperation, DataClassification::Personal, ['badges', 'gamification'], ['account'], ['partial']),
            'marketplace' => self::module('commerce_growth', 'modules.marketplace', 'guest.marketplace', ProcessingPurpose::Billing, DataClassification::Sensitive, ['marketplace', 'commerce'], ['account'], ['partial', 'outbox']),
            'subscriptions' => self::module('commerce_growth', 'modules.subscriptions', 'guest.pricing', ProcessingPurpose::Billing, DataClassification::Sensitive, ['billing', 'subscriptions', 'subscription-checkouts'], ['account'], ['partial', 'outbox']),
            'outfit_subscription' => self::module('commerce_growth', 'modules.outfit_subscription', 'auth.outfit-subscriptions.index', ProcessingPurpose::Billing, DataClassification::Sensitive, ['outfit-subscriptions', 'outfit-deliveries'], ['subscriptions'], ['partial', 'outbox']),
            'ads' => self::module('commerce_growth', 'modules.ads', 'admin.commerce.index', ProcessingPurpose::Marketing, DataClassification::Personal, ['ads', 'campaigns'], ['marketplace'], ['partial', 'outbox']),
            'sponsors' => self::module('commerce_growth', 'modules.sponsors', 'auth.sponsor-workspace.index', ProcessingPurpose::Marketing, DataClassification::Personal, ['sponsor-workspace', 'sponsors'], ['ads'], ['partial', 'outbox']),
            'agency' => self::module('commerce_growth', 'modules.agency', 'guest.werbeagentur', ProcessingPurpose::Marketing, DataClassification::Personal, ['website-requests', 'agency'], ['marketplace'], ['partial', 'outbox']),
            'admin' => self::module('platform_operations', 'modules.admin', 'auth.dashboard', ProcessingPurpose::Security, DataClassification::HighlySensitive, ['admin'], ['account'], ['partial', 'outbox']),
            'support' => self::module('platform_operations', 'modules.support', 'auth.notifications.index', ProcessingPurpose::Support, DataClassification::Sensitive, ['support'], ['account'], ['realtime', 'outbox']),
        ];
    }

    public static function forClient(): array
    {
        return collect(self::definitions())
            ->map(fn (array $module, string $key) => [
                'key' => $key,
                'domain' => $module['domain'],
                'label_key' => $module['label_key'],
                'web_route' => $module['web_route'],
                'processing_purpose' => $module['processing_purpose']->value,
                'data_classification' => $module['data_classification']->value,
                'depends_on' => $module['depends_on'],
                'delivery' => $module['delivery'],
            ])
            ->values()
            ->all();
    }

    public static function forApiPath(string $path): ?array
    {
        $path = trim(preg_replace('#^api/v1/?#', '', trim($path, '/')), '/');

        return collect(self::definitions())
            ->flatMap(fn (array $module, string $key) => collect($module['api_prefixes'])
                ->map(fn (string $prefix) => ['key' => $key, 'prefix' => $prefix, 'module' => $module]))
            ->filter(fn (array $candidate) => $path === $candidate['prefix'] || str_starts_with($path, $candidate['prefix'].'/'))
            ->sortByDesc(fn (array $candidate) => strlen($candidate['prefix']))
            ->first();
    }

    private static function module(
        string $domain,
        string $labelKey,
        string $webRoute,
        ProcessingPurpose $processingPurpose,
        DataClassification $dataClassification,
        array $apiPrefixes,
        array $dependsOn,
        array $delivery = ['partial'],
    ): array {
        return [
            'domain' => $domain,
            'label_key' => $labelKey,
            'web_route' => $webRoute,
            'processing_purpose' => $processingPurpose,
            'data_classification' => $dataClassification,
            'api_prefixes' => $apiPrefixes,
            'depends_on' => $dependsOn,
            'delivery' => $delivery,
        ];
    }
}
