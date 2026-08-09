<?php

namespace Tests\Feature;

use App\Support\StagingHttpDeliveryReport;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class StagingHttpDeliveryAuditTest extends TestCase
{
    private const BASE_URL = 'https://staging.airmius.test';

    private const GUEST_ORDER_PATH = '/checkout/guest-commerce/17/top-secret-token-value/success';

    public function test_full_remote_contract_passes_without_exposing_urls_tokens_headers_or_bodies(): void
    {
        $this->fakeHealthyStaging();

        $report = app(StagingHttpDeliveryReport::class)->make(
            self::BASE_URL,
            self::GUEST_ORDER_PATH,
        );
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('staging-http-delivery.v1', $report['contract']);
        $this->assertSame('go', $report['decision'], $encoded);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertTrue($report['evidence_complete']);
        $this->assertSame(['pass' => 11, 'pending' => 0, 'fail' => 0], $report['summary']);
        $this->assertStringNotContainsString(self::BASE_URL, $encoded);
        $this->assertStringNotContainsString('top-secret-token-value', $encoded);
        $this->assertStringNotContainsString('W/"private-validator"', $encoded);
        $this->assertStringNotContainsString('<html', $encoded);
        foreach ($report['privacy'] as $value) {
            $this->assertFalse($value);
        }

        $pathFile = tempnam(sys_get_temp_dir(), 'airmius-staging-order-');
        $this->assertIsString($pathFile);
        file_put_contents($pathFile, self::GUEST_ORDER_PATH);
        try {
            $exitCode = Artisan::call('airmius:audit-staging-http', [
                '--base-url' => self::BASE_URL,
                '--guest-order-path-file' => $pathFile,
                '--json' => true,
                '--strict' => true,
            ]);
            $output = Artisan::output();
            $this->assertSame(Command::SUCCESS, $exitCode);
            $this->assertStringNotContainsString('top-secret-token-value', $output);
            $this->assertStringNotContainsString(self::BASE_URL, $output);
        } finally {
            @unlink($pathFile);
        }

        Http::assertSentCount(38);
    }

    public function test_missing_live_guest_order_stays_pending_and_strict_command_fails(): void
    {
        $this->fakeHealthyStaging();

        $report = app(StagingHttpDeliveryReport::class)->make(self::BASE_URL);

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['evidence_complete']);
        $this->assertSame('pending', $this->checkStatus($report, 'remote.tokenized_guest_order'));

        $this->assertSame(Command::SUCCESS, Artisan::call('airmius:audit-staging-http', [
            '--base-url' => self::BASE_URL,
            '--json' => true,
        ]));
        $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-staging-http', [
            '--base-url' => self::BASE_URL,
            '--json' => true,
            '--strict' => true,
        ]));
    }

    public function test_invalid_or_sensitive_inputs_fail_before_any_network_request_and_are_not_printed(): void
    {
        Http::fake();
        $unsafe = 'http://user:private-password@internal.example.test/?token=secret';

        $report = app(StagingHttpDeliveryReport::class)->make($unsafe, '/checkout/guest-commerce/../secret');
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $this->checkStatus($report, 'input.configuration'));
        $this->assertStringNotContainsString('private-password', $encoded);
        $this->assertStringNotContainsString('internal.example.test', $encoded);
        $this->assertStringNotContainsString('token=secret', $encoded);

        $credentialOnly = app(StagingHttpDeliveryReport::class)->make('https://private-user@staging.airmius.test');
        $this->assertSame('fail', $this->checkStatus($credentialOnly, 'input.configuration'));
        Http::assertNothingSent();
    }

    public function test_one_bad_remote_header_fails_closed_without_copying_the_value(): void
    {
        $this->fakeHealthyStaging(compression: 'identity-private-value');

        $report = app(StagingHttpDeliveryReport::class)->make(self::BASE_URL, self::GUEST_ORDER_PATH);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $this->checkStatus($report, 'remote.immutable_asset'));
        $this->assertStringNotContainsString('identity-private-value', $encoded);
    }

    private function fakeHealthyStaging(string $compression = 'br'): void
    {
        Http::fake(function (Request $request) use ($compression) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $security = [
                'Content-Security-Policy' => "default-src 'self'",
                'X-Content-Type-Options' => 'nosniff',
            ];

            if ($request->method() === 'OPTIONS' && $path === '/api/v1/meta') {
                return Http::response('', 204, [
                    'Access-Control-Allow-Origin' => 'https://app.airmius.com',
                    'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                    'Access-Control-Allow-Headers' => 'X-Locale, Authorization',
                    'Access-Control-Expose-Headers' => 'ETag, X-Request-Id',
                ]);
            }

            if ($path === '/') {
                return Http::response('<html><body>Airmius</body></html>', 200, $security + [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
                ]);
            }

            if (str_starts_with($path, '/build/assets/')) {
                return Http::response('versioned-app-asset', 200, [
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                    'Content-Encoding' => $compression,
                    'Vary' => 'Accept-Encoding',
                ]);
            }

            if ($path === '/build/manifest.json') {
                return Http::response('{}', 200, [
                    'Cache-Control' => 'no-cache, must-revalidate',
                ]);
            }

            if ($path === '/api/v1/meta') {
                if ($request->hasHeader('Authorization')) {
                    return Http::response(['data' => []], 200, [
                        'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                    ]);
                }
                if ($request->hasHeader('If-None-Match')) {
                    return Http::response('', 304, ['ETag' => 'W/"meta-validator"']);
                }

                return Http::response(['data' => []], 200, [
                    'Cache-Control' => 'public, max-age=300, stale-while-revalidate=60, must-revalidate',
                    'ETag' => 'W/"meta-validator"',
                    'Vary' => 'Accept-Language, X-Locale',
                ]);
            }

            if ($path === '/api/v1/__airmius_delivery_probe_missing') {
                return Http::response(['code' => 'not_found'], 404, [
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                ]);
            }

            if (in_array($path, ['/robots.txt', '/sitemap.xml', '/blog/rss.xml'], true)) {
                $etag = 'W/"'.trim(str_replace(['/', '.'], '-', $path), '-').'"';
                if ($request->hasHeader('If-None-Match')) {
                    return Http::response('', 304, ['ETag' => $etag]);
                }

                return Http::response('public machine document', 200, [
                    'Cache-Control' => 'public, max-age=600, must-revalidate',
                    'ETag' => $etag,
                ]);
            }

            if ($path === '/marketplace' && $request->hasHeader('X-Inertia')) {
                return Http::response([
                    'component' => 'Guest/Marketplace',
                    'props' => [
                        'products' => ['data' => []],
                        'commerceCatalog' => ['data' => []],
                        'filters' => ['search' => 'airmius-delivery-audit'],
                    ],
                ], 200, ['X-Inertia' => 'true']);
            }

            if (in_array($path, ['/vereine', '/marketplace', '/e-learning'], true)) {
                return Http::response('<html><body>Public Airmius surface</body></html>', 200, $security + [
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]);
            }

            if ($path === self::GUEST_ORDER_PATH) {
                return Http::response(
                    '<html><head><meta name="robots" content="noindex,nofollow"></head><body>Private order</body></html>',
                    200,
                    [
                        'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                        'Pragma' => 'no-cache',
                        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                        'Referrer-Policy' => 'no-referrer',
                    ],
                );
            }

            return Http::response('', 500);
        });
    }

    /** @param array<string, mixed> $report */
    private function checkStatus(array $report, string $id): string
    {
        $check = collect($report['checks'])->firstWhere('id', $id);
        $this->assertIsArray($check, "Missing staging HTTP check: {$id}");

        return $check['status'];
    }
}
