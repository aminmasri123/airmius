<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VisitorCountry
{
    public function resolve(Request $request, ?string $preferredCountry = null): array
    {
        if ($country = $this->normalize($preferredCountry)) {
            return ['country' => $country, 'source' => 'profile'];
        }

        foreach ($this->countryHeaders() as $header) {
            if ($country = $this->normalize($request->headers->get($header))) {
                return ['country' => $country, 'source' => strtolower($header)];
            }
        }

        if ($country = $this->fromConfiguredApi($request)) {
            return ['country' => $country, 'source' => 'ip_api'];
        }

        if ($country = $this->fromAcceptLanguage($request)) {
            return ['country' => $country, 'source' => 'browser_language'];
        }

        return ['country' => config('app.fallback_country', 'DE'), 'source' => 'fallback'];
    }

    private function countryHeaders(): array
    {
        return [
            'CF-IPCountry',
            'CloudFront-Viewer-Country',
            'X-Vercel-IP-Country',
            'X-AppEngine-Country',
            'X-Country-Code',
        ];
    }

    private function fromConfiguredApi(Request $request): ?string
    {
        $url = config('services.geoip.url');

        if (blank($url)) {
            return null;
        }

        $ip = $request->ip();

        if (! $ip || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return null;
        }

        try {
            $response = Http::timeout(2)->acceptJson()->get(str_replace('{ip}', $ip, $url));

            if (! $response->ok()) {
                return null;
            }

            return $this->normalize(
                $response->json('country_code')
                ?? $response->json('countryCode')
                ?? $response->json('country')
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        $language = (string) $request->headers->get('Accept-Language', '');

        if (preg_match('/\b[a-z]{2}-([A-Z]{2})\b/', $language, $matches)) {
            return $this->normalize($matches[1]);
        }

        return null;
    }

    private function normalize(?string $country): ?string
    {
        $country = strtoupper(trim((string) $country));

        if ($country === '' || $country === 'XX' || ! preg_match('/^[A-Z]{2}$/', $country)) {
            return null;
        }

        return $country;
    }
}
