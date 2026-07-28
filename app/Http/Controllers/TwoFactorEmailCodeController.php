<?php

namespace App\Http\Controllers;

use App\Notifications\TwoFactorLoginCodeRequested;
use App\Support\MobileTwoFactorChallenge;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class TwoFactorEmailCodeController extends Controller
{
    public function send(TwoFactorLoginRequest $request, MobileTwoFactorChallenge $codes)
    {
        $user = $request->challengedUser();

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Für den E-Mail-Code wird eine verifizierte E-Mail-Adresse benötigt.'],
            ]);
        }

        $code = $codes->issueEmailCode($this->challengeIdentifier($user));
        $user->notify(new TwoFactorLoginCodeRequested($code));

        return response()->json([
            'message' => 'Wir haben dir einen Sicherheitscode per E-Mail gesendet.',
            'expires_in' => MobileTwoFactorChallenge::EXPIRES_IN_SECONDS,
        ]);
    }

    public function store(
        Request $request,
        TwoFactorLoginRequest $twoFactorRequest,
        MobileTwoFactorChallenge $codes,
        StatefulGuard $guard
    ) {
        $data = $request->validate([
            'email_code' => ['required', 'string', 'max:32'],
        ]);
        $user = $twoFactorRequest->challengedUser();
        $code = preg_replace('/\s+/', '', $data['email_code']);

        if (! $user->hasVerifiedEmail()
            || ! $codes->verifyEmailCode($this->challengeIdentifier($user), $code)
        ) {
            event(new TwoFactorAuthenticationFailed($user));

            throw ValidationException::withMessages([
                'email_code' => ['Der Sicherheitscode ist ungültig oder abgelaufen.'],
            ]);
        }

        event(new ValidTwoFactorAuthenticationCodeProvided($user));
        $request->session()->forget('login.id');
        $guard->login($user, $twoFactorRequest->remember());
        $request->session()->regenerate();

        return app(TwoFactorLoginResponse::class)->toResponse($request);
    }

    private function challengeIdentifier(object $user): string
    {
        return 'web-user:'.$user->getAuthIdentifier();
    }
}
