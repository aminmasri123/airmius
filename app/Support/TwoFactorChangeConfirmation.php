<?php

namespace App\Support;

use App\Notifications\TwoFactorChangeCodeRequested;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorChangeConfirmation
{
    public function issue(Request $request, string $purpose): array
    {
        $user = $request->user();
        abort_unless($user->hasVerifiedEmail() && filter_var($user->email, FILTER_VALIDATE_EMAIL), 422, 'Bitte bestätige zuerst deine E-Mail-Adresse.');
        $rate = 'two-factor-change-send:'.$user->id;
        abort_if(RateLimiter::tooManyAttempts($rate, 3), 429, 'Zu viele Code-Anfragen. Bitte versuche es später erneut.');
        RateLimiter::hit($rate, 600);
        $token = Str::random(64);
        $code = (string) random_int(100000, 999999);
        $key = $this->key($request, $purpose);
        $payload = [
            'token_hash' => hash('sha256', $token),
            'code_hash' => Hash::make($code),
            'email_hash' => hash('sha256', $user->email),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];
        Cache::lock($key.':lock', 10)->block(3, fn () => Cache::put($key, $payload, 600));
        try {
            $user->notify(new TwoFactorChangeCodeRequested($code, $purpose));
        } catch (\Throwable $exception) {
            // Invalidate only this failed delivery, not a newer request.
            Cache::lock($key.':lock', 10)->block(3, function () use ($key, $payload) {
                if (Cache::get($key) === $payload) {
                    Cache::forget($key);
                }
            });
            report($exception);
            abort(503, 'Die E-Mail konnte nicht versendet werden. Bitte versuche es später erneut.');
        }

        return ['challenge_token' => $token, 'expires_in' => 600, 'message' => 'Der Code wurde an deine bestätigte E-Mail-Adresse gesendet.'];
    }

    public function confirm(Request $request, string $purpose): void
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string', 'size:64'],
            'email_code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);
        $rate = 'two-factor-change-attempts:'.$request->user()->id;
        $key = $this->key($request, $purpose);
        $valid = Cache::lock($key.':lock', 10)->block(3, function () use ($request, $data, $rate, $key) {
            abort_if(RateLimiter::tooManyAttempts($rate, 5), 429, 'Zu viele Fehlversuche. Bitte warte 10 Minuten.');
            $payload = Cache::get($key);
            $matches = $request->user()->hasVerifiedEmail() && is_array($payload)
                && ($payload['expires_at'] ?? 0) > now()->timestamp
                && hash_equals($payload['email_hash'], hash('sha256', $request->user()->email))
                && hash_equals($payload['token_hash'], hash('sha256', $data['challenge_token']))
                && Hash::check($data['email_code'], $payload['code_hash']);
            if (! $matches) {
                RateLimiter::hit($rate, 600);

                return false;
            }
            Cache::forget($key);

            return true;
        });
        if (! $valid) {
            throw ValidationException::withMessages(['email_code' => 'Der Code ist falsch, abgelaufen oder bereits verwendet.']);
        }
    }

    private function key(Request $request, string $purpose): string
    {
        $binding = $request->bearerToken()
            ? 'token:'.$request->bearerToken()
            : 'session:'.($request->hasSession() ? $request->session()->getId() : 'none');

        return 'two-factor-change:'.$request->user()->id.':'.$purpose.':'.hash('sha256', $binding);
    }
}
