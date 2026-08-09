<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

final class StagingHttpDeliveryReport
{
    public const CONTRACT = 'staging-http-delivery.v1';

    private const PRIVATE_CACHE_DIRECTIVES = [
        'private',
        'no-store',
        'no-cache',
        'must-revalidate',
        'max-age=0',
    ];

    private string $baseUrl;

    private string $origin;

    private int $timeout;

    /** @return array<string, mixed> */
    public function make(
        string $baseUrl,
        ?string $guestOrderPath = null,
        string $origin = 'https://app.airmius.com',
        int $timeout = 10,
    ): array {
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->origin = trim($origin);
        $this->timeout = min(30, max(2, $timeout));

        $configuration = $this->configurationCheck();
        if ($configuration['status'] === 'fail') {
            return $this->report([$configuration]);
        }

        $checks = [
            $configuration,
            $this->safeCheck('remote.tls_security', fn (): array => $this->tlsSecurityCheck()),
            $this->safeCheck('remote.immutable_asset', fn (): array => $this->immutableAssetCheck()),
            $this->safeCheck('remote.build_manifest', fn (): array => $this->buildManifestCheck()),
            $this->safeCheck('remote.public_api_cache', fn (): array => $this->publicApiCacheCheck()),
            $this->safeCheck('remote.private_api_cache', fn (): array => $this->privateApiCacheCheck()),
            $this->safeCheck('remote.cors', fn (): array => $this->corsCheck()),
            $this->safeCheck('remote.machine_readable_guest', fn (): array => $this->machineReadableGuestCheck()),
            $this->safeCheck('remote.guest_surfaces', fn (): array => $this->guestSurfaceCheck()),
            $this->safeCheck('remote.guest_partial_reload', fn (): array => $this->guestPartialReloadCheck()),
            $this->guestOrderCheck($guestOrderPath),
        ];

        return $this->report($checks);
    }

    /** @return array{id:string,status:string,detail:string} */
    private function configurationCheck(): array
    {
        $base = parse_url($this->baseUrl);
        $origin = parse_url($this->origin);
        $validBase = is_array($base)
            && ($base['scheme'] ?? null) === 'https'
            && is_string($base['host'] ?? null)
            && trim((string) $base['host']) !== ''
            && ! isset($base['user'])
            && ! isset($base['pass'])
            && ! isset($base['query'])
            && ! isset($base['fragment'])
            && in_array($base['path'] ?? '', ['', '/'], true);
        $validOrigin = is_array($origin)
            && in_array($origin['scheme'] ?? null, ['https', 'http'], true)
            && is_string($origin['host'] ?? null)
            && trim((string) $origin['host']) !== ''
            && ! isset($origin['user'])
            && ! isset($origin['pass'])
            && ! isset($origin['query'])
            && ! isset($origin['fragment'])
            && in_array($origin['path'] ?? '', ['', '/'], true);

        return $this->check(
            'input.configuration',
            $validBase && $validOrigin ? 'pass' : 'fail',
            $validBase && $validOrigin
                ? 'HTTPS base URL, browser origin, bounded timeout, and privacy-safe output policy are valid; input values are not emitted.'
                : 'Provide a root HTTPS base URL and a valid root browser origin without credentials, query, or fragment. Values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function tlsSecurityCheck(): array
    {
        $response = $this->request()->get($this->url('/'));
        $passes = $response->status() === 200
            && $this->contains($response, 'Strict-Transport-Security', 'max-age=')
            && trim($response->header('Content-Security-Policy')) !== ''
            && strtolower($response->header('X-Content-Type-Options')) === 'nosniff';

        return $this->check(
            'remote.tls_security',
            $passes ? 'pass' : 'fail',
            $passes ? 'TLS verification, HSTS, CSP, and MIME-sniffing protection passed on the public entry page.' : 'Public entry TLS/security-header verification failed; response values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function immutableAssetCheck(): array
    {
        $asset = $this->localAppAssetPath();
        if ($asset === null) {
            return $this->check('remote.immutable_asset', 'fail', 'The local version-bound app asset could not be resolved from the build manifest.');
        }

        $response = $this->request(['Accept-Encoding' => 'br, gzip'])->get($this->url('/build/'.$asset));
        $encoding = strtolower($response->header('Content-Encoding'));
        $passes = $response->status() === 200
            && $this->contains($response, 'Cache-Control', 'public')
            && $this->contains($response, 'Cache-Control', 'max-age=31536000')
            && $this->contains($response, 'Cache-Control', 'immutable')
            && in_array($encoding, ['br', 'gzip'], true)
            && $this->contains($response, 'Vary', 'Accept-Encoding');

        return $this->check(
            'remote.immutable_asset',
            $passes ? 'pass' : 'fail',
            $passes ? 'The version-bound app asset is immutable for one year and compressed with an encoding-aware cache variant.' : 'Immutable asset cache or compression verification failed; asset names and header values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function buildManifestCheck(): array
    {
        $response = $this->request()->get($this->url('/build/manifest.json'));
        $cache = strtolower($response->header('Cache-Control'));
        $passes = $response->status() === 200
            && str_contains($cache, 'no-cache')
            && str_contains($cache, 'must-revalidate')
            && ! str_contains($cache, 'immutable');

        return $this->check(
            'remote.build_manifest',
            $passes ? 'pass' : 'fail',
            $passes ? 'The mutable build manifest is revalidated and is never marked immutable.' : 'Build-manifest cache verification failed; header values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function publicApiCacheCheck(): array
    {
        $response = $this->request([
            'Accept' => 'application/json',
            'Accept-Language' => 'fr-FR,fr;q=0.9',
        ])->get($this->url('/api/v1/meta'));
        $etag = $response->header('ETag');
        $firstPasses = $response->status() === 200
            && trim($etag) !== ''
            && $this->contains($response, 'Cache-Control', 'public')
            && $this->contains($response, 'Cache-Control', 'max-age=300')
            && $this->contains($response, 'Vary', 'Accept-Language');

        $conditional = $this->request([
            'Accept' => 'application/json',
            'Accept-Language' => 'fr-FR,fr;q=0.9',
            'If-None-Match' => $etag,
        ])->get($this->url('/api/v1/meta'));
        $passes = $firstPasses && $conditional->status() === 304 && $conditional->header('ETag') === $etag;

        return $this->check(
            'remote.public_api_cache',
            $passes ? 'pass' : 'fail',
            $passes ? 'Anonymous locale-aware API metadata uses public five-minute caching and returns 304 for its validator.' : 'Public API ETag/cache verification failed; validators and header values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function privateApiCacheCheck(): array
    {
        $authorized = $this->request([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer airmius-delivery-audit-intentionally-invalid',
        ])->get($this->url('/api/v1/meta'));
        $missing = $this->request(['Accept' => 'application/json'])
            ->get($this->url('/api/v1/__airmius_delivery_probe_missing'));
        $passes = $authorized->status() === 200
            && $this->hasPrivateCachePolicy($authorized)
            && in_array($missing->status(), [404, 405], true)
            && $this->hasPrivateCachePolicy($missing);

        return $this->check(
            'remote.private_api_cache',
            $passes ? 'pass' : 'fail',
            $passes ? 'Authorization-bearing and error API responses are private, non-storable, and revalidation-safe.' : 'Private/error API cache verification failed; authorization and response values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function corsCheck(): array
    {
        $response = $this->request([
            'Origin' => $this->origin,
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'X-Locale',
        ])->send('OPTIONS', $this->url('/api/v1/meta'));
        $passes = $response->status() === 204
            && $response->header('Access-Control-Allow-Origin') === $this->origin
            && $this->contains($response, 'Access-Control-Allow-Methods', 'GET')
            && $this->contains($response, 'Access-Control-Allow-Headers', 'X-Locale')
            && $this->contains($response, 'Access-Control-Expose-Headers', 'ETag');

        return $this->check(
            'remote.cors',
            $passes ? 'pass' : 'fail',
            $passes ? 'Allowed-origin preflight exposes the required locale, cache-validator, and API headers.' : 'CORS preflight verification failed; origin and header values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function machineReadableGuestCheck(): array
    {
        foreach (['/robots.txt', '/sitemap.xml', '/blog/rss.xml'] as $path) {
            $response = $this->request()->get($this->url($path));
            $etag = $response->header('ETag');
            if ($response->status() !== 200
                || trim($etag) === ''
                || ! $this->contains($response, 'Cache-Control', 'public')
                || ! $this->contains($response, 'Cache-Control', 'max-age=')) {
                return $this->check('remote.machine_readable_guest', 'fail', 'A machine-readable guest surface failed its public cache/validator contract; paths and values are not emitted.');
            }

            $conditional = $this->request(['If-None-Match' => $etag])->get($this->url($path));
            if ($conditional->status() !== 304 || $conditional->header('ETag') !== $etag) {
                return $this->check('remote.machine_readable_guest', 'fail', 'A machine-readable guest surface failed conditional delivery; paths and validators are not emitted.');
            }
        }

        return $this->check('remote.machine_readable_guest', 'pass', 'Robots, sitemap, and RSS use public cache bounds, validators, and conditional 304 delivery.');
    }

    /** @return array{id:string,status:string,detail:string} */
    private function guestSurfaceCheck(): array
    {
        foreach (['/vereine', '/marketplace', '/e-learning'] as $path) {
            $response = $this->request(['Accept' => 'text/html'])->get($this->url($path));
            if ($response->status() !== 200
                || ! str_contains(strtolower($response->header('Content-Type')), 'text/html')
                || trim($response->header('Content-Security-Policy')) === ''
                || strtolower($response->header('X-Content-Type-Options')) !== 'nosniff'
                || str_contains($response->body(), 'feature_rollout_unavailable')) {
                return $this->check('remote.guest_surfaces', 'fail', 'A representative guest surface failed availability or security isolation; paths and response values are not emitted.');
            }
        }

        return $this->check('remote.guest_surfaces', 'pass', 'Club discovery, marketplace, and learning guest surfaces remain available, security-hardened, and outside workspace rollout failures.');
    }

    /** @return array{id:string,status:string,detail:string} */
    private function guestPartialReloadCheck(): array
    {
        $response = $this->request([
            'Accept' => 'application/json',
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Guest/Marketplace',
            'X-Inertia-Partial-Data' => 'products,commerceCatalog,filters',
        ])->get($this->url('/marketplace?search=airmius-delivery-audit'));
        $payload = $response->json();
        $props = is_array($payload) && is_array($payload['props'] ?? null) ? $payload['props'] : [];
        $hasRequired = collect(['products', 'commerceCatalog', 'filters'])
            ->every(static fn (string $key): bool => array_key_exists($key, $props));
        $hasExpensive = collect([
            'featuredProducts', 'flashDeals', 'essentialDeals', 'serviceDeals',
            'sportCategories', 'officialStores', 'providerLocations', 'cart',
        ])->contains(static fn (string $key): bool => array_key_exists($key, $props));
        $passes = $response->status() === 200
            && strtolower($response->header('X-Inertia')) === 'true'
            && ($payload['component'] ?? null) === 'Guest/Marketplace'
            && $hasRequired
            && ! $hasExpensive;

        return $this->check(
            'remote.guest_partial_reload',
            $passes ? 'pass' : 'fail',
            $passes ? 'The marketplace guest filter returns only its requested partial props without curated, provider, cart, or static payloads.' : 'Guest partial-reload payload verification failed; response content is not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function guestOrderCheck(?string $path): array
    {
        if ($path === null || trim($path) === '') {
            return $this->check('remote.tokenized_guest_order', 'pending', 'Provide one current relative guest-order success, cancel, or bank-transfer path. The path and token will never be emitted.');
        }

        $path = trim($path);
        if (! str_starts_with($path, '/checkout/guest-commerce/')
            || str_contains($path, '://')
            || str_contains($path, '..')
            || str_contains($path, '?')
            || str_contains($path, '#')) {
            return $this->check('remote.tokenized_guest_order', 'fail', 'The guest-order input must be a relative tokenized checkout path without query or fragment. The input is not emitted.');
        }

        return $this->safeCheck('remote.tokenized_guest_order', function () use ($path): array {
            $response = $this->request(['Accept' => 'text/html'])->get($this->url($path));
            $body = strtolower($response->body());
            $passes = $response->status() === 200
                && $this->hasPrivateCachePolicy($response)
                && strtolower($response->header('Pragma')) === 'no-cache'
                && $this->contains($response, 'X-Robots-Tag', 'noindex')
                && $this->contains($response, 'X-Robots-Tag', 'nofollow')
                && strtolower($response->header('Referrer-Policy')) === 'no-referrer'
                && str_contains($body, '<meta name="robots" content="noindex,nofollow"')
                && ! str_contains($body, '<link rel="canonical"')
                && ! str_contains($body, '<meta property="og:url"');

            return $this->check(
                'remote.tokenized_guest_order',
                $passes ? 'pass' : 'fail',
                $passes ? 'The live tokenized guest order is private, non-indexable, non-referring, and publishes no canonical or OpenGraph URL.' : 'Tokenized guest-order privacy verification failed; path, token, body, and headers are not emitted.',
            );
        });
    }

    private function request(array $headers = []): PendingRequest
    {
        return Http::withOptions([
            'allow_redirects' => false,
            'verify' => true,
        ])->connectTimeout(min(5, $this->timeout))
            ->timeout($this->timeout)
            ->withHeaders(array_merge([
                'User-Agent' => 'Airmius-Staging-Delivery-Audit/'.ReleaseReadinessReport::VERSION,
            ], $headers));
    }

    private function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    private function contains(Response $response, string $header, string $needle): bool
    {
        return str_contains(strtolower($response->header($header)), strtolower($needle));
    }

    private function hasPrivateCachePolicy(Response $response): bool
    {
        $cache = strtolower($response->header('Cache-Control'));

        return collect(self::PRIVATE_CACHE_DIRECTIVES)
            ->every(static fn (string $directive): bool => str_contains($cache, $directive));
    }

    private function localAppAssetPath(): ?string
    {
        $path = public_path('build/manifest.json');
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        try {
            $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $asset = is_array($manifest['resources/js/app.js'] ?? null)
                ? ($manifest['resources/js/app.js']['file'] ?? null)
                : null;

            return is_string($asset) && preg_match('#^assets/[A-Za-z0-9._-]+$#', $asset) === 1 ? $asset : null;
        } catch (JsonException) {
            return null;
        }
    }

    /** @param callable(): array{id:string,status:string,detail:string} $callback */
    private function safeCheck(string $id, callable $callback): array
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            return $this->check($id, 'fail', 'The remote check could not complete ('.$exception::class.'). URLs, headers, bodies, credentials, and tokens are not emitted.');
        }
    }

    /**
     * @param  array<int, array{id:string,status:string,detail:string}>  $checks
     * @return array<string, mixed>
     */
    private function report(array $checks): array
    {
        $summary = array_fill_keys(['pass', 'pending', 'fail'], 0);
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return [
            'contract' => self::CONTRACT,
            'release_version' => ReleaseReadinessReport::VERSION,
            'generated_at' => now()->utc()->toIso8601String(),
            'decision' => $summary['fail'] === 0 && $summary['pending'] === 0 ? 'go' : 'no_go',
            'automated_checks_passed' => $summary['fail'] === 0,
            'evidence_complete' => $summary['fail'] === 0 && $summary['pending'] === 0,
            'summary' => $summary,
            'checks' => $checks,
            'privacy' => [
                'stores_personal_data' => false,
                'stores_secrets' => false,
                'outputs_urls' => false,
                'outputs_headers' => false,
                'outputs_bodies' => false,
                'outputs_tokens' => false,
            ],
        ];
    }

    /** @return array{id:string,status:string,detail:string} */
    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
