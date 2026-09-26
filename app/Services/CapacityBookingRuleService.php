<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\TrainingAvailabilityStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CapacityBookingRuleService
{
    public function assertCourseEnrollmentOpen(LearningCourse $course, User $user): void
    {
        if ($course->registration_deadline_at?->isPast()) {
            throw ValidationException::withMessages(['course' => 'The registration deadline has passed.']);
        }

        if (! $course->capacity) {
            return;
        }

        $alreadyActive = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if ($alreadyActive) {
            return;
        }

        $activeSeats = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->count();

        if ($activeSeats >= $course->capacity) {
            throw ValidationException::withMessages(['course' => 'All available places are already booked.']);
        }
    }

    /** @return array{0:string,1:?int,2:mixed,3:mixed} */
    public function eventResponseStatus(Event $event, User $user, ?EventParticipant $existing, string $requestedStatus): array
    {
        if ($event->participant_response_deadline_at?->isPast()) {
            throw ValidationException::withMessages(['status' => __('server.events.response_deadline_expired')]);
        }

        if ($requestedStatus !== 'yes') {
            return [$requestedStatus, null, null, null];
        }

        $this->assertTrainingAvailability($event, $user);

        if (! $event->max_participants || $existing?->status === 'yes') {
            return [$requestedStatus, null, null, null];
        }

        $yesCount = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', 'yes')
            ->lockForUpdate()
            ->count();

        if ($yesCount >= $event->max_participants) {
            $position = (int) EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('status', 'waitlist')
                ->lockForUpdate()
                ->max('waitlist_position') + 1;

            return ['waitlist', $position, null, null];
        }

        if ($existing?->status === 'waitlist') {
            return ['yes', null, now(), $event->waitlistOfferExpiresAt()];
        }

        return ['yes', null, null, null];
    }

    private function assertTrainingAvailability(Event $event, User $user): void
    {
        if ($event->type !== 'training' || ! $event->start_time) {
            return;
        }

        $eventDate = $event->start_time->toDateString();
        $blocking = TrainingAvailabilityStatus::query()
            ->where('user_id', $user->id)
            ->whereNull('cleared_at')
            ->whereIn('status', ['unavailable', 'injured', 'ill'])
            ->whereDate('starts_on', '<=', $eventDate)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $eventDate))
            ->exists();

        if ($blocking) {
            throw ValidationException::withMessages(['status' => 'Your current availability status prevents this training booking.']);
        }
    }
}
