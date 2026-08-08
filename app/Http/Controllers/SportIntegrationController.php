<?php

namespace App\Http\Controllers;

use App\Models\ConnectedSportAccount;
use App\Models\ConnectedSportActivity;
use App\Services\SportIntegrationSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SportIntegrationController extends Controller
{
    public const PROVIDERS = [
        'google_fit' => [
            'label_key' => 'sport_integrations.providers.google_fit.label',
            'route_key' => 'google-fit',
            'status' => 'live_oauth',
            'description_key' => 'sport_integrations.providers.google_fit.description',
            'scopes' => [
                'openid',
                'profile',
                'email',
                'https://www.googleapis.com/auth/fitness.activity.read',
                'https://www.googleapis.com/auth/fitness.location.read',
            ],
        ],
        'garmin' => [
            'label_key' => 'sport_integrations.providers.garmin.label',
            'status' => 'partner_required',
            'description_key' => 'sport_integrations.providers.garmin.description',
            'scopes' => ['activities', 'wellness'],
        ],
        'strava' => [
            'label_key' => 'sport_integrations.providers.strava.label',
            'status' => 'live_oauth',
            'description_key' => 'sport_integrations.providers.strava.description',
            'scopes' => ['read', 'activity:read_all'],
        ],
        'fitbit' => [
            'label_key' => 'sport_integrations.providers.fitbit.label',
            'status' => 'planned_oauth',
            'description_key' => 'sport_integrations.providers.fitbit.description',
            'scopes' => ['activity', 'profile'],
        ],
        'polar' => [
            'label_key' => 'sport_integrations.providers.polar.label',
            'status' => 'planned_oauth',
            'description_key' => 'sport_integrations.providers.polar.description',
            'scopes' => ['accesslink.read_all'],
        ],
        'mi_fitness' => [
            'label_key' => 'sport_integrations.providers.mi_fitness.label',
            'status' => 'partner_required',
            'description_key' => 'sport_integrations.providers.mi_fitness.description',
            'scopes' => ['activities'],
        ],
    ];

    public static function localizedProviders(): array
    {
        $providers = [];

        foreach (self::PROVIDERS as $key => $definition) {
            $providers[$key] = [
                ...$definition,
                'label' => __($definition['label_key']),
                'description' => __($definition['description_key']),
            ];
        }

        return $providers;
    }

    public function redirect(Request $request, string $provider)
    {
        $provider = $this->canonicalProvider($provider);
        $definition = $this->definition($provider);

        if ($definition['status'] !== 'live_oauth') {
            ConnectedSportAccount::updateOrCreate(
                ['user_id' => $request->user()->id, 'provider' => $provider],
                [
                    'display_name' => $definition['label'],
                    'status' => 'requested',
                    'scopes' => $definition['scopes'],
                    'sync_summary' => ['message' => __('sport_integrations.summary.requested')],
                ],
            );

            return back()->with('success', __('sport_integrations.flash.requested', ['provider' => $definition['label']]));
        }

        $state = Str::random(40);
        $validSince = now()->subMinutes(15)->timestamp;
        $states = array_filter(
            $request->session()->get('sport_oauth_states', []),
            fn ($timestamp) => (int) $timestamp >= $validSince,
        );
        $states[$state] = now()->timestamp;

        if (count($states) > 5) {
            $states = array_slice($states, -5, null, true);
        }

        $request->session()->put('sport_oauth_provider', $provider);
        $request->session()->put('sport_oauth_state', $state);
        $request->session()->put('sport_oauth_states', $states);
        Cache::put('sport_oauth_state:'.$state, [
            'user_id' => $request->user()->id,
            'provider' => $provider,
        ], now()->addMinutes(15));

        if (! $this->clientId($provider) || ! $this->clientSecret($provider)) {
            return back()->with('error', __('sport_integrations.flash.not_configured', ['provider' => $definition['label']]));
        }

        $targetUrl = $this->authorizeUrl($provider, $definition, $state);

        if ($request->boolean('debug')) {
            parse_str(parse_url($targetUrl, PHP_URL_QUERY) ?: '', $query);

            return response()->json([
                'provider' => $provider,
                'client_id' => $query['client_id'] ?? null,
                'redirect_uri' => $query['redirect_uri'] ?? null,
                'scope' => $query['scope'] ?? null,
                'target_url' => $targetUrl,
            ]);
        }

        return redirect()->away($targetUrl);
    }

    public function callback(Request $request, string $provider)
    {
        $provider = $this->canonicalProvider($provider);
        $definition = $this->definition($provider);
        abort_unless($definition['status'] === 'live_oauth', 404);

        abort_if($request->query('error'), 400, (string) $request->query('error'));

        $state = (string) $request->query('state');
        $states = $request->session()->get('sport_oauth_states', []);
        $legacyState = (string) $request->session()->get('sport_oauth_state', '');
        $cachedState = Cache::pull('sport_oauth_state:'.$state);
        $cachedStateIsValid = is_array($cachedState)
            && (int) ($cachedState['user_id'] ?? 0) === (int) $request->user()->id
            && ($cachedState['provider'] ?? null) === $provider;
        $stateIsValid = $state !== ''
            && (array_key_exists($state, $states) || hash_equals($legacyState, $state) || $cachedStateIsValid);

        if (! $stateIsValid) {
            Log::warning('Sport OAuth state mismatch.', [
                'provider' => $provider,
                'user_id' => $request->user()?->id,
                'has_state' => $state !== '',
                'session_states' => array_keys($states),
                'legacy_state_present' => $legacyState !== '',
                'cached_state_present' => is_array($cachedState),
            ]);

            return redirect()
                ->route('auth.settings')
                ->with('error', __('sport_integrations.flash.expired', ['provider' => $definition['label']]));
        }

        unset($states[$state]);
        $request->session()->put('sport_oauth_states', $states);

        if (hash_equals($legacyState, $state)) {
            $request->session()->forget('sport_oauth_state');
        }

        $tokenResponse = Http::asForm()->post($this->tokenUrl($provider), $this->tokenPayload($provider, (string) $request->query('code')));

        if ($tokenResponse->failed()) {
            Log::warning('Sport OAuth token exchange failed.', [
                'provider' => $provider,
                'status' => $tokenResponse->status(),
                'body' => Str::limit($tokenResponse->body(), 1000),
                'redirect_uri' => $this->redirectUrl($provider),
                'user_id' => $request->user()?->id,
            ]);

            $googleError = $tokenResponse->json('error_description')
                ?: $tokenResponse->json('error')
                ?: __('sport_integrations.errors.unknown_provider');

            return redirect()
                ->route('auth.settings')
                ->with('error', __('sport_integrations.flash.token_exchange_failed', [
                    'provider' => $definition['label'],
                    'error' => $googleError,
                ]));
        }

        $token = $tokenResponse->json();
        $profile = $this->profileFromToken($provider, $token);

        ConnectedSportAccount::updateOrCreate(
            ['user_id' => $request->user()->id, 'provider' => $provider],
            [
                'provider_user_id' => $profile['id'] ?? $profile['sub'] ?? $profile['email'] ?? null,
                'display_name' => $definition['label'],
                'status' => 'connected',
                'scopes' => isset($token['scope']) ? explode(' ', $token['scope']) : $definition['scopes'],
                'access_token' => $token['access_token'] ?? null,
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_expires_at' => $this->tokenExpiresAt($provider, $token),
                'sync_summary' => ['message' => __('sport_integrations.summary.connected')],
            ],
        );

        return redirect()->route('auth.settings')->with('success', __('sport_integrations.flash.connected', ['provider' => $definition['label']]));
    }

    public function sync(Request $request, ConnectedSportAccount $account, SportIntegrationSyncService $syncService)
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $result = $syncService->sync($account);

        return back()->with(($result['ok'] ?? false) ? 'success' : 'error', $result['message']);
    }

    public function storeActivity(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'activity_type' => ['nullable', 'string', 'max:80'],
            'started_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $imagePath = $request->file('image')?->store('sport-activities', 'public');
        $durationMinutes = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;
        $distanceKm = isset($data['distance_km']) ? (float) $data['distance_km'] : null;

        ConnectedSportActivity::create([
            'user_id' => $request->user()->id,
            'provider' => 'manual',
            'provider_activity_id' => 'manual-'.Str::uuid(),
            'activity_type' => $data['activity_type'] ?: 'Training',
            'title' => $data['title'],
            'started_at' => $data['started_at'],
            'duration_seconds' => $durationMinutes !== null ? $durationMinutes * 60 : null,
            'distance_meters' => $distanceKm !== null ? (int) round($distanceKm * 1000) : null,
            'calories' => $data['calories'] ?? null,
            'image_path' => $imagePath,
            'metrics' => [
                'source_kind' => 'manual_entry',
                'created_manually_at' => now()->toIso8601String(),
            ],
        ]);

        return back()->with('success', __('sport_integrations.flash.activity_created'));
    }

    public function destroy(Request $request, ConnectedSportAccount $account)
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $account->delete();

        return back()->with('success', __('sport_integrations.flash.disconnected'));
    }

    public function destroyActivity(Request $request, ConnectedSportActivity $activity)
    {
        abort_unless($activity->user_id === $request->user()->id, 403);

        if ($activity->image_path) {
            Storage::disk('public')->delete($activity->image_path);
        }

        $activity->delete();

        return back()->with('success', __('sport_integrations.flash.activity_deleted'));
    }

    public function updateActivity(Request $request, ConnectedSportActivity $activity)
    {
        abort_unless($activity->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $activity->update([
            'title' => $data['title'],
        ]);

        return back()->with('success', __('sport_integrations.flash.activity_renamed'));
    }

    public function destroyActivities(Request $request)
    {
        $activities = ConnectedSportActivity::query()
            ->where('user_id', $request->user()->id)
            ->get(['id', 'image_path']);
        $deleted = $activities->count();

        $activities
            ->pluck('image_path')
            ->filter()
            ->each(fn ($path) => Storage::disk('public')->delete($path));

        ConnectedSportActivity::query()
            ->whereKey($activities->pluck('id'))
            ->delete();

        return back()->with('success', __('sport_integrations.flash.activities_deleted', ['count' => $deleted]));
    }

    private function definition(string $provider): array
    {
        $provider = $this->canonicalProvider($provider);

        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);

        return self::localizedProviders()[$provider];
    }

    private function canonicalProvider(string $provider): string
    {
        return str_replace('-', '_', $provider);
    }

    private function redirectUrl(string $provider): string
    {
        if ($provider === 'google_fit') {
            return config('services.google_fit.redirect') ?: route('auth.sport-integrations.callback', self::PROVIDERS[$provider]['route_key']);
        }

        if ($provider === 'strava') {
            return config('services.strava.redirect') ?: route('auth.sport-integrations.callback', $provider);
        }

        return route('auth.sport-integrations.callback', $provider);
    }

    private function clientId(string $provider): ?string
    {
        return match ($provider) {
            'google_fit' => config('services.google_fit.client_id'),
            'strava' => config('services.strava.client_id'),
            default => null,
        };
    }

    private function clientSecret(string $provider): ?string
    {
        return match ($provider) {
            'google_fit' => config('services.google_fit.client_secret'),
            'strava' => config('services.strava.client_secret'),
            default => null,
        };
    }

    private function authorizeUrl(string $provider, array $definition, string $state): string
    {
        if ($provider === 'strava') {
            return 'https://www.strava.com/oauth/authorize?'.http_build_query([
                'client_id' => $this->clientId($provider),
                'redirect_uri' => $this->redirectUrl($provider),
                'response_type' => 'code',
                'scope' => implode(',', $definition['scopes']),
                'state' => $state,
                'approval_prompt' => 'auto',
            ], '', '&', PHP_QUERY_RFC3986);
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId($provider),
            'redirect_uri' => $this->redirectUrl($provider),
            'response_type' => 'code',
            'scope' => implode(' ', $definition['scopes']),
            'state' => $state,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'select_account consent',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function tokenUrl(string $provider): string
    {
        return match ($provider) {
            'strava' => 'https://www.strava.com/oauth/token',
            default => 'https://oauth2.googleapis.com/token',
        };
    }

    private function tokenPayload(string $provider, string $code): array
    {
        return [
            'client_id' => $this->clientId($provider),
            'client_secret' => $this->clientSecret($provider),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUrl($provider),
        ];
    }

    private function profileFromToken(string $provider, array $token): array
    {
        if ($provider === 'strava') {
            $athlete = $token['athlete'] ?? [];

            return [
                'id' => isset($athlete['id']) ? (string) $athlete['id'] : null,
                'name' => trim(($athlete['firstname'] ?? '').' '.($athlete['lastname'] ?? '')),
            ];
        }

        $profileResponse = Http::withToken($token['access_token'] ?? '')->get('https://www.googleapis.com/oauth2/v3/userinfo');

        return $profileResponse->successful() ? $profileResponse->json() : [];
    }

    private function tokenExpiresAt(string $provider, array $token)
    {
        if ($provider === 'strava' && isset($token['expires_at'])) {
            return now()->setTimestamp((int) $token['expires_at']);
        }

        return isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null;
    }
}
