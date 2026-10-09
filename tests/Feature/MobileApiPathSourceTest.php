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

        $this->assertStringContainsString("return 'https://airmius.com'", $app);
        $this->assertStringContainsString("'/api/v1/auth/login'", $client);
        $this->assertStringContainsString("'/api/v1/mobile/push-devices'", $client);
    }

    public function test_every_literal_api_v1_template_in_executed_flutter_client_exists_in_laravel(): void
    {
        $client = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_api_client.dart'));
        // Interpolations can contain quoted alternatives inside a Dart string.
        preg_match_all('~(?<quote>[\'\"])(?<path>/api/v1(?:(?:\\\\.)|\$\{[^}]*\}|(?!\k<quote>)[^\\\\])*?)\k<quote>~s', $client, $matches);

        $normalize = static function (string $path): string {
            $path = str_replace('{slash}', '', $path);
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

        $clientTemplates = collect($matches['path'])->flatMap(fn ($path) => $this->expandAlternatives($path))->map($normalize)->unique();
        $unmatched = $clientTemplates->reject(fn ($path) => $this->matchesRoute($path, $serverTemplates))->values()->all();

        $this->assertNotEmpty($clientTemplates);
        $this->assertSame([], $unmatched, 'Flutter references missing Laravel API routes: '.implode(', ', $unmatched));
    }

    public function test_source_checker_expands_choices_and_accepts_literal_route_parameters_without_accepting_missing_routes(): void
    {
        $this->assertSame(['/api/v1/admin/1/approve', '/api/v1/admin/1/reject'], $this->expandAlternatives('/api/v1/admin/1/${approve ? \'approve\' : \'reject\'}'));
        $routes = ['/api/v1/billing/invoices/{}/{}/question'];
        $this->assertTrue($this->matchesRoute('/api/v1/billing/invoices/club_invoice/{}/question', $routes));
        $this->assertFalse($this->matchesRoute('/api/v1/billing/invoices/club_invoice/{}/missing', $routes));
    }

    private function expandAlternatives(string $path): array
    {
        if (preg_match('/\$\{[^{}]*\?\s*[\'\"]([^\'\"]+)[\'\"]\s*:\s*[\'\"]([^\'\"]+)[\'\"]\}/', $path, $choice)) {
            return array_merge($this->expandAlternatives(str_replace($choice[0], $choice[1], $path)), $this->expandAlternatives(str_replace($choice[0], $choice[2], $path)));
        }

        return [$path];
    }

    private function matchesRoute(string $path, array $routes): bool
    {
        foreach ($routes as $route) {
            $pattern = '#^'.str_replace('\{\}', '[^/]+', preg_quote($route, '#')).'$#';
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
