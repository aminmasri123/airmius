<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\TrainingAvailabilityStatus;
use App\Models\User;
use App\Services\CapacityBookingRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CapacityBookingRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_capacity_deadline_and_event_availability_are_enforced(): void
    {
        $service = app(CapacityBookingRuleService::class);
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $absent = User::factory()->create();

        $course = LearningCourse::query()->create([
            'user_id' => $owner->id,
            'title' => 'Limited Course',
            'slug' => 'limited-course',
            'status' => 'published',
            'is_public' => true,
            'is_free' => true,
            'capacity' => 1,
            'registration_deadline_at' => now()->addHour(),
        ]);
        LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $first->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $service->assertCourseEnrollmentOpen($course->fresh(), $second);
    }

    public function test_event_capacity_waitlist_and_training_absence_are_enforced(): void
    {
        $service = app(CapacityBookingRuleService::class);
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $absent = User::factory()->create();

        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Training Capacity',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addDays(3),
            'max_participants' => 1,
            'participant_response_deadline_at' => now()->addDay(),
        ]);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'user_id' => $first->id,
            'status' => 'yes',
        ]);
        TrainingAvailabilityStatus::query()->create([
            'user_id' => $absent->id,
            'created_by' => $absent->id,
            'status' => 'injured',
            'visibility' => 'trainer',
            'starts_on' => now()->addDays(2)->toDateString(),
            'ends_on' => now()->addDays(4)->toDateString(),
        ]);

        [$status, $position] = $service->eventResponseStatus($event->fresh(), $second, null, 'yes');
        $this->assertSame('waitlist', $status);
        $this->assertSame(1, $position);

        try {
            $service->eventResponseStatus($event->fresh(), $absent, null, 'yes');
            $this->fail('Blocking availability did not fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }
}
