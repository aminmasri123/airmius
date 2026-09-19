<?php

namespace App\Console\Commands;

use App\Models\NutritionGoal;
use App\Models\NutritionMeal;
use App\Models\TrainingLog;
use App\Support\AppNotification;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Throwable;

class SendWaterReminders extends Command
{
    protected $signature = 'airmius:send-water-reminders';

    protected $description = 'Send opted-in drinking reminders during each user’s daytime window';

    public function handle(): int
    {
        $sent = 0;

        NutritionGoal::query()
            ->with('user:id,language,timezone,notification_channels,notification_quiet_time')
            ->where('water_reminders_per_day', '>', 0)
            ->chunkById(100, function ($goals) use (&$sent): void {
                foreach ($goals as $goal) {
                    $user = $goal->user;
                    if (! $user) {
                        continue;
                    }

                    try {
                        $timezone = new DateTimeZone($user->timezone ?: config('app.timezone'));
                    } catch (Throwable) {
                        $timezone = new DateTimeZone(config('app.timezone'));
                    }

                    $now = CarbonImmutable::now($timezone);
                    $count = min(3, max(0, (int) $goal->water_reminders_per_day));
                    $start = (int) $goal->water_reminder_start_hour;
                    $end = (int) $goal->water_reminder_end_hour;
                    if ($count === 0 || $start < 6 || $end > 22 || $end <= $start) {
                        continue;
                    }

                    $slots = $count === 1
                        ? [(int) round(($start + $end) / 2)]
                        : array_map(
                            fn (int $index) => (int) round($start + ($end - $start) * $index / ($count - 1)),
                            range(0, $count - 1),
                        );

                    foreach ($slots as $index => $hour) {
                        $due = $now->startOfDay()->addHours($hour);
                        if ($now->lt($due) || $now->gte($due->addMinutes(20))) {
                            continue;
                        }

                        $date = $now->toDateString();
                        $logged = NutritionMeal::query()
                            ->where('user_id', $user->id)
                            ->whereDate('eaten_on', $date)
                            ->where('water_ml', '>', 0);
                        $recentlyLogged = (clone $logged)
                            ->where('created_at', '>=', $now->subHours(2)->utc())
                            ->exists();
                        if ($recentlyLogged) {
                            continue;
                        }

                        $target = $this->dailyTarget($goal, $date);
                        if ((clone $logged)->sum('water_ml') >= $target) {
                            continue;
                        }

                        $notification = AppNotification::sendLocalized(
                            $user,
                            'nutrition.water.reminder',
                            'nutrition.notifications.water_title',
                            'nutrition.notifications.water_body',
                            [],
                            ['url' => route('auth.nutrition.index'), 'mobile_url' => 'airmius://nutrition', 'date' => $date],
                            ['category' => 'system', 'priority' => 'low', 'dedupe_key' => "water:{$date}:{$index}"],
                        );
                        if ($notification?->wasRecentlyCreated) {
                            $sent++;
                        }
                    }
                }
            });

        $this->info("Water reminders sent: {$sent}");

        return self::SUCCESS;
    }

    private function dailyTarget(NutritionGoal $goal, string $date): int
    {
        if ($goal->water_target_mode !== 'auto') {
            return max(1500, min(6000, (int) ($goal->water_target_ml ?: 2500)));
        }

        $base = $goal->body_weight_kg
            ? (int) round(((float) $goal->body_weight_kg * 33) / 50) * 50
            : 2500;
        $extra = TrainingLog::query()
            ->where('user_id', $goal->user_id)
            ->whereDate('performed_at', $date)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'draft'))
            ->get(['sport_type', 'duration_minutes', 'calories', 'intensity'])
            ->sum(function (TrainingLog $log): int {
                $duration = (int) ($log->duration_minutes ?? 0);
                if ($duration <= 0) {
                    return min(1200, (int) round(((int) ($log->calories ?? 0) * 0.5) / 50) * 50);
                }

                $sport = strtolower((string) $log->sport_type);
                $perHour = match (true) {
                    str_contains($sport, 'run'), str_contains($sport, 'lauf'), str_contains($sport, 'intervall'), str_contains($sport, 'football'), str_contains($sport, 'fussball') => 650,
                    str_contains($sport, 'bike'), str_contains($sport, 'rad'), str_contains($sport, 'cycling') => 600,
                    str_contains($sport, 'swim'), str_contains($sport, 'schwimm') => 450,
                    str_contains($sport, 'gym'), str_contains($sport, 'kraft') => 400,
                    default => 500,
                };
                $intensity = match ((string) $log->intensity) {
                    'hart' => 1.25,
                    'locker', 'recovery' => 0.75,
                    default => 1.0,
                };

                return min(1500, (int) round((($duration / 60) * $perHour * $intensity) / 50) * 50);
            });

        return max(1500, min(6000, $base + min(2000, (int) $extra)));
    }
}
