<?php

namespace App\Http\Controllers;

use App\Notifications\AccountDeletionCodeRequested;
use App\Notifications\AccountDeletionCompleted;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Jetstream\Contracts\DeletesUsers;

class AccountDeletionController extends Controller
{
    private const SESSION_KEY = 'account_deletion_confirmation';

    public function sendCode(Request $request): RedirectResponse
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
                    ? 'Die eingegebene E-Mail-Adresse stimmt nicht mit deinem Konto Überein.'
                    : 'Das eingegebene Passwort ist nicht korrekt.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        Notification::route('mail', $user->email)
            ->notify(new AccountDeletionCodeRequested($code));

        return back()->with('success', 'Wir haben dir einen Bestätigungscode per E-Mail gesendet.');
    }

    public function destroy(Request $request, StatefulGuard $guard)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ], [
            'code.required' => 'Bitte gib den Code aus deiner E-Mail ein.',
        ]);

        $confirmation = $request->session()->get(self::SESSION_KEY);
        $user = $request->user();

        if (! $confirmation
            || (int) ($confirmation['user_id'] ?? 0) !== (int) $user->id
            || now()->timestamp > (int) ($confirmation['expires_at'] ?? 0)
            || ! Hash::check((string) $request->code, (string) ($confirmation['code_hash'] ?? ''))
        ) {
            throw ValidationException::withMessages([
                'code' => 'Der Code ist ungültig oder abgelaufen. Bitte fordere einen neuen Code an.',
            ]);
        }

        $email = $user->email;
        $name = $user->name;

        app(DeletesUsers::class)->delete($user->fresh());

        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        try {
            Notification::route('mail', $email)
                ->notify(new AccountDeletionCompleted($name));
        } catch (\Throwable $exception) {
            Log::warning('Account deletion confirmation email could not be sent.', [
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);
        }

        return Inertia::location(url('/'));
    }
}
