<?php

namespace App\Http\Controllers;

use App\Models\ConnectedSportAccount;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SportIntegrationController extends Controller
{
    public const PROVIDERS = [
        'google_fit' => [
            'label' => 'Google Fit',
            'status' => 'live_oauth',
            'description' => 'Verknuepfung ueber Google OAuth. Aktivitaetsimport wird als naechster Schritt auf den gespeicherten Tokens aufgebaut.',
            'scopes' => [
                'openid',
                'profile',
                'email',
                'https://www.googleapis.com/auth/fitness.activity.read',
                'https://www.googleapis.com/auth/fitness.body.read',
            ],
        ],
        'garmin' => [
            'label' => 'Garmin',
            'status' => 'partner_required',
            'description' => 'Garmin Health API benoetigt eine Anbieterfreigabe. Nutzer koennen Interesse markieren, bis die Partnerfreigabe aktiv ist.',
            'scopes' => ['activities', 'wellness'],
        ],
        'mi_fitness' => [
            'label' => 'Mi Fitness',
            'status' => 'partner_required',
            'description' => 'Mi Fitness hat keine einfache Standard-OAuth-Anbindung im Projekt. Die Verknuepfung wird als gewuenscht vorgemerkt.',
            'scopes' => ['activities'],
        ],
    ];

    public function redirect(Request $request, string $provider)
    {
        $definition = $this->definition($provider);

        if ($definition['status'] !== 'live_oauth') {
            ConnectedSportAccount::updateOrCreate(
                ['user_id' => $request->user()->id, 'provider' => $provider],
                [
                    'display_name' => $definition['label'],
                    'status' => 'requested',
                    'scopes' => $definition['scopes'],
                    'sync_summary' => ['message' => 'Verknuepfung vorgemerkt. Anbieterfreigabe/API-Zugang fehlt noch.'],
                ],
            );

            return back()->with('success', $definition['label'].' wurde vorgemerkt.');
        }

        session(['sport_oauth_provider' => $provider]);

        return Socialite::driver('google')
            ->redirectUrl($this->redirectUrl($provider))
            ->scopes($definition['scopes'])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        $definition = $this->definition($provider);
        abort_unless($definition['status'] === 'live_oauth', 404);

        $socialUser = Socialite::driver('google')
            ->redirectUrl($this->redirectUrl($provider))
            ->stateless()
            ->user();

        ConnectedSportAccount::updateOrCreate(
            ['user_id' => $request->user()->id, 'provider' => $provider],
            [
                'provider_user_id' => $socialUser->getId(),
                'display_name' => $definition['label'],
                'status' => 'connected',
                'scopes' => $definition['scopes'],
                'access_token' => $socialUser->token ?? null,
                'refresh_token' => $socialUser->refreshToken ?? null,
                'token_expires_at' => isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
                'sync_summary' => ['message' => 'Konto verbunden. Aktivitaetsimport ist vorbereitet.'],
            ],
        );

        return redirect()->route('auth.settings')->with('success', $definition['label'].' wurde verbunden.');
    }

    public function sync(Request $request, ConnectedSportAccount $account)
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $definition = self::PROVIDERS[$account->provider] ?? null;

        $account->update([
            'last_synced_at' => now(),
            'sync_summary' => [
                'message' => ($definition['status'] ?? null) === 'live_oauth'
                    ? 'OAuth-Verknuepfung vorhanden. Der konkrete Aktivitaetsimport wird nach API-Freigabe/Mapping aktiviert.'
                    : 'Noch keine Live-Schnittstelle aktiv. Anbieterfreigabe/API-Zugang fehlt.',
            ],
        ]);

        return back()->with('success', 'Synchronisationsstatus aktualisiert.');
    }

    public function destroy(Request $request, ConnectedSportAccount $account)
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $account->delete();

        return back()->with('success', 'Sport-App-Verknuepfung entfernt.');
    }

    private function definition(string $provider): array
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);

        return self::PROVIDERS[$provider];
    }

    private function redirectUrl(string $provider): string
    {
        if ($provider === 'google_fit') {
            return config('services.google_fit.redirect') ?: route('auth.sport-integrations.callback', $provider);
        }

        return route('auth.sport-integrations.callback', $provider);
    }
}
