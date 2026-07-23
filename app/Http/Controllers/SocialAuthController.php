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
            if ($this->isAllowedMobileReturnUrl($request->string('return_url')->toString())) {
                $request->session()->put('social_auth.mobile_return_url', $request->string('return_url')->toString());
            }
        } else {
            $request->session()->forget(['social_auth.mobile', 'social_auth.mobile_locale', 'social_auth.mobile_return_url']);
        }

        if ($request->filled('redirect')) {
            $request->session()->put('url.intended', url($request->string('redirect')->toString()));
        }

        $driver = Socialite::driver($provider)
            ->redirectUrl($this->redirectUrl($provider))
            ->scopes($this->scopes($provider));

        if ($request->boolean('mobile')) {
            $driver->with([
                'state' => $this->mobileState(
                    $request->string('locale')->toString() ?: 'de',
                    $this->isAllowedMobileReturnUrl($request->string('return_url')->toString())
                        ? $request->string('return_url')->toString()
                        : null,
                ),
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
        $mobileReturnUrl = $this->mobileReturnUrl($request);

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($account) {
            $this->updateAccount($account, $socialUser);
            Auth::login($account->user, remember: true);

            if ($isMobileCallback) {
                return $this->mobileCallbackRedirect($account->user, $provider, $mobileLocale, $mobileReturnUrl);
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
            return $this->mobileCallbackRedirect($user, $provider, $mobileLocale, $mobileReturnUrl);
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

    private function mobileCallbackRedirect(User $user, string $provider, string $locale, ?string $returnUrl)
    {
        $token = $user->createToken('mobile-'.$provider)->plainTextToken;
        $payload = [
            'token' => $token,
            'token_type' => 'Bearer',
            'provider' => $provider,
            'locale' => $locale,
        ];
        $query = http_build_query($payload);

        if ($returnUrl && $this->isAllowedMobileReturnUrl($returnUrl)) {
            return redirect()->away($this->appendQuery($returnUrl, $payload));
        }

        $deepLink = 'https://app.airmius.com/auth/callback?'.$query;
        $customSchemeLink = 'airmius://auth/callback?'.$query;
        $androidIntent = 'intent://app.airmius.com/auth/callback?'.$query.'#Intent;scheme=https;package=com.airmius.app;S.browser_fallback_url='.rawurlencode($customSchemeLink).';end';
        $dashboardUrl = route('auth.dashboard');

        return response($this->mobileCallbackHtml($provider, $deepLink, $customSchemeLink, $androidIntent, $dashboardUrl))
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
        $json = base64_decode($this->base64UrlDecode($encoded), true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        $locale = is_array($payload) ? ($payload['locale'] ?? null) : null;

        return is_string($locale) && $locale !== '' ? $locale : 'de';
    }

    private function mobileReturnUrl(Request $request): ?string
    {
        $sessionReturnUrl = $request->session()->pull('social_auth.mobile_return_url', null);
        if (is_string($sessionReturnUrl) && $this->isAllowedMobileReturnUrl($sessionReturnUrl)) {
            return $sessionReturnUrl;
        }

        $payload = $this->mobileStatePayload($request);
        $returnUrl = is_array($payload) ? ($payload['return_url'] ?? null) : null;

        return is_string($returnUrl) && $this->isAllowedMobileReturnUrl($returnUrl) ? $returnUrl : null;
    }

    private function mobileState(string $locale, ?string $returnUrl = null): string
    {
        $payload = json_encode([
            'mobile' => true,
            'locale' => $locale ?: 'de',
            'return_url' => $returnUrl,
        ]);

        return self::MOBILE_STATE_PREFIX.rtrim(strtr(base64_encode((string) $payload), '+/', '-_'), '=');
    }

    private function mobileStatePayload(Request $request): ?array
    {
        $state = (string) $request->query('state');
        if (! str_starts_with($state, self::MOBILE_STATE_PREFIX)) {
            return null;
        }

        $encoded = Str::after($state, self::MOBILE_STATE_PREFIX);
        $json = base64_decode($this->base64UrlDecode($encoded), true);
        $payload = is_string($json) ? json_decode($json, true) : null;

        return is_array($payload) ? $payload : null;
    }

    private function base64UrlDecode(string $encoded): string
    {
        $base64 = strtr($encoded, '-_', '+/');
        $padding = strlen($base64) % 4;

        return $padding ? $base64.str_repeat('=', 4 - $padding) : $base64;
    }

    private function isAllowedMobileReturnUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? '';

        if ($scheme === 'airmius') {
            return $host === 'auth' && $path === '/callback';
        }

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host)) {
            return false;
        }

        return in_array($host, ['127.0.0.1', 'localhost', 'app.airmius.com'], true);
    }

    private function appendQuery(string $url, array $query): string
    {
        $fragment = '';
        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($url, $fragmentPosition);
            $url = substr($url, 0, $fragmentPosition);
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($query).$fragment;
    }

    private function mobileCallbackHtml(string $provider, string $deepLink, string $customSchemeLink, string $androidIntent, string $dashboardUrl): string
    {
        $providerName = $provider === 'microsoft' ? 'Microsoft' : 'Google';
        $deepLinkAttribute = e($deepLink);
        $customSchemeAttribute = e($customSchemeLink);
        $androidIntentAttribute = e($androidIntent);
        $dashboardUrl = e($dashboardUrl);
        $deepLinkJson = json_encode($deepLink, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        $customSchemeJson = json_encode($customSchemeLink, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        $androidIntentJson = json_encode($androidIntent, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

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
        <p>{$providerName} hat dich angemeldet. Oeffne jetzt die Airmius App, um den Login abzuschliessen.</p>
        <div class="actions">
            <a class="primary" id="open-app" href="$deepLinkAttribute" data-intent-link="$androidIntentAttribute" data-custom-link="$customSchemeAttribute">Airmius App oeffnen</a>
            <a href="$dashboardUrl">Im Browser weiter</a>
        </div>
        <small>Wenn nichts passiert, tippe auf "Airmius App oeffnen". Airmius nutzt zuerst den HTTPS-App-Link und versucht auf Android zusaetzlich den App-Intent.</small>
    </main>
    <script>
        (function () {
            var deepLink = $deepLinkJson;
            var customSchemeLink = $customSchemeJson;
            var androidIntent = $androidIntentJson;
            var isAndroid = /Android/i.test(navigator.userAgent);
            var openApp = document.getElementById("open-app");
            if (openApp && isAndroid) {
                openApp.addEventListener("click", function (event) {
                    event.preventDefault();
                    window.location.href = customSchemeLink;
                    setTimeout(function () {
                        window.location.href = androidIntent;
                    }, 900);
                });
            }
            setTimeout(function () {
                window.location.href = isAndroid ? customSchemeLink : deepLink;
            }, 250);
        })();
    </script>
</body>
</html>
HTML;
    }
}
