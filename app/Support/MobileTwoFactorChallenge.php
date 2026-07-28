<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MobileTwoFactorChallenge
{
    public const EXPIRES_IN_SECONDS = 600;

    public const EMAIL_CODE_MAX_ATTEMPTS = 5;

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
        Cache::forget($this->emailCodeKey($token));
    }

    public function issueEmailCode(string $token): string
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->emailCodeKey($token), [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::EXPIRES_IN_SECONDS)->timestamp,
        ], now()->addSeconds(self::EXPIRES_IN_SECONDS));

        return $code;
    }

    public function verifyEmailCode(string $token, string $code): bool
    {
        $key = $this->emailCodeKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload)
            || now()->timestamp > (int) ($payload['expires_at'] ?? 0)
            || (int) ($payload['attempts'] ?? 0) >= self::EMAIL_CODE_MAX_ATTEMPTS
        ) {
            Cache::forget($key);

            return false;
        }

        if (Hash::check($code, (string) ($payload['code_hash'] ?? ''))) {
            Cache::forget($key);

            return true;
        }

        $payload['attempts'] = (int) ($payload['attempts'] ?? 0) + 1;

        if ($payload['attempts'] >= self::EMAIL_CODE_MAX_ATTEMPTS) {
            Cache::forget($key);
        } else {
            $remainingSeconds = max(1, (int) $payload['expires_at'] - now()->timestamp);
            Cache::put($key, $payload, now()->addSeconds($remainingSeconds));
        }

        return false;
    }

    private function key(string $token): string
    {
        return 'mobile-2fa-challenge:'.hash('sha256', $token);
    }

    private function emailCodeKey(string $token): string
    {
        return 'mobile-2fa-email-code:'.hash('sha256', $token);
    }
}
