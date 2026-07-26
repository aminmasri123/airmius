<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Notifications\MobilePasswordResetRequested;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Password::broker()->sendResetLink(
            ['email' => mb_strtolower(trim($data['email']))],
            fn (User $user, string $token) => $user->notify(
                new MobilePasswordResetRequested($token)
            )
        );

        // Always return the same response so the endpoint cannot be used to
        // discover which email addresses have an Airmius account.
        return response()->json([
            'data' => [
                'message' => 'Wenn ein Konto zu dieser E-Mail-Adresse existiert, wurde ein sicherer Reset-Link gesendet.',
            ],
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', PasswordRule::default(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = Password::broker()->reset(
            [
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['Der Reset-Link ist ungültig oder abgelaufen. Bitte fordere einen neuen Link an.'],
            ]);
        }

        return response()->json([
            'data' => [
                'message' => 'Dein Passwort wurde zurückgesetzt. Du kannst dich jetzt anmelden.',
            ],
        ]);
    }
}
