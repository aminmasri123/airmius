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
    private const MOBILE_STATE_PREFIX = 'airmius-mobile:';

    public function redirect(Request $request, string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        if ($request->boolean('mobile')) {
            $request->session()->put('social_auth.mobile', true);
            $request->session()->put('social_auth.mobile_locale', $request->string('locale')->toString() ?: 'de');
        } else {
            $request->session()->forget(['social_auth.mobile', 'social_auth.mobile_locale']);
        }

        if ($request->filled('redirect')) {
            $request->session()->put('url.intended', url($request->string('redirect')->toString()));
        }

        $driver = Socialite::driver($provider)
            ->redirectUrl($this->redirectUrl($provider))
            ->scopes($this->scopes($provider));

        if ($request->boolean('mobile')) {
            $driver->with([
                'state' => $this->mobileState($request->string('locale')->toString() ?: 'de'),
            ]);
        }

        return $driver->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $socialUser = Socialite::driver($provider)
            ->redirectUrl($this->redirectUrl($provider))
            ->scopes($this->scopes($provider))
            ->stateless()
            ->user();
        $isMobileCallback = $this->isMobileCallback($request);
        $mobileLocale = $this->mobileLocale($request);

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($account) {
            $this->updateAccount($account, $socialUser);
            Auth::login($account->user, remember: true);

            if ($isMobileCallback) {
                return $this->mobileCallbackRedirect($account->user, $provider, $mobileLocale);
            }

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

        if ($isMobileCallback) {
            return $this->mobileCallbackRedirect($user, $provider, $mobileLocale);
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

    private function mobileCallbackRedirect(User $user, string $provider, string $locale)
    {
        $token = $user->createToken('mobile-'.$provider)->plainTextToken;

        return redirect()->away('airmius://auth/callback?'.http_build_query([
            'token' => $token,
            'token_type' => 'Bearer',
            'provider' => $provider,
            'locale' => $locale,
        ]));
    }

    private function isMobileCallback(Request $request): bool
    {
        return $request->session()->pull('social_auth.mobile', false)
            || str_starts_with((string) $request->query('state'), self::MOBILE_STATE_PREFIX);
    }

    private function mobileLocale(Request $request): string
    {
        $sessionLocale = $request->session()->pull('social_auth.mobile_locale', null);
        if (is_string($sessionLocale) && $sessionLocale !== '') {
            return $sessionLocale;
        }

        $state = (string) $request->query('state');
        if (! str_starts_with($state, self::MOBILE_STATE_PREFIX)) {
            return 'de';
        }

        $encoded = Str::after($state, self::MOBILE_STATE_PREFIX);
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        $locale = is_array($payload) ? ($payload['locale'] ?? null) : null;

        return is_string($locale) && $locale !== '' ? $locale : 'de';
    }

    private function mobileState(string $locale): string
    {
        $payload = json_encode([
            'mobile' => true,
            'locale' => $locale ?: 'de',
        ]);

        return self::MOBILE_STATE_PREFIX.rtrim(strtr(base64_encode((string) $payload), '+/', '-_'), '=');
    }
}
