<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'microsoft'];

    public function redirect(Request $request, string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        if ($request->filled('redirect')) {
            $request->session()->put('url.intended', url($request->string('redirect')->toString()));
        }

        return Socialite::driver($provider)
            ->redirectUrl($this->redirectUrl($provider))
            ->scopes($this->scopes($provider))
            ->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $socialUser = Socialite::driver($provider)
            ->redirectUrl($this->redirectUrl($provider))
            ->scopes($this->scopes($provider))
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

        $created = false;
        $user = Auth::user() ?: $this->userForSocialAccount($socialUser, $created);

        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $socialUser->getId(),
        ]);

        $this->updateAccount($account, $socialUser);
        Auth::login($user, remember: true);

        if ($created) {
            app(CommerceCheckoutController::class)->trackAttributedAdConversion(request(), 'registration', 0, [
                'registered_user_id' => $user->id,
                'provider' => $provider,
                'country' => $user->country,
            ]);
        }

        return redirect()->intended(route('auth.dashboard'));
    }

    private function userForSocialAccount($socialUser, bool &$created = false): User
    {
        $email = $this->emailFor($socialUser);

        if ($email && ($user = User::where('email', $email)->first())) {
            return $user;
        }

        $name = $socialUser->getName() ?: $socialUser->getNickname() ?: 'Airmius User';
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');

        $created = true;

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
            'email' => $this->emailFor($socialUser),
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

    private function scopes(string $provider): array
    {
        return match ($provider) {
            'google' => ['openid', 'profile', 'email'],
            'microsoft' => ['openid', 'profile', 'email', 'User.Read'],
            default => [],
        };
    }

    private function emailFor($socialUser): ?string
    {
        return $socialUser->getEmail()
            ?: data_get($socialUser->user, 'email')
            ?: data_get($socialUser->user, 'mail')
            ?: data_get($socialUser->user, 'userPrincipalName');
    }
}
