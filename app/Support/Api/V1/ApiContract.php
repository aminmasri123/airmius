<?php

namespace App\Support\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiContract
{
    public const VERSION = 'v1';

    public const CONTRACT_VERSION = '2026-08-08';

    public const MIN_CLIENT_VERSION = '1.0.0';

    public static function matches(Request $request): bool
    {
        return $request->is('api/v1') || $request->is('api/v1/*');
    }

    public static function requestId(Request $request): string
    {
        $existing = $request->attributes->get('api_request_id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $header = trim((string) $request->headers->get('X-Request-Id'));
        $requestId = self::isValidRequestId($header) ? $header : (string) Str::uuid();

        $request->attributes->set('api_request_id', $requestId);

        return $requestId;
    }

    public static function headers(Request $request): array
    {
        return [
            'X-Airmius-Api-Version' => self::VERSION,
            'X-Airmius-Contract' => self::CONTRACT_VERSION,
            'X-Airmius-Min-Client-Version' => self::MIN_CLIENT_VERSION,
            'X-Request-Id' => self::requestId($request),
        ];
    }

    public static function meta(Request $request, array $extra = []): array
    {
        return array_merge([
            'api_version' => self::VERSION,
            'contract_version' => self::CONTRACT_VERSION,
            'request_id' => self::requestId($request),
        ], $extra);
    }

    private static function isValidRequestId(string $requestId): bool
    {
        return $requestId !== ''
            && strlen($requestId) <= 120
            && preg_match('/^[A-Za-z0-9_.:-]+$/', $requestId) === 1;
    }
}
