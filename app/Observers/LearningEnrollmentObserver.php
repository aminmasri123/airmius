<?php

namespace App\Observers;

use App\Models\LearningEnrollment;
use App\Services\DomainEventPublisher;

class LearningEnrollmentObserver
{
    public function __construct(private DomainEventPublisher $domainEvents) {}

    public function created(LearningEnrollment $enrollment): void
    {
        $this->record($enrollment, 'content_learning.enrollment.created.v1');
    }

    public function updated(LearningEnrollment $enrollment): void
    {
        if (! $enrollment->wasChanged(['status', 'completed_at'])) {
            return;
        }

        $completed = $enrollment->status === 'completed' || $enrollment->completed_at !== null;

        $this->record(
            $enrollment,
            $completed
                ? 'content_learning.enrollment.completed.v1'
                : 'content_learning.enrollment.status_changed.v1',
        );
    }

    private function record(LearningEnrollment $enrollment, string $eventName): void
    {
        $this->domainEvents->record(
            $eventName,
            $enrollment,
            payload: [
                'course_id' => $enrollment->learning_course_id,
                'status' => $enrollment->status,
                'progress_percent' => $enrollment->progress_percent,
                'completed_at' => $enrollment->completed_at?->toIso8601String(),
            ],
            audience: ['users' => [$enrollment->user_id]],
        );
    }
}
