<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceToken;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobilePushDeviceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:128'],
            'platform' => ['required', Rule::in(['ios', 'android', 'web', 'unknown'])],
            'provider' => ['required', Rule::in(['apns', 'fcm', 'expo', 'web'])],
            'token' => ['required', 'string', 'max:500'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'build_number' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', Rule::in(['de', 'en', 'fr', 'ar'])],
            'timezone' => ['nullable', 'string', 'max:80'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'max:80'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['boolean'],
        ]);

        $tokenHash = hash('sha256', $data['token']);

        $device = MobileDeviceToken::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'device_id' => $data['device_id'],
            ],
            [
                'platform' => $data['platform'],
                'provider' => $data['provider'],
                'token' => $data['token'],
                'token_hash' => $tokenHash,
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? $request->headers->get('X-Airmius-App-Version'),
                'build_number' => $data['build_number'] ?? $request->headers->get('X-Airmius-Build'),
                'locale' => $data['locale'] ?? $request->headers->get('X-App-Locale', 'de'),
                'timezone' => $data['timezone'] ?? $request->headers->get('X-Airmius-Timezone'),
                'channels' => array_values(array_unique($data['channels'] ?? [])),
                'permissions' => $data['permissions'] ?? [],
                'last_seen_at' => now(),
                'disabled_at' => null,
            ]
        );

        MobileDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', $data['provider'])
            ->where('token_hash', $tokenHash)
            ->whereKeyNot($device->id)
            ->update([
                'disabled_at' => now(),
                'last_seen_at' => now(),
            ]);

        return response()->json([
            'data' => [
                'status' => $device->wasRecentlyCreated ? 'registered' : 'updated',
                'device' => $this->devicePayload($device->fresh()),
                'active_devices_count' => $this->activeDeviceCount($request),
            ],
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $deviceId)
    {
        $device = MobileDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('device_id', $deviceId)
            ->firstOrFail();

        $device->update([
            'disabled_at' => now(),
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'disabled' => true,
                'device_id' => $deviceId,
                'active_devices_count' => $this->activeDeviceCount($request),
            ],
        ]);
    }

    protected function devicePayload(MobileDeviceToken $device): array
    {
        return [
            'device_id' => $device->device_id,
            'platform' => $device->platform,
            'provider' => $device->provider,
            'token_fingerprint' => substr($device->token_hash, 0, 12),
            'device_name' => $device->device_name,
            'app_version' => $device->app_version,
            'build_number' => $device->build_number,
            'locale' => $device->locale,
            'timezone' => $device->timezone,
            'channels' => $device->channels ?? [],
            'permissions' => $device->permissions ?? [],
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'disabled_at' => $device->disabled_at?->toIso8601String(),
        ];
    }

    protected function activeDeviceCount(Request $request): int
    {
        return MobileDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('disabled_at')
            ->count();
    }
}
