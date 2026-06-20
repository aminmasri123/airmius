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
        $query = http_build_query([
            'token' => $token,
            'token_type' => 'Bearer',
            'provider' => $provider,
            'locale' => $locale,
        ]);
        $deepLink = 'airmius://auth/callback?'.$query;
        $androidIntent = 'intent://auth/callback?'.$query.'#Intent;scheme=airmius;package=com.airmius.app;end';
        $dashboardUrl = route('auth.dashboard');

        return response($this->mobileCallbackHtml($deepLink, $androidIntent, $dashboardUrl))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
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

    private function mobileCallbackHtml(string $deepLink, string $androidIntent, string $dashboardUrl): string
    {
        $deepLink = e($deepLink);
        $androidIntent = e($androidIntent);
        $dashboardUrl = e($dashboardUrl);

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Airmius App oeffnen</title>
    <style>
        :root { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #0c1016; color: #f4f7fb; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        main { width: min(92vw, 520px); border: 1px solid #263241; border-radius: 18px; background: #121821; padding: 28px; box-shadow: 0 18px 55px rgba(0,0,0,.28); }
        h1 { margin: 0 0 10px; font-size: 28px; line-height: 1.1; }
        p { margin: 0 0 18px; color: #aab6c5; line-height: 1.5; }
        a, button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; border-radius: 12px; border: 1px solid #60a5fa; padding: 0 16px; color: #f4f7fb; background: #1d2633; font-weight: 800; text-decoration: none; cursor: pointer; }
        .primary { background: #60a5fa; color: #0c1016; border-color: #60a5fa; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; }
        small { display: block; margin-top: 18px; color: #7f8da3; line-height: 1.45; }
    </style>
</head>
<body>
    <main>
        <h1>Login bestaetigt</h1>
        <p>Google hat dich angemeldet. Oeffne jetzt die Airmius App, um den Login abzuschliessen.</p>
        <div class="actions">
            <a class="primary" id="open-app" href="$deepLink">Airmius App oeffnen</a>
            <a href="$dashboardUrl">Im Browser weiter</a>
        </div>
        <small>Wenn nichts passiert, tippe auf "Airmius App oeffnen". Auf Android versucht Airmius zusaetzlich den App-Intent.</small>
    </main>
    <script>
        (function () {
            var deepLink = "$deepLink";
            var androidIntent = "$androidIntent";
            var isAndroid = /Android/i.test(navigator.userAgent);
            setTimeout(function () {
                window.location.href = isAndroid ? androidIntent : deepLink;
            }, 250);
        })();
    </script>
</body>
</html>
HTML;
    }
}
