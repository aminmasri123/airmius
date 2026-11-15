<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RideGeocodingService
{
    public function search(string $query, string $locale = 'de', bool $area = false): array
    {
        $url = (string) config('rides.geocoding_url');
        $key = (string) config('rides.geocoding_key');
        if (! $url || ! $key) {
            throw new RuntimeException('geocoding_unavailable');
        }

        return Cache::remember('ride-places:'.hash('sha256', $url.$key.$locale.$area.mb_strtolower(trim($query))), now()->addHours(12), function () use ($url, $key, $query, $locale, $area) {
            // Never send keys to the client, follow redirects, or log provider URLs.
            $response = Http::acceptJson()->connectTimeout(3)->timeout(6)->withoutRedirecting()->get($url, [
                'key' => $key, 'q' => $query, 'locale' => $locale, 'limit' => 8,
            ]);
            if (! $response->successful() || ! is_array($response->json('hits'))) {
                throw new RuntimeException('geocoding_unavailable');
            }

            return collect($response->json('hits'))->map(function ($hit) use ($area) {
                if (! is_array($hit) || ! is_numeric($hit['point']['lat'] ?? null) || ! is_numeric($hit['point']['lng'] ?? null)) {
                    return null;
                }
                $lat = (float) $hit['point']['lat'];
                $lng = (float) $hit['point']['lng'];
                if (abs($lat) > 90 || abs($lng) > 180 || ($area && (! empty($hit['street']) || ! empty($hit['housenumber'])))) {
                    return null;
                }
                if ($area && ! in_array($hit['osm_key'] ?? null, ['place', 'boundary'], true)) {
                    return null;
                }
                $text = fn ($key) => mb_substr(trim((string) ($hit[$key] ?? '')), 0, 255);
                $city = $text('city') ?: (empty($hit['street']) ? $text('name') : '');

                return [
                    'label' => implode(', ', array_unique(array_filter([$text('name'), trim($text('street').' '.$text('housenumber')), trim($text('postcode').' '.$city), $text('country')]))),
                    'name' => $text('name'), 'street' => $text('street'), 'house_number' => mb_substr($text('housenumber'), 0, 40),
                    'postal_code' => mb_substr($text('postcode'), 0, 30), 'city' => $city,
                    'country' => strtoupper(mb_substr($text('countrycode'), 0, 2)), 'latitude' => $lat, 'longitude' => $lng,
                ];
            })->filter()->values()->all();
        });
    }

    public function area(string $postal, string $city, string $country): ?array
    {
        if (! trim($city) || ! trim($country)) {
            return null;
        }
        // Deliberately geocode the public locality, never a private street.
        $hits = $this->search(trim("$postal $city $country"), app()->getLocale(), true);
        foreach ($hits as $hit) {
            if ($hit['country'] === strtoupper($country)
                && (mb_strtolower(Str::ascii($hit['city'])) === mb_strtolower(Str::ascii(trim($city)))
                    || ($postal !== '' && $hit['postal_code'] === trim($postal)))
                && (! $hit['postal_code'] || ! $postal || $hit['postal_code'] === trim($postal))) {
                return $hit;
            }
        }

        return null;
    }
}
