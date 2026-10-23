<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ProgressiveLoginLockout
{
    private function key(string $email): string
    {
        return 'login-lockout:v1:'.hash('sha256', mb_strtolower(trim($email)));
    }

    public function state(string $email): array
    {
        return Cache::get($this->key($email), ['attempts' => 0, 'blocked_until' => 0]);
    }

    public function remainingSeconds(string $email): int
    {
        return max(0, $this->state($email)['blocked_until'] - now()->timestamp);
    }

    public function ensureAllowed(string $email): void
    {
        $seconds = $this->remainingSeconds($email);
        if ($seconds > 0) {
            throw ValidationException::withMessages([
                'email' => [__('account_security.login_lockout', ['seconds' => $seconds])],
            ])->status(429);
        }
    }

    /** Returns true only when this failure starts a new lockout. */
    public function recordFailure(string $email): bool
    {
        $key = $this->key($email);

        return Cache::lock($key.':lock', 10)->block(5, function () use ($key, $email) {
            $state = $this->state($email);
            if ($state['blocked_until'] > now()->timestamp) {
                return false;
            }

            $state['attempts']++;
            $blocked = $state['attempts'] % 5 === 0;
            if ($blocked) {
                $duration = $state['attempts'] >= 15 ? 3600 : ($state['attempts'] >= 10 ? 600 : 180);
                $state['blocked_until'] = now()->timestamp + $duration;
            }
            // Preserve escalation across cooldowns and successful logins until password reset.
            Cache::forever($key, $state);

            return $blocked;
        });
    }

    public function reset(string $email): void
    {
        $key = $this->key($email);
        Cache::lock($key.':lock', 10)->block(5, fn () => Cache::forget($key));
    }
}
