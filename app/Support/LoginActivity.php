<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\LoginSuccessfulNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;

class LoginActivity
{
    public static function record(User $user, Request $request, bool $success): void
    {
        $mobile = $request->is('api/*');
        $agent = strtolower((string) $request->userAgent());
        $device = $mobile ? 'Airmius App' : match (true) {
            str_contains($agent, 'edg/') => 'Edge',
            str_contains($agent, 'firefox/') => 'Firefox',
            str_contains($agent, 'chrome/') => 'Chrome',
            str_contains($agent, 'safari/') => 'Safari',
            default => 'Browser',
        };
        $key = 'login-activity:'.$user->id;
        Cache::lock($key.':lock', 5)->block(3, function () use ($key, $success, $device) {
            $cutoff = now()->subDays(30)->toIso8601String();
            $rows = array_filter(Cache::get($key, []), fn ($row) => $row['at'] >= $cutoff);
            array_unshift($rows, ['at' => now()->toIso8601String(), 'success' => $success, 'device' => $device]);
            Cache::put($key, array_slice($rows, 0, 30), now()->addDays(30));
        });
        if (! $success) {
            return;
        }
        $id = $mobile ? $request->input('installation_id') : $request->cookie('airmius_device');
        if (! is_string($id) || ! preg_match('/\A[a-f0-9]{64}\z/', $id)) {
            $id = bin2hex(random_bytes(32));
            if ($mobile) {
                // Older clients cannot retain an installation key. Bound their reminders.
                if (! Cache::add('legacy-login-alert:'.$user->id.':'.hash('sha256', $request->ip().'|'.$agent), true, now()->addDay())) {
                    return;
                }
            }
            if (! $mobile) {
                Cookie::queue('airmius_device', $id, 60 * 24 * 180, '/', null, $request->isSecure(), true, false, 'lax');
            }
        }
        $known = 'login-device:'.$user->id.':'.hash('sha256', $id);
        $ip = inet_pton($request->ip() ?: '127.0.0.1');
        $network = hash_hmac('sha256', substr((string) $ip, 0, strlen((string) $ip) === 4 ? 3 : 6), config('app.key'));
        $previous = Cache::get($known);
        $context = ['network' => $network, 'browser' => $device];
        $changed = $previous && $previous['network'] !== $network && $previous['browser'] !== $device;
        if ((! $previous || $changed) && Cache::add($known.':alert:'.$network.':'.$device, true, now()->addDay())) {
            try {
                $user->notify(new LoginSuccessfulNotification(null, $device, now()->format('d.m.Y H:i T')));
            } catch (\Throwable $exception) {
                Cache::forget($known.':alert:'.$network.':'.$device);
                report($exception);
                return;
            }
        }
        Cache::put($known, $context, now()->addDays(180));
    }

    public static function recent(User $user): array
    {
        $cutoff = now()->subDays(30)->toIso8601String();
        return array_values(array_filter(Cache::get('login-activity:'.$user->id, []), fn ($row) => $row['at'] >= $cutoff));
    }
}
