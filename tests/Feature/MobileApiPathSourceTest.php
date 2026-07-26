<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MobileApiPathSourceTest extends TestCase
{
    public function test_executed_flutter_api_client_does_not_contain_legacy_friends_api_prefixes(): void
    {
        $file = base_path('mobile/airmius_mobile/lib/core/airmius_api_client.dart');
        $contents = file_get_contents($file);
        $this->assertStringNotContainsString("'/friends/", $contents);
        $this->assertStringNotContainsString('\"/friends/', $contents);
    }

    public function test_mobile_api_base_is_an_origin_and_paths_own_the_api_version(): void
    {
        $app = file_get_contents(base_path('mobile/airmius_mobile/lib/airmius_app.dart'));
        $client = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_api_client.dart'));

        $this->assertStringContainsString("return kReleaseMode ? 'https://app.airmius.com' : 'http://localhost'", $app);
        $this->assertStringContainsString("'/api/v1/auth/login'", $client);
        $this->assertStringContainsString("'/api/v1/mobile/push-devices'", $client);
    }

    public function test_every_literal_api_v1_template_in_executed_flutter_client_exists_in_laravel(): void
    {
        $client = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_api_client.dart'));
        preg_match_all('/[\'\"](\/api\/v1(?:\/[^\'\"]*)?)[\'\"]/', $client, $matches);

        $normalize = static function (string $path): string {
            $path = preg_replace('/\$\{[^}]+\}/', '{}', $path);
            $path = preg_replace('/\$[A-Za-z_][A-Za-z0-9_]*/', '{}', (string) $path);
            $path = preg_replace('/\{[^}]+\}/', '{}', (string) $path);
            return preg_replace('#/+#', '/', (string) $path);
        };

        $serverTemplates = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
            ->filter(fn (string $uri) => str_starts_with($uri, '/api/v1'))
            ->map($normalize)
            ->unique()
            ->all();

        $clientTemplates = collect($matches[1])->map($normalize)->unique();
        $unmatched = $clientTemplates->diff($serverTemplates)->values()->all();

        $this->assertNotEmpty($clientTemplates);
        $this->assertSame([], $unmatched, 'Flutter references missing Laravel API routes: '.implode(', ', $unmatched));
    }
}
