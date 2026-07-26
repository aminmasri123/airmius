<?php

namespace Tests\Feature;

use App\Models\LearningCourse;
use App\Models\LearningCourseReview;
use App\Models\LearningAssignment;
use App\Models\LearningAssignmentSubmission;
use App\Models\LearningCertificate;
use App\Models\LearningEmailDelivery;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningLessonComment;
use App\Models\LearningLessonProgress;
use App\Models\LearningQuiz;
use App\Models\LearningQuizAttempt;
use App\Models\LearningQuizQuestion;
use App\Models\LearningSecurityEvent;
use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearningStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_can_create_course_structure_and_lesson(): void
    {
        $tutor = User::factory()->create();

        $response = $this->actingAs($tutor)->post(route('auth.learning.studio.courses.store'), [
            'title' => 'Explosiver Antritt',
            'subtitle' => 'Schneller starten im Spiel',
            'description' => 'Ein Kurs fuer Sprinttechnik im Teamsport.',
            'category' => 'training',
            'sport_type' => 'Fussball',
            'level' => 'intermediate',
            'language' => 'de',
            'status' => 'draft',
            'is_public' => false,
            'is_free' => true,
            'learning_goals_text' => "Startposition verbessern\nErste Schritte trainieren",
        ]);

        $course = LearningCourse::query()->where('title', 'Explosiver Antritt')->first();

        $this->assertNotNull($course);
        $response->assertRedirect(route('auth.learning.studio.index', ['course' => $course->id]));
        $this->assertSame($tutor->id, $course->user_id);
        $this->assertSame(['Startposition verbessern', 'Erste Schritte trainieren'], $course->learning_goals);
        $this->assertSame(1, $course->sections()->count());

        $section = $course->sections()->first();

        $this->actingAs($tutor)->post(route('auth.learning.studio.lessons.store', $course), [
            'learning_course_section_id' => $section->id,
            'title' => 'Analyse und Warm-up',
            'type' => 'video',
            'summary' => 'Erste Technikpunkte und Aktivierung.',
            'content' => 'Achte auf Koerperwinkel und Abdruck.',
            'video_url' => 'https://example.com/video',
            'attachments_text' => "https://example.com/checkliste.pdf",
            'duration_minutes' => 18,
            'is_preview' => true,
        ])->assertSessionHasNoErrors();

        $lesson = LearningLesson::query()->where('title', 'Analyse und Warm-up')->first();

        $this->assertNotNull($lesson);
        $this->assertSame(18, $course->fresh()->estimated_minutes);
        $this->assertSame('https://example.com/checkliste.pdf', $lesson->attachments[0]['url']);
    }

    public function test_public_learning_page_lists_published_courses(): void
    {
        $tutor = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Athletik Grundlagen',
            'slug' => 'athletik-grundlagen',
            'description' => 'Basiswissen fuer Sportler.',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);

        $response = $this->get(route('guest.e-learning'));

        $response->assertOk();
        $response->assertSee('Athletik Grundlagen');
    }

    public function test_public_course_page_hides_locked_lesson_content_from_guests(): void
    {
        $tutor = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Geschuetzter Kurs',
            'slug' => 'geschuetzter-kurs',
            'description' => 'Basiswissen fuer Sportler.',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create([
            'title' => 'Start',
            'position' => 1,
        ]);
        $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Gesperrte Lektion',
            'summary' => 'Nur fuer Teilnehmer.',
            'content' => 'LOCKED_SECRET_CONTENT',
            'video_url' => 'https://example.com/locked-secret-video',
            'attachments' => [['name' => 'secret.pdf', 'url' => 'https://example.com/secret.pdf']],
            'position' => 1,
            'is_preview' => false,
        ]);
        $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Preview Lektion',
            'summary' => 'Frei sichtbar.',
            'content' => 'PREVIEW_SECRET_CONTENT',
            'position' => 2,
            'is_preview' => true,
        ]);
        $quiz = LearningQuiz::query()->create([
            'learning_course_id' => $course->id,
            'title' => 'Wissenscheck',
            'pass_percent' => 70,
        ]);
        LearningQuizQuestion::query()->create([
            'learning_quiz_id' => $quiz->id,
            'question' => 'Was ist richtig?',
            'options' => ['Option A', 'Option B'],
            'correct_options' => ['CORRECT_SECRET_ANSWER'],
            'explanation' => 'SECRET_EXPLANATION',
            'position' => 1,
        ]);

        $response = $this->get(route('guest.learning.courses.show', $course));

        $response->assertOk();
        $response->assertSee('Preview Lektion');
        $response->assertSee('PREVIEW_SECRET_CONTENT');
        $response->assertSee('Gesperrte Lektion');
        $response->assertDontSee('LOCKED_SECRET_CONTENT');
        $response->assertDontSee('locked-secret-video');
        $response->assertDontSee('secret.pdf');
        $response->assertDontSee('CORRECT_SECRET_ANSWER');
        $response->assertDontSee('SECRET_EXPLANATION');
    }

    public function test_enrolled_user_can_receive_locked_lesson_content_without_quiz_answers(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Teilnehmer Kurs',
            'slug' => 'teilnehmer-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create([
            'title' => 'Start',
            'position' => 1,
        ]);
        $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Interne Lektion',
            'content' => 'ENROLLED_SECRET_CONTENT',
            'position' => 1,
        ]);
        $quiz = LearningQuiz::query()->create([
            'learning_course_id' => $course->id,
            'title' => 'Teilnehmer Quiz',
            'pass_percent' => 70,
        ]);
        LearningQuizQuestion::query()->create([
            'learning_quiz_id' => $quiz->id,
            'question' => 'Teilnehmer Frage',
            'options' => ['A', 'B'],
            'correct_options' => ['ENROLLED_CORRECT_SECRET'],
            'position' => 1,
        ]);
        LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('guest.learning.courses.show', $course));

        $response->assertOk();
        $response->assertSee('ENROLLED_SECRET_CONTENT');
        $response->assertSee('Teilnehmer Frage');
        $response->assertDontSee('ENROLLED_CORRECT_SECRET');
    }

    public function test_paid_course_cannot_be_enrolled_for_free(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Premium Kurs',
            'slug' => 'premium-kurs',
            'category' => 'training',
            'level' => 'advanced',
            'status' => 'published',
            'is_public' => true,
            'is_free' => false,
            'price_cents' => 4900,
            'published_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('auth.learning.courses.enroll', $course))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertFalse(
            LearningEnrollment::query()
                ->where('learning_course_id', $course->id)
                ->where('user_id', $student->id)
                ->exists()
        );
    }

    public function test_enrolled_user_can_mark_lessons_complete_and_updates_progress(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Fortschritt Kurs',
            'slug' => 'fortschritt-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create([
            'title' => 'Start',
            'position' => 1,
        ]);
        $firstLesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Erste Lektion',
            'position' => 1,
        ]);
        $secondLesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Zweite Lektion',
            'position' => 2,
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->put(route('auth.learning.lessons.complete', [$course, $firstLesson]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            LearningLessonProgress::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->where('learning_lesson_id', $firstLesson->id)
                ->where('completed', true)
                ->exists()
        );
        $this->assertSame(50, $enrollment->fresh()->progress_percent);
        $this->assertNull($enrollment->fresh()->completed_at);

        $this->actingAs($student)
            ->put(route('auth.learning.lessons.complete', [$course, $secondLesson]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(100, $enrollment->fresh()->progress_percent);
        $this->assertNotNull($enrollment->fresh()->completed_at);
    }

    public function test_quiz_attempt_can_complete_course_and_issue_certificate(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Quiz Abschluss',
            'slug' => 'quiz-abschluss',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Basis',
            'position' => 1,
        ]);
        $quiz = LearningQuiz::query()->create([
            'learning_course_id' => $course->id,
            'title' => 'Finale',
            'pass_percent' => 70,
        ]);
        $question = LearningQuizQuestion::query()->create([
            'learning_quiz_id' => $quiz->id,
            'question' => 'Was ist wichtig?',
            'options' => ['Technik', 'Zufall'],
            'correct_options' => ['Technik'],
            'position' => 1,
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->put(route('auth.learning.lessons.complete', [$course, $lesson]))
            ->assertRedirect();

        $this->assertNull($enrollment->fresh()->completed_at);

        $this->actingAs($student)
            ->post(route('auth.learning.quizzes.attempts.store', [$course, $quiz]), [
                'answers' => [
                    $question->id => ['Technik'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            LearningQuizAttempt::query()
                ->where('learning_quiz_id', $quiz->id)
                ->where('learning_enrollment_id', $enrollment->id)
                ->where('passed', true)
                ->exists()
        );
        $this->assertNotNull($enrollment->fresh()->completed_at);
        $this->assertNotNull($enrollment->fresh()->certificate);
    }

    public function test_enrolled_user_can_review_course(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Review Kurs',
            'slug' => 'review-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('auth.learning.reviews.store', $course), [
                'rating' => 5,
                'body' => 'Sehr klarer Aufbau.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            LearningCourseReview::query()
                ->where('learning_course_id', $course->id)
                ->where('user_id', $student->id)
                ->where('rating', 5)
                ->exists()
        );
    }

    public function test_tutor_can_update_question_status(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Fragen Kurs',
            'slug' => 'fragen-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Basis',
            'position' => 1,
        ]);
        $comment = LearningLessonComment::query()->create([
            'learning_lesson_id' => $lesson->id,
            'user_id' => $student->id,
            'body' => 'Wie oft trainieren?',
            'visibility' => 'course',
            'status' => 'open',
        ]);

        $this->actingAs($tutor)
            ->put(route('auth.learning.studio.comments.update', [$course, $comment]), [
                'status' => 'resolved',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('resolved', $comment->fresh()->status);
        $this->assertNotNull($comment->fresh()->resolved_at);
    }

    public function test_completed_student_can_download_certificate_pdf_and_see_library(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Zertifikat Kurs',
            'slug' => 'zertifikat-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'progress_percent' => 100,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $certificate = LearningCertificate::query()->create([
            'learning_course_id' => $course->id,
            'learning_enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'code' => 'AIR-LEARN-TESTPDF',
            'issued_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('auth.learning.certificates.show', $certificate))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($student)
            ->get(route('auth.learning.my-courses.index'))
            ->assertOk()
            ->assertSee('Zertifikat Kurs')
            ->assertSee('AIR-LEARN-TESTPDF');
    }

    public function test_tutor_can_reorder_and_delete_lessons_and_delete_quiz(): void
    {
        $tutor = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Sortier Kurs',
            'slug' => 'sortier-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'draft',
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $firstLesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Alt Eins',
            'position' => 1,
        ]);
        $secondLesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Alt Zwei',
            'position' => 2,
        ]);
        $quiz = LearningQuiz::query()->create([
            'learning_course_id' => $course->id,
            'title' => 'Wissenscheck',
            'pass_percent' => 70,
        ]);

        $this->actingAs($tutor)
            ->put(route('auth.learning.studio.lessons.reorder', $course), [
                'lessons' => [
                    ['id' => $firstLesson->id, 'position' => 2],
                    ['id' => $secondLesson->id, 'position' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $firstLesson->fresh()->position);
        $this->assertSame(1, $secondLesson->fresh()->position);

        $this->actingAs($tutor)
            ->delete(route('auth.learning.studio.lessons.destroy', [$course, $firstLesson]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse(LearningLesson::query()->whereKey($firstLesson->id)->exists());

        $this->actingAs($tutor)
            ->delete(route('auth.learning.studio.quizzes.destroy', [$course, $quiz]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse(LearningQuiz::query()->whereKey($quiz->id)->exists());
    }

    public function test_course_questions_notify_tutor_and_tutor_can_reply(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Antwort Kurs',
            'slug' => 'antwort-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Basis',
            'position' => 1,
        ]);
        LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('auth.learning.lessons.comments.store', [$course, $lesson]), [
                'body' => 'Wie oft soll ich die Uebung machen?',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $comment = LearningLessonComment::query()->where('user_id', $student->id)->first();

        $this->assertNotNull($comment);
        $this->assertTrue(Notification::query()
            ->where('user_id', $tutor->id)
            ->where('type', 'learning.question.created')
            ->exists());

        $this->actingAs($tutor)
            ->post(route('auth.learning.studio.comments.replies.store', [$course, $comment]), [
                'body' => 'Drei saubere Durchgaenge reichen fuer den Start.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('answered', $comment->fresh()->status);
        $this->assertTrue(LearningLessonComment::query()
            ->where('parent_id', $comment->id)
            ->where('body', 'Drei saubere Durchgaenge reichen fuer den Start.')
            ->exists());
        $this->assertTrue(Notification::query()
            ->where('user_id', $student->id)
            ->where('type', 'learning.question.answered')
            ->exists());

        $this->actingAs($student)
            ->get(route('guest.learning.courses.show', $course))
            ->assertOk()
            ->assertSee('Drei saubere Durchgaenge reichen fuer den Start.');
    }

    public function test_public_certificate_verification_page_shows_valid_certificate(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Verify Kurs',
            'slug' => 'verify-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
        LearningCertificate::query()->create([
            'learning_course_id' => $course->id,
            'learning_enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'code' => 'AIR-LEARN-VERIFY',
            'issued_at' => now(),
        ]);

        $this->get(route('guest.learning.certificates.verify', 'AIR-LEARN-VERIFY'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/LearningCertificateVerify')
            )
            ->assertSee('Verify Kurs')
            ->assertSee('AIR-LEARN-VERIFY');
    }

    public function test_public_certificate_api_returns_only_safe_verification_data(): void
    {
        $tutor = User::factory()->create(['name' => 'Kursleitung']);
        $student = User::factory()->create(['name' => 'Teilnehmende Person']);
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'API Verify Kurs',
            'subtitle' => 'Öffentliche Kursbeschreibung',
            'slug' => 'api-verify-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
        LearningCertificate::query()->create([
            'learning_course_id' => $course->id,
            'learning_enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'code' => 'AIR-API-VERIFY',
            'issued_at' => now(),
        ]);

        $this->getJson('/api/v1/public/learning/certificates/air-api-verify')
            ->assertOk()
            ->assertJsonPath('data.code', 'AIR-API-VERIFY')
            ->assertJsonPath('data.student_name', 'Teilnehmende Person')
            ->assertJsonPath('data.course_title', 'API Verify Kurs')
            ->assertJsonPath('data.progress_percent', 100)
            ->assertJsonMissingPath('data.user.email');

        $this->getJson('/api/v1/public/learning/certificates/unknown-code')
            ->assertNotFound()
            ->assertJsonPath('message', 'Certificate not found.');
    }

    public function test_learning_studio_contains_sales_analytics_for_linked_products(): void
    {
        $tutor = User::factory()->create();
        $buyer = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Analytics Kurs',
            'slug' => 'analytics-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'is_free' => false,
            'price_cents' => 4900,
            'published_at' => now(),
        ]);
        $product = MarketplaceProduct::query()->create([
            'user_id' => $tutor->id,
            'learning_course_id' => $course->id,
            'title' => 'Analytics Kurs Zugang',
            'description' => 'Digitaler Kurszugang',
            'category' => 'course',
            'offer_type' => 'online_course',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
            'price_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
        ]);
        $order = CommerceOrder::query()->create([
            'user_id' => $buyer->id,
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $order->items()->create([
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'quantity' => 1,
            'unit_gross_cents' => 4900,
            'total_cents' => 4900,
            'currency' => 'EUR',
            'is_shippable' => false,
        ]);

        $this->actingAs($tutor)
            ->get(route('auth.learning.studio.index', ['course' => $course->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Learning/Studio')
                ->where('selectedCourse.analytics.sales_count', 1)
                ->where('selectedCourse.analytics.gross_sales_cents', 4900)
                ->where('selectedCourse.analytics.net_revenue_cents', 4900)
            );
    }

    public function test_tutor_can_upload_learning_assets(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');

        $tutor = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Upload Kurs',
            'slug' => 'upload-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($tutor)
            ->postJson(route('auth.learning.studio.uploads.store', $course), [
                'purpose' => 'lesson_attachment',
                'file' => UploadedFile::fake()->create('uebung.pdf', 120, 'application/pdf'),
            ]);

        $response->assertOk()
            ->assertJsonPath('name', 'uebung.pdf')
            ->assertJsonPath('mime_type', 'application/pdf');

        Storage::disk('public')->assertExists($response->json('path'));
    }

    public function test_video_watch_progress_can_complete_lesson(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Video Kurs',
            'slug' => 'video-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Video Lektion',
            'type' => 'video',
            'video_url' => 'https://example.com/video.mp4',
            'duration_minutes' => 10,
            'position' => 1,
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->putJson(route('auth.learning.lessons.progress.update', [$course, $lesson]), [
                'watch_seconds' => 480,
            ])
            ->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('watch_percent', 80);

        $this->assertTrue(LearningLessonProgress::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('learning_lesson_id', $lesson->id)
            ->where('completed', true)
            ->exists());
        $this->assertSame(100, $enrollment->fresh()->progress_percent);
    }

    public function test_drip_lesson_stays_locked_until_release_day(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Drip Kurs',
            'slug' => 'drip-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Tag Drei',
            'content' => 'DRIP_SECRET_CONTENT',
            'duration_minutes' => 5,
            'unlock_after_days' => 3,
            'position' => 1,
        ]);
        LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('guest.learning.courses.show', $course))
            ->assertOk()
            ->assertSee('drip_locked')
            ->assertDontSee('DRIP_SECRET_CONTENT');

        $this->actingAs($student)
            ->putJson(route('auth.learning.lessons.complete', [$course, $lesson]))
            ->assertForbidden();

        $this->travel(4)->days();

        $this->actingAs($student)
            ->get(route('guest.learning.courses.show', $course))
            ->assertOk()
            ->assertSee('DRIP_SECRET_CONTENT');
    }

    public function test_student_can_submit_assignment_and_tutor_can_grade_it(): void
    {
        Mail::fake();

        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Aufgaben Kurs',
            'slug' => 'aufgaben-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Technik Video',
            'position' => 1,
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $assignment = LearningAssignment::query()->create([
            'learning_course_id' => $course->id,
            'learning_lesson_id' => $lesson->id,
            'title' => 'Videoanalyse einreichen',
            'instructions' => 'Beschreibe deine Technik.',
            'points' => 100,
            'is_required' => true,
        ]);

        $this->actingAs($student)
            ->post(route('auth.learning.assignments.submissions.store', [$course, $assignment]), [
                'body' => 'Meine Analyse',
                'attachment_url' => 'https://example.com/video.mp4',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $submission = LearningAssignmentSubmission::query()->firstOrFail();
        $this->assertSame($enrollment->id, $submission->learning_enrollment_id);
        $this->assertSame('submitted', $submission->status);

        $this->actingAs($tutor)
            ->put(route('auth.learning.studio.assignment-submissions.update', [$course, $submission]), [
                'status' => 'passed',
                'score' => 92,
                'feedback' => 'Starke Analyse.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('passed', $submission->fresh()->status);
        $this->assertSame(92, $submission->fresh()->score);
        $this->assertTrue(Notification::query()
            ->where('user_id', $student->id)
            ->where('type', 'learning.assignment.graded')
            ->exists());
    }

    public function test_required_assignment_blocks_certificate_until_it_is_passed(): void
    {
        Mail::fake();

        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Pflichtaufgabe Kurs',
            'slug' => 'pflichtaufgabe-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Grundlage',
            'position' => 1,
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $assignment = LearningAssignment::query()->create([
            'learning_course_id' => $course->id,
            'learning_lesson_id' => $lesson->id,
            'title' => 'Techniknachweis',
            'instructions' => 'Reiche deinen Nachweis ein.',
            'points' => 100,
            'is_required' => true,
        ]);

        $this->actingAs($student)
            ->put(route('auth.learning.lessons.complete', [$course, $lesson]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(50, $enrollment->fresh()->progress_percent);
        $this->assertNull($enrollment->fresh()->completed_at);
        $this->assertNull($enrollment->fresh()->certificate);

        $this->actingAs($student)
            ->post(route('auth.learning.assignments.submissions.store', [$course, $assignment]), [
                'body' => 'Mein Nachweis',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $submission = LearningAssignmentSubmission::query()->firstOrFail();
        $this->assertNull($enrollment->fresh()->completed_at);

        $this->actingAs($tutor)
            ->put(route('auth.learning.studio.assignment-submissions.update', [$course, $submission]), [
                'status' => 'passed',
                'score' => 88,
                'feedback' => 'Bestanden.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(100, $enrollment->fresh()->progress_percent);
        $this->assertNotNull($enrollment->fresh()->completed_at);
        $this->assertNotNull($enrollment->fresh()->certificate);
    }

    public function test_local_lesson_video_is_served_only_to_enrolled_users(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('learning/videos/sprint.mp4', 'VIDEO_BYTES');

        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $other = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Secure Video Kurs',
            'slug' => 'secure-video-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Sprint Video',
            'video_url' => url('/storage/learning/videos/sprint.mp4'),
            'position' => 1,
        ]);
        LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($other)
            ->get(route('auth.learning.lessons.video', [$course, $lesson]))
            ->assertForbidden();

        $this->assertTrue(LearningSecurityEvent::query()
            ->where('learning_course_id', $course->id)
            ->where('learning_lesson_id', $lesson->id)
            ->where('type', 'video_signature_invalid')
            ->exists());

        $signedUrl = URL::temporarySignedRoute('auth.learning.lessons.video', now()->addMinutes(15), [$course, $lesson]);

        $this->actingAs($other)
            ->get($signedUrl)
            ->assertForbidden();

        $this->assertTrue(LearningSecurityEvent::query()
            ->where('learning_course_id', $course->id)
            ->where('learning_lesson_id', $lesson->id)
            ->where('type', 'video_access_denied')
            ->exists());

        $response = $this->actingAs($student)
            ->get($signedUrl);

        $response->assertOk();
        $this->assertSame('VIDEO_BYTES', $response->streamedContent());
    }

    public function test_learning_drip_command_notifies_once_when_lesson_unlocks(): void
    {
        Mail::fake();

        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Reminder Kurs',
            'slug' => 'reminder-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Tag Zwei',
            'unlock_after_days' => 2,
            'position' => 1,
        ]);
        LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now()->subDays(3),
        ]);

        $this->artisan('airmius:send-learning-drip-notifications')->assertSuccessful();
        $this->artisan('airmius:send-learning-drip-notifications')->assertSuccessful();

        $this->assertSame(1, LearningEmailDelivery::query()
            ->where('learning_lesson_id', $lesson->id)
            ->where('type', 'drip_unlocked')
            ->count());
        $this->assertSame(1, Notification::query()
            ->where('user_id', $student->id)
            ->where('type', 'learning.drip.unlocked')
            ->count());
    }

    public function test_learning_monitor_command_reports_missing_video_files(): void
    {
        Storage::fake('public');

        $tutor = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Monitoring Kurs',
            'slug' => 'monitoring-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Fehlendes Video',
            'video_url' => url('/storage/learning/videos/missing.mp4'),
            'position' => 1,
        ]);

        $this->artisan('airmius:monitor-learning-health')
            ->assertExitCode(1);
    }

    public function test_quiz_attached_to_drip_lesson_stays_locked_until_release_day(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Drip Quiz Kurs',
            'slug' => 'drip-quiz-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $section = $course->sections()->create(['title' => 'Start', 'position' => 1]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Tag Drei Quiz',
            'unlock_after_days' => 3,
            'position' => 1,
        ]);
        $quiz = LearningQuiz::query()->create([
            'learning_course_id' => $course->id,
            'learning_lesson_id' => $lesson->id,
            'title' => 'Gesperrter Check',
            'pass_percent' => 70,
        ]);
        $question = LearningQuizQuestion::query()->create([
            'learning_quiz_id' => $quiz->id,
            'question' => 'Was ist richtig?',
            'options' => ['Plan', 'Zufall'],
            'correct_options' => ['Plan'],
            'position' => 1,
        ]);
        LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->postJson(route('auth.learning.quizzes.attempts.store', [$course, $quiz]), [
                'answers' => [$question->id => ['Plan']],
            ])
            ->assertForbidden();

        $this->travel(4)->days();

        $this->actingAs($student)
            ->post(route('auth.learning.quizzes.attempts.store', [$course, $quiz]), [
                'answers' => [$question->id => ['Plan']],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
}
