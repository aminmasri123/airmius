<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GraphHopperConnection
{
    public function selfHosted(): bool
    {
        return config('sport_map.routing.graphhopper_mode', 'cloud') === 'self_hosted';
    }

    public function baseUrl(): string
    {
        return rtrim(trim((string) config('sport_map.routing.'.($this->selfHosted()
            ? 'graphhopper_self_hosted_url' : 'graphhopper_base_url'))), '/');
    }

    public function configured(): bool
    {
        $url = $this->baseUrl();

        return in_array(config('sport_map.routing.graphhopper_mode', 'cloud'), ['cloud', 'self_hosted'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_QUERY) === null
            && parse_url($url, PHP_URL_FRAGMENT) === null
            && ($this->selfHosted() || trim((string) config('sport_map.routing.graphhopper_api_key')) !== '');
    }

    public function parameters(bool $roundTrip = false): array
    {
        if ($this->selfHosted()) {
            return $roundTrip ? ['ch.disable' => 'true'] : [];
        }

        return ['key' => trim((string) config('sport_map.routing.graphhopper_api_key'))];
    }

    public function request(): PendingRequest
    {
        $request = Http::timeout(max(1, (int) config('sport_map.routing.timeout_seconds', 4)))
            ->acceptJson()->withoutRedirecting();
        $token = trim((string) config('sport_map.routing.graphhopper_self_hosted_token'));
        if ($this->selfHosted() && $token !== '') {
            $request->withToken($token);
        }

        return $request;
    }
}
