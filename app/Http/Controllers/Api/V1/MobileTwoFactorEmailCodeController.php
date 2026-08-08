<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TwoFactorLoginCodeRequested;
use App\Support\MobileTwoFactorChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileTwoFactorEmailCodeController extends Controller
{
    public function __invoke(Request $request, MobileTwoFactorChallenge $challenges)
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string', 'min:40', 'max:255'],
        ]);

        $challenge = $challenges->find($data['challenge_token']);
        $user = $challenge ? User::find($challenge['user_id']) : null;

        if (! $user || ! $user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'challenge_token' => ['Die Sicherheitsabfrage ist abgelaufen. Bitte melde dich erneut an.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Für den E-Mail-Code wird eine verifizierte E-Mail-Adresse benötigt.'],
            ]);
        }

        $code = $challenges->issueEmailCode($data['challenge_token']);
        $user->notify(new TwoFactorLoginCodeRequested($code));

        return response()->json([
            'data' => [
                'message' => __('account_security.responses.email_code_sent'),
                'email' => Str::mask((string) $user->email, '*', 2, max(1, strlen((string) $user->email) - 5)),
                'expires_in' => MobileTwoFactorChallenge::EXPIRES_IN_SECONDS,
            ],
        ]);
    }
}
