<?php

namespace Tests\Feature;

use App\Models\LearningCertificate;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearningCertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        // Keep completion evidence within the fixture's completion cutoff across second boundaries.
        $this->freezeTime();
        $owner = User::factory()->create(['name' => 'Kursleitung Beispiel']);
        $student = User::factory()->create(['name' => 'Alex Beispiel']);
        $course = LearningCourse::create(['user_id' => $owner->id, 'title' => 'TEST: Einführung in die Kursprüfung', 'subtitle' => 'Musterzertifikat für lokale Tests', 'language' => 'de', 'category' => 'training', 'level' => 'beginner', 'status' => 'published', 'is_public' => true]);
        $section = $course->sections()->create(['title' => 'Basics', 'position' => 1]);
        $lesson = $course->lessons()->create(['learning_course_section_id' => $section->id, 'title' => 'Required lesson', 'type' => 'lesson']);
        $enrollment = LearningEnrollment::create(['learning_course_id' => $course->id, 'user_id' => $student->id, 'status' => 'active', 'progress_percent' => 100, 'completed_at' => now()]);
        $progress = $enrollment->lessonProgress()->create(['learning_lesson_id' => $lesson->id, 'completed' => true, 'completed_at' => now()]);
        $quiz = $course->quizzes()->create(['title' => 'Required quiz', 'pass_percent' => 70]);
        $attempt = $quiz->attempts()->create(['learning_enrollment_id' => $enrollment->id, 'user_id' => $student->id, 'answers' => [], 'score_percent' => 100, 'passed' => true, 'submitted_at' => now()]);
        $task = $course->assignments()->create(['title' => 'Required task', 'points' => 100, 'is_required' => true]);
        $submission = $task->submissions()->create(['learning_enrollment_id' => $enrollment->id, 'user_id' => $student->id, 'status' => 'passed', 'score' => 100, 'submitted_at' => now(), 'graded_at' => now()]);
        $certificate = LearningCertificate::create(['learning_course_id' => $course->id, 'learning_enrollment_id' => $enrollment->id, 'user_id' => $student->id, 'code' => 'AIR-LEARN-QR-TEST', 'issued_at' => now()]);

        return [$student, $certificate, $enrollment, $progress, $attempt, $submission];
    }

    public function test_completed_course_produces_a_qr_pdf_and_safe_public_verification(): void
    {
        [$student,$certificate] = $this->fixture();
        $pdf = $this->actingAs($student)->get(route('auth.learning.certificates.show', $certificate))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->get(route('guest.learning.certificates.verify', $certificate->code))->assertOk()->assertInertia(fn (Assert $page) => $page->where('certificate.verified', true)->where('certificate.completion_status', 'passed'));
        $json = $this->getJson('/api/v1/public/learning/certificates/'.$certificate->code)->assertOk()->assertJsonPath('data.verified', true)->assertJsonPath('data.completion_status', 'passed');
        $this->assertStringNotContainsString($student->email, $json->getContent());
        $this->assertStringNotContainsString('answers', $json->getContent());
        if ($directory = getenv('AIRMIUS_CERTIFICATE_PREVIEW_DIR')) {
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($directory.'/certificate-qr-preview.pdf', $pdf);
            file_put_contents($directory.'/expected-qr-url.txt', route('guest.learning.certificates.verify', $certificate->code));
        }
    }

    public function test_cached_completion_flag_does_not_replace_lessons_quiz_or_assignment_evidence(): void
    {
        [$student,$certificate,$enrollment,$progress,$attempt,$submission] = $this->fixture();
        $api = '/api/v1/public/learning/certificates/'.$certificate->code;
        $progress->update(['completed' => false]);
        $this->getJson($api)->assertNotFound();
        $progress->update(['completed' => true]);
        $attempt->update(['passed' => false]);
        $this->getJson($api)->assertNotFound();
        $attempt->update(['passed' => true]);
        $submission->update(['status' => 'needs_revision']);
        $this->getJson($api)->assertNotFound();
        $this->actingAs($student)->get(route('auth.learning.certificates.show', $certificate))->assertForbidden();
        $submission->update(['status' => 'passed']);
        $this->getJson($api)->assertOk();
    }

    public function test_revoked_mismatched_future_and_unknown_certificates_are_not_confirmed(): void
    {
        [$student,$certificate,$enrollment] = $this->fixture();
        $api = '/api/v1/public/learning/certificates/'.$certificate->code;
        $enrollment->update(['status' => 'revoked']);
        $this->getJson($api)->assertNotFound();
        $this->get(route('guest.learning.certificates.verify', $certificate->code))->assertNotFound()->assertInertia(fn (Assert $page) => $page->where('certificate', null));
        $enrollment->update(['status' => 'active']);
        $certificate->update(['user_id' => User::factory()->create()->id]);
        $this->getJson($api)->assertNotFound();
        $certificate->update(['user_id' => $student->id, 'issued_at' => now()->addDay()]);
        $this->getJson($api)->assertNotFound();
        $certificate->update(['issued_at' => now()->subDay()]);
        $this->getJson($api)->assertNotFound();
        $certificate->update(['issued_at' => now()]);
        $enrollment->update(['progress_percent' => 95]);
        $this->getJson($api)->assertNotFound();
        $this->getJson('/api/v1/public/learning/certificates/UNKNOWN-CODE')->assertNotFound();
        $this->get(route('guest.learning.certificates.verify', 'UNKNOWN-CODE'))->assertNotFound()->assertInertia(fn (Assert $page) => $page->where('certificate', null));
    }

    public function test_new_curriculum_does_not_invalidate_a_previously_earned_certificate(): void
    {
        [$student, $certificate, $enrollment] = $this->fixture();
        $this->travel(2)->days();
        $section = $certificate->course->sections()->firstOrFail();
        $certificate->course->lessons()->create(['learning_course_section_id' => $section->id, 'title' => 'New lesson', 'type' => 'lesson']);
        $certificate->course->assignments()->create(['title' => 'New required task', 'points' => 100, 'is_required' => true]);
        $this->getJson('/api/v1/public/learning/certificates/'.$certificate->code)->assertOk()->assertJsonPath('data.verified', true);
    }
}
