<?php

namespace App\Services\Training;

use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Support\Collection;

final class TrainingPlanNotificationService
{
    /** @return Collection<int, int> */
    public function recipientIds(TrainingPlan $plan, ?User $actor = null): Collection
    {
        return $this->recipients($plan, $actor)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $replace
     * @param  iterable<int>|null  $onlyRecipientIds
     */
    public function notifyRecipients(
        TrainingPlan $plan,
        User $actor,
        string $titleKey,
        string $bodyKey,
        array $replace,
        string $url,
        ?iterable $onlyRecipientIds = null,
    ): void {
        $recipients = $this->recipients($plan, $actor);

        if ($onlyRecipientIds !== null) {
            $recipientIds = collect($onlyRecipientIds)
                ->map(fn ($id) => (int) $id)
                ->unique();

            $recipients = $recipients->filter(
                fn (User $recipient) => $recipientIds->contains((int) $recipient->id)
            );
        }

        $recipients->each(fn (User $recipient) => AppNotification::sendLocalized(
            $recipient,
            'training.plan.changed',
            $titleKey,
            $bodyKey,
            $replace,
            [
                'url' => $url,
                'training_plan_id' => $plan->id,
            ],
        ));
    }

    /** @return Collection<int, User> */
    private function recipients(TrainingPlan $plan, ?User $actor = null): Collection
    {
        $plan->load([
            'creator',
            'assignments.user',
            'assignments.team.users',
        ]);

        return collect([$plan->creator])
            ->merge($plan->assignments->pluck('user'))
            ->merge($plan->assignments->flatMap(
                fn ($assignment) => $assignment->team?->users ?? collect()
            ))
            ->filter(fn ($recipient) => $recipient instanceof User)
            ->unique('id')
            ->when(
                $actor !== null,
                fn (Collection $recipients) => $recipients->reject(
                    fn (User $recipient) => (int) $recipient->id === (int) $actor->id
                )
            )
            ->values();
    }
}
