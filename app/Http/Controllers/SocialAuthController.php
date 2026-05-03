<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'microsoft'];

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        return Socialite::driver($provider)
            ->redirectUrl($this->redirectUrl($provider))
            ->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $socialUser = Socialite::driver($provider)
            ->redirectUrl($this->redirectUrl($provider))
            ->stateless()
            ->user();

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($account) {
            $this->updateAccount($account, $socialUser);
            Auth::login($account->user, remember: true);

            return redirect()->intended(route('auth.dashboard'));
        }

        $user = Auth::user() ?: $this->userForSocialAccount($socialUser);

        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $socialUser->getId(),
        ]);

        $this->updateAccount($account, $socialUser);
        Auth::login($user, remember: true);

        return redirect()->intended(route('auth.dashboard'));
    }

    private function userForSocialAccount($socialUser): User
    {
        $email = $socialUser->getEmail();

        if ($email && ($user = User::where('email', $email)->first())) {
            return $user;
        }

        $name = $socialUser->getName() ?: $socialUser->getNickname() ?: 'Airmius User';
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');

        return User::create([
            'name' => $name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email ?: Str::lower(Str::random(16)).'@social.airmius.local',
            'email_verified_at' => now(),
            'password' => Hash::make(Str::password(32)),
            'country' => 'DE',
        ]);
    }

    private function updateAccount(SocialAccount $account, $socialUser): void
    {
        $account->update([
            'email' => $socialUser->getEmail(),
            'name' => $socialUser->getName() ?: $socialUser->getNickname(),
            'avatar_url' => $socialUser->getAvatar(),
            'access_token' => $socialUser->token ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'token_expires_at' => isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);
    }

    private function redirectUrl(string $provider): string
    {
        return config("services.{$provider}.redirect") ?: route('social-auth.callback', $provider);
    }
}
