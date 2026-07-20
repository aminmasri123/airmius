<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendEventReminders extends Command
{
    protected $signature = 'airmius:send-event-reminders';

    protected $description = 'Sendet fällige Event-Erinnerungen an relevante Teilnehmer.';

    public function handle(): int
    {
        $sentEvents = 0;
        $sentNotifications = 0;

        Event::query()
            ->with([
                'participants:id,name',
                'team.users:id',
                'club.users:id',
                'team.club.users:id',
            ])
            ->where('status', 'scheduled')
            ->whereNotNull('reminder_at')
            ->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->where('start_time', '>=', now())
            ->orderBy('reminder_at')
            ->chunkById(100, function ($events) use (&$sentEvents, &$sentNotifications) {
                foreach ($events as $event) {
                    $recipients = $this->recipientIds($event);

                    foreach ($recipients as $recipientId) {
                        AppNotification::send($recipientId, 'event.reminder', [
                            'title' => 'Event-Erinnerung',
                            'body' => $this->notificationBody($event),
                            'url' => route('auth.events.show', $event->id),
                            'event_id' => $event->id,
                            'event_title' => $event->title,
                            'event_start_time' => $event->start_time?->toISOString(),
                            'team_id' => $event->team_id,
                            'club_id' => $event->club_id ?: $event->team?->club_id,
                        ]);

                        $sentNotifications++;
                    }

                    $event->forceFill(['reminder_sent_at' => now()])->save();
                    $sentEvents++;
                }
            });

        $this->info("{$sentNotifications} Event-Erinnerungen fuer {$sentEvents} Events versendet.");

        return self::SUCCESS;
    }

    private function recipientIds(Event $event): Collection
    {
        $declinedIds = $event->participants
            ->filter(fn ($participant) => $participant->pivot?->status === 'no')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $participantIds = $event->participants
            ->filter(fn ($participant) => in_array($participant->pivot?->status, ['yes', 'maybe'], true))
            ->pluck('id');

        $scopeIds = collect();

        if ($event->team_id && $event->team) {
            $scopeIds = $scopeIds->merge($event->team->users->pluck('id'));
        } elseif ($event->club_id && $event->club) {
            $scopeIds = $scopeIds->merge($event->club->users->pluck('id'));
        } elseif ($event->team?->club) {
            $scopeIds = $scopeIds->merge($event->team->club->users->pluck('id'));
        }

        return collect([$event->user_id])
            ->merge($participantIds)
            ->merge($scopeIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $declinedIds->contains($id))
            ->values();
    }

    private function notificationBody(Event $event): string
    {
        $time = $event->start_time
            ? $event->start_time->timezone(config('app.timezone'))->format('d.m.Y H:i')
            : 'bald';

        $location = $event->location ? " Ort: {$event->location}." : '';

        return "{$event->title} startet am {$time}.{$location}";
    }
}
