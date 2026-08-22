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
            ?->timezone($event->event_timezone ?: config('app.timezone', 'UTC'))
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
                    'mobile_url' => 'airmius://events/'.$event->id,
                    'deep_link' => 'airmius://events/'.$event->id,
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'team_id' => $event->team_id,
                    'club_id' => $event->club_id ?: $event->team?->club_id,
                ],
            ));
    }

    public function notifyParticipationResponse(
        Event $event,
        User $actor,
        string $status,
        ?string $reason = null,
    ): void {
        $event->loadMissing(['user', 'team:id,club_id']);

        if (! $event->user || (int) $event->user->id === (int) $actor->id) {
            return;
        }

        $reason = filled($reason) ? str($reason)->limit(180)->toString() : null;
        $bodyKey = $reason
            ? 'server.events.notifications.response_body_with_reason'
            : 'server.events.notifications.response_body';

        AppNotification::sendLocalized(
            $event->user,
            'event.participation_response',
            'server.events.notifications.response_title',
            $bodyKey,
            array_filter([
                'athlete' => $this->displayName($actor),
                'event' => $event->title,
                'status' => AppNotification::translatedReplacement(
                    'server.events.notifications.response_status.'.$status,
                    $status,
                ),
                'reason' => $reason,
            ], fn (mixed $value) => $value !== null),
            $this->participationData($event, $actor, [
                'participation_status' => $status,
                'participation_action' => 'responded',
                'response_reason' => $reason,
            ]),
        );
    }

    public function notifyParticipationWithdrawn(Event $event, User $actor): void
    {
        $event->loadMissing(['user', 'team:id,club_id']);

        if (! $event->user || (int) $event->user->id === (int) $actor->id) {
            return;
        }

        AppNotification::sendLocalized(
            $event->user,
            'event.participation_response',
            'server.events.notifications.response_title',
            'server.events.notifications.response_withdrawn_body',
            [
                'athlete' => $this->displayName($actor),
                'event' => $event->title,
            ],
            $this->participationData($event, $actor, [
                'participation_status' => null,
                'participation_action' => 'withdrawn',
                'response_reason' => null,
            ]),
        );
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

    /** @param array<string, mixed> $response */
    private function participationData(Event $event, User $actor, array $response): array
    {
        return array_merge([
            'url' => route('auth.events.show', $event),
            'mobile_url' => 'airmius://events/'.$event->id,
            'deep_link' => 'airmius://events/'.$event->id,
            'event_id' => $event->id,
            'event_title' => $event->title,
            'actor_id' => $actor->id,
            'actor_name' => $this->displayName($actor),
            'team_id' => $event->team_id,
            'club_id' => $event->club_id ?: $event->team?->club_id,
        ], $response);
    }

    private function displayName(User $user): string
    {
        return trim((string) $user->name) ?: 'User #'.$user->id;
    }
}
