<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserDataErasureCodeRequested;
use App\Support\SupportedLocale;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class UserDataErasureConfirmationService
{
    private const TTL_MINUTES = 15;

    public function issue(User $user, string $identity, array $categories): void
    {
        $this->assertIdentity($user, $identity);

        $categories = app(UserDataErasureService::class)->normalizeCategories($categories);
        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey((int) $user->id), [
            'code_hash' => Hash::make($code),
            'categories_hash' => $this->categoriesHash($categories),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
        ], now()->addMinutes(self::TTL_MINUTES));

        Notification::route('mail', $user->email)
            ->notify(new UserDataErasureCodeRequested(
                $code,
                SupportedLocale::normalize($user->language) ?? app()->getLocale(),
            ));
    }

    /**
     * @return array<int, string>
     */
    public function consume(User $user, string $code, array $categories): array
    {
        $categories = app(UserDataErasureService::class)->normalizeCategories($categories);
        $confirmation = Cache::get($this->cacheKey((int) $user->id));

        if (! is_array($confirmation)
            || now()->timestamp > (int) ($confirmation['expires_at'] ?? 0)
            || ! hash_equals((string) ($confirmation['categories_hash'] ?? ''), $this->categoriesHash($categories))
            || ! Hash::check($code, (string) ($confirmation['code_hash'] ?? ''))
        ) {
            throw ValidationException::withMessages([
                'code' => __('data_erasure.validation.code_invalid'),
            ]);
        }

        Cache::forget($this->cacheKey((int) $user->id));

        return $categories;
    }

    public function usesSocialLogin(User $user): bool
    {
        return $user->socialAccounts()->exists();
    }

    private function assertIdentity(User $user, string $identity): void
    {
        $usesSocialLogin = $this->usesSocialLogin($user);
        $identity = trim($identity);

        $confirmed = $usesSocialLogin
            ? hash_equals(strtolower((string) $user->email), strtolower($identity))
            : Hash::check($identity, $user->password);

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'identity' => $usesSocialLogin
                    ? __('data_erasure.validation.email_mismatch')
                    : __('data_erasure.validation.password_incorrect'),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function categoriesHash(array $categories): string
    {
        return hash('sha256', json_encode($categories, JSON_THROW_ON_ERROR));
    }

    private function cacheKey(int $userId): string
    {
        return "user_data_erasure_confirmation:{$userId}";
    }
}
