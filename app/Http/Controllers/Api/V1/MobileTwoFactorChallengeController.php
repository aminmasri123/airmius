<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\AdminTwoFactor;
use App\Support\MobileTwoFactorChallenge;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;

class MobileTwoFactorChallengeController extends Controller
{
    public function __invoke(
        Request $request,
        MobileTwoFactorChallenge $challenges,
        TwoFactorAuthenticationProvider $provider
    ) {
        $data = $request->validate([
            'challenge_token' => ['required', 'string', 'min:40', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
            'recovery_code' => ['nullable', 'string', 'max:100'],
            'email_code' => ['nullable', 'string', 'max:32'],
        ]);

        $challenge = $challenges->find($data['challenge_token']);
        $user = $challenge ? User::find($challenge['user_id']) : null;

        if (! $user || ! $user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'challenge_token' => ['Die Sicherheitsabfrage ist abgelaufen. Bitte melde dich erneut an.'],
            ]);
        }

        $valid = false;
        $recoveryCode = trim((string) ($data['recovery_code'] ?? ''));
        $emailCode = preg_replace('/\s+/', '', (string) ($data['email_code'] ?? ''));

        if ($recoveryCode !== '') {
            $matchingCode = collect($user->recoveryCodes())
                ->first(fn (string $code): bool => hash_equals($code, $recoveryCode));
            if ($matchingCode) {
                $user->replaceRecoveryCode($matchingCode);
                $valid = true;
            }
        } elseif ($emailCode !== '') {
            $valid = $user->hasVerifiedEmail()
                && $challenges->verifyEmailCode($data['challenge_token'], $emailCode);
        } else {
            $code = preg_replace('/\s+/', '', (string) ($data['code'] ?? ''));
            $valid = $code !== '' && $provider->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code
            );
        }

        if (! $valid) {
            event(new TwoFactorAuthenticationFailed($user));
            throw ValidationException::withMessages([
                $emailCode !== '' ? 'email_code' : 'code' => [
                    'Der Sicherheitscode ist ungültig oder abgelaufen. Bitte prüfe ihn und versuche es erneut.',
                ],
            ]);
        }

        event(new ValidTwoFactorAuthenticationCodeProvided($user));
        $challenges->forget($data['challenge_token']);

        return response()->json([
            'data' => [
                'token' => $user->createToken(
                    $challenge['device_name'],
                    AdminTwoFactor::requiredFor($user)
                        ? ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY, AdminTwoFactor::tokenAbility()]
                        : ['*']
                )->plainTextToken,
                'token_type' => 'Bearer',
                'user' => new UserResource($user->loadMissing(['roles', 'permissions'])),
            ],
        ]);
    }
}
