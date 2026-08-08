<?php

namespace Tests\Unit;

use App\Support\PlatformModuleRegistry;
use App\Support\Privacy\DataClassification;
use App\Support\Privacy\ProcessingPurpose;
use PHPUnit\Framework\TestCase;

class PlatformModuleRegistryTest extends TestCase
{
    public function test_module_contract_is_complete_and_dependency_safe(): void
    {
        $modules = PlatformModuleRegistry::definitions();

        foreach ([
            'ads', 'marketplace', 'learning', 'feed_story', 'chat', 'training',
            'outfit_subscription', 'blog', 'events', 'subscriptions', 'admin',
            'leveling', 'teams', 'members', 'files', 'agency', 'sport_matching',
            'sponsors', 'nutrition', 'routes', 'recruiting',
        ] as $expected) {
            self::assertArrayHasKey($expected, $modules);
        }

        foreach ($modules as $key => $module) {
            self::assertNotEmpty($module['domain'], $key);
            self::assertNotEmpty($module['label_key'], $key);
            self::assertInstanceOf(ProcessingPurpose::class, $module['processing_purpose'], $key);
            self::assertInstanceOf(DataClassification::class, $module['data_classification'], $key);
            self::assertNotEmpty($module['api_prefixes'], $key);

            foreach ($module['depends_on'] as $dependency) {
                self::assertArrayHasKey($dependency, $modules, "{$key} depends on an unknown module {$dependency}");
            }
        }
    }

    public function test_api_paths_resolve_to_their_governed_module(): void
    {
        self::assertSame('training', PlatformModuleRegistry::forApiPath('/api/v1/training/plans')['key']);
        self::assertSame('nutrition', PlatformModuleRegistry::forApiPath('api/v1/nutrition/water')['key']);
        self::assertSame('marketplace', PlatformModuleRegistry::forApiPath('api/v1/commerce/orders')['key']);
        self::assertSame('admin', PlatformModuleRegistry::forApiPath('api/v1/admin/platform')['key']);
        self::assertNull(PlatformModuleRegistry::forApiPath('api/v1/unknown-module'));
    }
}
