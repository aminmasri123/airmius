<?php

namespace Tests\Feature;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\Notification as AppNotification;
use App\Models\User;
use App\Services\Learning\LearningProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_catalogs_have_key_and_placeholder_parity(): void
    {
        $reference = Arr::dot(require lang_path('de/learning.php'));

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $catalog = Arr::dot(require lang_path($locale.'/learning.php'));
            $this->assertSame(array_keys($reference), array_keys($catalog), $locale.' key parity');

            foreach ($reference as $key => $source) {
                $this->assertSame(
                    $this->placeholders((string) $source),
                    $this->placeholders((string) $catalog[$key]),
                    $locale.' placeholder parity for '.$key,
                );
            }
        }
    }

    public function test_mobile_responses_and_completion_notifications_follow_request_and_recipient_locales(): void
    {
        $tutor = User::factory()->create(['language' => 'fr']);
        $student = User::factory()->create(['language' => 'ar']);
        [$course, $lesson] = $this->courseWithLesson($tutor);
        Sanctum::actingAs($student);

        $this->withHeader('X-App-Locale', 'ar')
            ->postJson('/api/v1/learning/courses/'.$course->id.'/enroll')
            ->assertCreated()
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', __('learning.responses.enrolled', locale: 'ar'));

        $enrollmentNotification = AppNotification::query()
            ->where('user_id', $tutor->id)
            ->where('type', 'learning.enrollment.created')
            ->firstOrFail();

        $this->assertSame('fr', data_get($enrollmentNotification->data, 'locale'));
        $this->assertSame(
            __('learning.notifications.enrollment_title', locale: 'fr'),
            data_get($enrollmentNotification->data, 'title'),
        );
        $this->assertSame(
            'learning.notifications.enrollment_body',
            data_get($enrollmentNotification->data, 'i18n.body_key'),
        );

        $this->withHeader('X-App-Locale', 'ar')
            ->putJson('/api/v1/learning/courses/'.$course->id.'/lessons/'.$lesson->id.'/complete')
            ->assertOk()
            ->assertJsonPath('message', __('learning.responses.lesson_completed', locale: 'ar'));

        $studentNotification = AppNotification::query()
            ->where('user_id', $student->id)
            ->where('type', 'learning.certificate.issued')
            ->firstOrFail();
        $tutorNotification = AppNotification::query()
            ->where('user_id', $tutor->id)
            ->where('type', 'learning.student.completed')
            ->firstOrFail();

        $this->assertSame('ar', data_get($studentNotification->data, 'locale'));
        $this->assertSame(
            __('learning.notifications.certificate_issued_title', locale: 'ar'),
            data_get($studentNotification->data, 'title'),
        );
        $this->assertSame('fr', data_get($tutorNotification->data, 'locale'));
        $this->assertSame(
            __('learning.notifications.student_completed_title', locale: 'fr'),
            data_get($tutorNotification->data, 'title'),
        );
    }

    public function test_completed_enrollment_does_not_regress_when_course_content_changes(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        [$course, $lesson] = $this->courseWithLesson($tutor);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $progress = app(LearningProgressService::class);

        $progress->completeLesson($course, $enrollment, $lesson);
        $this->assertNotNull($enrollment->fresh()->completed_at);
        $certificateId = $enrollment->fresh()->certificate?->id;

        LearningLesson::query()->create([
            'learning_course_id' => $course->id,
            'learning_course_section_id' => $lesson->learning_course_section_id,
            'title' => 'Später ergänzte Lektion',
            'position' => 2,
        ]);
        $progress->refreshCompletion($course, $enrollment->fresh());

        $this->assertSame(100, $enrollment->fresh()->progress_percent);
        $this->assertNotNull($enrollment->fresh()->completed_at);
        $this->assertSame($certificateId, $enrollment->fresh()->certificate?->id);
        $this->assertSame(1, $enrollment->fresh()->certificate()->count());
    }

    public function test_empty_course_cannot_issue_a_certificate(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Leerer Kurs',
            'slug' => 'leerer-kurs',
            'status' => 'published',
            'is_public' => true,
            'is_free' => true,
            'published_at' => now(),
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        app(LearningProgressService::class)->refreshCompletion($course, $enrollment);

        $this->assertSame(0, $enrollment->fresh()->progress_percent);
        $this->assertNull($enrollment->fresh()->completed_at);
        $this->assertNull($enrollment->fresh()->certificate);
    }

    /** @return array{LearningCourse, LearningLesson} */
    private function courseWithLesson(User $tutor): array
    {
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Internationales Training',
            'slug' => 'internationales-training-'.strtolower(str()->random(6)),
            'status' => 'published',
            'is_public' => true,
            'is_free' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Grundlagen',
            'position' => 1,
        ]);

        return [$course, $lesson];
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
        $placeholders = array_values(array_unique($matches[0] ?? []));
        sort($placeholders);

        return $placeholders;
    }
}
