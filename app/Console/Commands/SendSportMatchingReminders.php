<?php

namespace App\Console\Commands;

use App\Models\SportMatchingAttendance;
use App\Support\AppNotification;
use Illuminate\Console\Command;

class SendSportMatchingReminders extends Command
{
    protected $signature = 'airmius:send-sport-matching-reminders';

    protected $description = 'Send reminders for upcoming sport matching sessions';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        SportMatchingAttendance::query()
            ->with('matching:id,title,starts_at,status')
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereHas('matching', fn ($query) => $query
                ->where('status', '!=', 'cancelled')
                ->where('starts_at', '>', $now))
            ->chunkById(100, function ($attendances) use ($now, &$sent): void {
                foreach ($attendances as $attendance) {
                    $matching = $attendance->matching;
                    if (! $matching || ! $matching->starts_at) {
                        continue;
                    }

                    $isTwoHourReminder = $matching->starts_at->lte($now->copy()->addHours(2));
                    $isDayReminder = $matching->starts_at->lte($now->copy()->addHours(24));
                    $column = $isTwoHourReminder
                        ? 'reminder_2h_sent_at'
                        : ($isDayReminder ? 'reminder_24h_sent_at' : null);

                    if (! $column || $attendance->{$column}) {
                        continue;
                    }

                    $needsConfirmation = $attendance->status === 'pending';
                    $titleKey = $needsConfirmation
                        ? 'sport_matching.notifications.reminder_confirm_title'
                        : ($isTwoHourReminder
                            ? 'sport_matching.notifications.reminder_two_hours_title'
                            : 'sport_matching.notifications.reminder_tomorrow_title');
                    $bodyKey = $needsConfirmation
                        ? 'sport_matching.notifications.reminder_confirm_body'
                        : 'sport_matching.notifications.reminder_body';

                    AppNotification::sendLocalized(
                        $attendance->user_id,
                        'sport_matching.reminder',
                        $titleKey,
                        $bodyKey,
                        ['matching' => $matching->title],
                        [
                            'url' => route('auth.sport-matching.index'),
                            'matching_id' => $matching->id,
                            'reminder' => $isTwoHourReminder ? '2h' : '24h',
                        ],
                    );

                    $attendance->forceFill([$column => now()])->save();
                    $sent++;
                }
            });

        $this->info("Sport matching reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
