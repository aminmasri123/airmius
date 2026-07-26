<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MobileTwoFactorChallenge
{
    public const EXPIRES_IN_SECONDS = 600;

    /**
     * @return array{token: string, expires_in: int}
     */
    public function issue(User $user, string $deviceName): array
    {
        $token = Str::random(80);

        Cache::put($this->key($token), [
            'user_id' => $user->getKey(),
            'device_name' => trim($deviceName) ?: 'mobile',
        ], now()->addSeconds(self::EXPIRES_IN_SECONDS));

        return [
            'token' => $token,
            'expires_in' => self::EXPIRES_IN_SECONDS,
        ];
    }

    /**
     * @return array{user_id: int, device_name: string}|null
     */
    public function find(string $token): ?array
    {
        $payload = Cache::get($this->key($token));

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['device_name'])) {
            return null;
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'device_name' => (string) $payload['device_name'],
        ];
    }

    public function forget(string $token): void
    {
        Cache::forget($this->key($token));
    }

    private function key(string $token): string
    {
        return 'mobile-2fa-challenge:'.hash('sha256', $token);
    }
}
