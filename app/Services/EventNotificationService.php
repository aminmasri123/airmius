<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Support\Collection;

final class EventNotificationService
{
    public function notifyPublished(Event $event, User $actor): void
    {
        $event->loadMissing(['club:id,name', 'team:id,club_id,name']);

        [$recipients, $titleKey, $scope] = $this->publicationAudience($event);

        if ($recipients->isEmpty() || $titleKey === null || $scope === null) {
            return;
        }

        $date = $event->start_time
            ?->timezone(config('app.timezone', 'UTC'))
            ->format('d.m.Y H:i');

        $recipients
            ->unique('id')
            ->reject(fn (User $recipient) => (int) $recipient->id === (int) $actor->id)
            ->each(fn (User $recipient) => AppNotification::sendLocalized(
                $recipient,
                'event.published',
                $titleKey,
                'server.events.notifications.published_body',
                [
                    'event' => $event->title,
                    'scope' => $scope,
                    'date' => $date,
                ],
                [
                    'url' => route('auth.events.show', $event),
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'team_id' => $event->team_id,
                    'club_id' => $event->club_id ?: $event->team?->club_id,
                ],
            ));
    }

    /** @return array{Collection<int, User>, string|null, string|null} */
    private function publicationAudience(Event $event): array
    {
        if ($event->team_id && $event->team) {
            return [
                $event->team->users()->get(),
                'server.events.notifications.team_published_title',
                $event->team->name,
            ];
        }

        if ($event->club_id && $event->club) {
            return [
                $event->club->users()
                    ->where(function ($query) {
                        $query->whereNull('club_user.membership_status')
                            ->orWhere('club_user.membership_status', 'active');
                    })
                    ->get(),
                'server.events.notifications.club_published_title',
                $event->club->name,
            ];
        }

        return [collect(), null, null];
    }
}
