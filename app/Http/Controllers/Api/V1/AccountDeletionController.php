<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Notifications\AccountDeletionCodeRequested;
use App\Notifications\AccountDeletionCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesUsers;

class AccountDeletionController extends Controller
{
    public function sendCode(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Bitte bestätige zuerst deine Identität.',
        ]);

        $user = $request->user();
        $usesSocialLogin = $user->socialAccounts()->exists();

        $confirmed = $usesSocialLogin
            ? hash_equals(strtolower((string) $user->email), strtolower((string) $request->password))
            : Hash::check($request->password, $user->password);

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'password' => $usesSocialLogin
                    ? 'Die eingegebene E-Mail-Adresse stimmt nicht mit deinem Konto überein.'
                    : 'Das eingegebene Passwort ist nicht korrekt.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey((int) $user->id), [
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15)->timestamp,
        ], now()->addMinutes(15));

        Notification::route('mail', $user->email)
            ->notify(new AccountDeletionCodeRequested($code));

        return response()->json([
            'data' => [
                'message' => 'Wir haben dir einen Bestätigungscode per E-Mail gesendet.',
                'expires_in_minutes' => 15,
            ],
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ], [
            'code.required' => 'Bitte gib den Code aus deiner E-Mail ein.',
        ]);

        $user = $request->user();
        $confirmation = Cache::get($this->cacheKey((int) $user->id));

        if (! is_array($confirmation)
            || now()->timestamp > (int) ($confirmation['expires_at'] ?? 0)
            || ! Hash::check((string) $request->code, (string) ($confirmation['code_hash'] ?? ''))
        ) {
            throw ValidationException::withMessages([
                'code' => 'Der Code ist ungültig oder abgelaufen. Bitte fordere einen neuen Code an.',
            ]);
        }

        $email = $user->email;
        $name = $user->name;

        Cache::forget($this->cacheKey((int) $user->id));
        $user->currentAccessToken()?->delete();

        app(DeletesUsers::class)->delete($user->fresh());

        try {
            Notification::route('mail', $email)
                ->notify(new AccountDeletionCompleted($name));
        } catch (\Throwable $exception) {
            Log::warning('Mobile account deletion confirmation email could not be sent.', [
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'data' => [
                'message' => 'Dein Konto wurde gelöscht.',
            ],
        ]);
    }

    private function cacheKey(int $userId): string
    {
        return "mobile_account_deletion_confirmation:{$userId}";
    }
}
