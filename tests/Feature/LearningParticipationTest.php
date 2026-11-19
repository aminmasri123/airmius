<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\LearningCertificate;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningParticipationTest extends TestCase
{
    use RefreshDatabase;

    public static function platforms(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('platforms')]
    public function test_hide_leave_and_resume_preserve_progress_and_paid_access(bool $api): void
    {
        [$user,$course,$enrollment,$lesson] = $this->fixture();
        $order = CommerceOrder::create(['user_id' => $user->id, 'type' => 'marketplace_product', 'provider' => 'bank_transfer', 'amount_cents' => 1000, 'currency' => 'EUR', 'status' => 'completed']);
        LearningCertificate::create(['learning_course_id' => $course->id, 'learning_enrollment_id' => $enrollment->id, 'user_id' => $user->id, 'code' => 'KEEP-PARTICIPATION', 'issued_at' => now()]);
        $api ? Sanctum::actingAs($user) : $this->actingAs($user);
        $url = $this->url($api, $enrollment);
        $this->putJson($url, ['action' => 'hide'])->assertSuccessful();
        $this->assertNotNull($enrollment->fresh()->hidden_at);
        $this->assertSame('active', $enrollment->fresh()->status);
        if ($api) {
            $this->getJson('/api/v1/learning/courses/'.$course->id)->assertOk()->assertJsonPath('data.can_use_learning_room', true);
        }
        $this->putJson($url, ['action' => 'show'])->assertSuccessful();
        $this->assertNull($enrollment->fresh()->hidden_at);
        $this->putJson($url, ['action' => 'leave'])->assertSuccessful();
        $this->putJson($url, ['action' => 'leave'])->assertSuccessful();
        $this->assertSame('left', $enrollment->fresh()->status);
        $this->assertNotNull($enrollment->fresh()->left_at);
        if ($api) {
            $this->getJson('/api/v1/learning/courses/'.$course->id)->assertOk()->assertJsonPath('data.can_use_learning_room', false)->assertJsonPath('data.course.can_resume', true)->assertJsonPath('data.course.enrollment_id', $enrollment->id);
            $this->putJson('/api/v1/learning/courses/'.$course->id.'/lessons/'.$lesson->id.'/complete')->assertForbidden();
        }
        $this->putJson($url, ['action' => 'resume'])->assertSuccessful();
        $this->putJson($url, ['action' => 'resume'])->assertSuccessful();
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertNull($enrollment->fresh()->left_at);
        $this->assertSame(42, $enrollment->fresh()->progress_percent);
        $this->assertDatabaseHas('learning_lesson_progress', ['learning_enrollment_id' => $enrollment->id, 'watch_seconds' => 123, 'completed' => true]);
        $this->assertDatabaseHas('learning_certificates', ['code' => 'KEEP-PARTICIPATION']);
        $this->assertSame(1, CommerceOrder::where('user_id', $user->id)->count());
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, LearningEnrollment::where('user_id', $user->id)->count());
    }

    #[DataProvider('platforms')]
    public function test_only_owner_can_manage_participation_and_revoked_access_cannot_resume(bool $api): void
    {
        [$user,$course,$enrollment] = $this->fixture();
        $stranger = User::factory()->create();
        $api ? Sanctum::actingAs($stranger) : $this->actingAs($stranger);
        foreach (['leave', 'hide', 'show', 'resume'] as $action) {
            $this->putJson($this->url($api, $enrollment), ['action' => $action])->assertForbidden();
        }
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertNull($enrollment->fresh()->hidden_at);
        $api ? Sanctum::actingAs($user) : $this->actingAs($user);
        $this->putJson($this->url($api, $enrollment), ['action' => 'invalid'])->assertUnprocessable();
        foreach (['cancelled', 'refunded'] as $status) {
            $enrollment->update(['status' => $status]);
            $this->putJson($this->url($api, $enrollment), ['action' => 'resume'])->assertForbidden();
            $this->assertSame($status, $enrollment->fresh()->status);
        }
    }

    public function test_resume_checks_capacity_and_course_visibility(): void
    {
        [$user,$course,$enrollment] = $this->fixture();
        Sanctum::actingAs($user);
        $enrollment->update(['status' => 'left']);
        $course->update(['capacity' => 1]);
        LearningEnrollment::create(['learning_course_id' => $course->id, 'user_id' => User::factory()->create()->id, 'status' => 'active']);
        $this->putJson($this->url(true, $enrollment), ['action' => 'resume'])->assertUnprocessable();
        $this->assertSame('left', $enrollment->fresh()->status);
        $course->update(['capacity' => null, 'status' => 'archived']);
        $this->putJson($this->url(true, $enrollment), ['action' => 'resume'])->assertForbidden();
    }

    private function url(bool $api, LearningEnrollment $e): string
    {
        return $api ? '/api/v1/learning/enrollments/'.$e->id.'/participation' : route('auth.learning.enrollments.participation', $e);
    }

    private function fixture(): array
    {
        $user = User::factory()->create();
        $course = LearningCourse::create(['user_id' => User::factory()->create()->id, 'title' => 'Paid retained course', 'category' => 'training', 'level' => 'beginner', 'status' => 'published', 'is_public' => true, 'is_free' => false, 'price_cents' => 1000]);
        $section = $course->sections()->create(['title' => 'Start']);
        $lesson = $section->lessons()->create(['learning_course_id' => $course->id, 'title' => 'Lesson', 'content' => 'Retained content']);
        $enrollment = LearningEnrollment::create(['user_id' => $user->id, 'learning_course_id' => $course->id, 'status' => 'active', 'progress_percent' => 42, 'started_at' => now()->subDays(4)]);
        LearningLessonProgress::create(['user_id' => $user->id, 'learning_enrollment_id' => $enrollment->id, 'learning_lesson_id' => $lesson->id, 'watch_seconds' => 123, 'completed' => true]);

        return [$user, $course, $enrollment, $lesson];
    }
}
