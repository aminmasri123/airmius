<?php

namespace App\Observers;

use App\Models\TrainingLog;
use App\Services\DomainEventPublisher;

class TrainingLogObserver
{
    public function __construct(private readonly DomainEventPublisher $domainEvents) {}

    public function created(TrainingLog $log): void
    {
        $this->recordVerifiedCompletion($log);
    }

    public function updated(TrainingLog $log): void
    {
        if (! $log->wasChanged('status') || $log->status !== 'completed') {
            return;
        }

        $this->recordVerifiedCompletion($log);
    }

    private function recordVerifiedCompletion(TrainingLog $log): void
    {
        $verification = $this->verificationFor($log);

        if ($log->status !== 'completed' || $verification === null) {
            return;
        }

        if (! $log->performed_at || $log->performed_at->isAfter(now()->addMinutes(5))) {
            return;
        }

        $this->domainEvents->record(
            'training.log.completed.v1',
            $log,
            payload: ['verification' => $verification],
            audience: ['users' => [(int) $log->user_id]],
        );
    }

    private function verificationFor(TrainingLog $log): ?string
    {
        if ($log->training_plan_item_id) {
            return 'training_plan';
        }

        if ($log->sport_route_track_id) {
            return 'verified_gps_track';
        }

        return null;
    }
}
