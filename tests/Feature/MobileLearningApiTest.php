<?php

namespace Tests\Feature;

use App\Models\LearningAssignment;
use App\Models\LearningCourse;
use App\Models\LearningCourseSection;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningQuiz;
use App\Models\LearningQuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileLearningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_enroll_learn_complete_and_receive_a_certificate(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Sicher im Sportverein',
            'slug' => 'sicher-im-sportverein',
            'subtitle' => 'Datenschutz und Jugendschutz',
            'description' => 'Ein verständlicher Grundkurs.',
            'category' => 'safety',
            'level' => 'beginner',
            'language' => 'de',
            'status' => 'published',
            'is_public' => true,
            'is_free' => true,
            'estimated_minutes' => 30,
            'published_at' => now(),
        ]);
        $section = LearningCourseSection::query()->create([
            'learning_course_id' => $course->id,
            'title' => 'Grundlagen',
            'position' => 1,
        ]);
        $firstLesson = LearningLesson::query()->create([
            'learning_course_id' => $course->id,
            'learning_course_section_id' => $section->id,
            'title' => 'Datenschutz verstehen',
            'summary' => 'Die wichtigsten Begriffe.',
            'content' => 'Personenbezogene Daten müssen geschützt werden.',
            'duration_minutes' => 10,
            'position' => 1,
            'is_preview' => false,
        ]);
        $secondLesson = LearningLesson::query()->create([
            'learning_course_id' => $course->id,
            'learning_course_section_id' => $section->id,
            'title' => 'Sicher handeln',
            'content' => 'Prüfe Berechtigungen und Einwilligungen.',
            'duration_minutes' => 10,
            'position' => 2,
            'is_preview' => false,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/learning')
            ->assertOk()
            ->assertJsonPath('data.catalog.0.title', 'Sicher im Sportverein')
            ->assertJsonPath('data.catalog.0.is_enrolled', false)
            ->assertJsonCount(0, 'data.enrollments');

        $this->getJson("/api/v1/learning/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.sections.0.lessons.0.locked', true)
            ->assertJsonPath('data.sections.0.lessons.0.content', null);

        $this->postJson("/api/v1/learning/courses/{$course->id}/enroll")
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $this->getJson("/api/v1/learning/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.sections.0.lessons.0.locked', false)
            ->assertJsonPath(
                'data.sections.0.lessons.0.content',
                'Personenbezogene Daten müssen geschützt werden.',
            );

        $this->postJson("/api/v1/learning/courses/{$course->id}/lessons/{$firstLesson->id}/notes", [
            'body' => 'Einwilligungen immer dokumentieren.',
        ])->assertCreated();

        $this->postJson("/api/v1/learning/courses/{$course->id}/lessons/{$firstLesson->id}/comments", [
            'body' => 'Wie lange müssen Nachweise aufbewahrt werden?',
        ])->assertCreated();

        $this->putJson("/api/v1/learning/courses/{$course->id}/lessons/{$firstLesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.progress_percent', 50)
            ->assertJsonPath('data.lesson_id', $firstLesson->id)
            ->assertJsonCount(0, 'data.new_badges');

        $completion = $this->putJson(
            "/api/v1/learning/courses/{$course->id}/lessons/{$secondLesson->id}/complete",
        )
            ->assertOk()
            ->assertJsonPath('data.progress_percent', 100)
            ->assertJsonPath('data.certificate.course_title', 'Sicher im Sportverein')
            ->assertJsonPath('data.new_badges.0.badge.key', 'player_course_completed')
            ->assertJsonPath('data.new_badges.0.reason', 'course_completed');

        $certificateId = $completion->json('data.certificate.id');

        $this->getJson("/api/v1/learning/certificates/{$certificateId}")
            ->assertOk()
            ->assertJsonPath('data.progress_percent', 100)
            ->assertJsonPath('data.course_title', 'Sicher im Sportverein');

        $this->get("/api/v1/learning/certificates/{$certificateId}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseHas('learning_lesson_notes', [
            'user_id' => $student->id,
            'learning_lesson_id' => $firstLesson->id,
        ]);
        $this->assertDatabaseHas('learning_certificates', [
            'user_id' => $student->id,
            'learning_course_id' => $course->id,
        ]);
        $this->assertDatabaseHas('user_badges', [
            'user_id' => $student->id,
            'reason' => 'course_completed',
        ]);
        $this->assertDatabaseHas('gamification_xp_events', [
            'user_id' => $student->id,
            'reason' => 'course_completed',
            'amount' => 15,
        ]);

        $this->putJson(
            "/api/v1/learning/courses/{$course->id}/lessons/{$secondLesson->id}/complete",
        )
            ->assertOk()
            ->assertJsonCount(0, 'data.new_badges');

        $this->assertDatabaseCount('learning_certificates', 1);
        $this->assertDatabaseCount('user_badges', 1);
    }

    public function test_paid_courses_cannot_be_enrolled_for_free_and_private_courses_are_hidden(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $paid = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Trainer Pro',
            'slug' => 'trainer-pro',
            'status' => 'published',
            'is_public' => true,
            'is_free' => false,
            'price_cents' => 4900,
            'published_at' => now(),
        ]);
        $private = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Interner Entwurf',
            'slug' => 'interner-entwurf',
            'status' => 'draft',
            'is_public' => false,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/learning')
            ->assertOk()
            ->assertJsonCount(1, 'data.catalog')
            ->assertJsonPath('data.catalog.0.id', $paid->id);

        $this->postJson("/api/v1/learning/courses/{$paid->id}/enroll")
            ->assertForbidden();

        $this->getJson("/api/v1/learning/courses/{$private->id}")
            ->assertNotFound();
    }

    public function test_quiz_answers_stay_secret_and_submissions_belong_to_the_enrollment(): void
    {
        $tutor = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Regelwissen',
            'slug' => 'regelwissen',
            'status' => 'published',
            'is_public' => true,
            'is_free' => true,
            'published_at' => now(),
        ]);
        $lesson = LearningLesson::query()->create([
            'learning_course_id' => $course->id,
            'title' => 'Regeln',
            'content' => 'Lerninhalt',
            'position' => 1,
        ]);
        $quiz = LearningQuiz::query()->create([
            'learning_course_id' => $course->id,
            'learning_lesson_id' => $lesson->id,
            'title' => 'Wissenscheck',
            'pass_percent' => 100,
        ]);
        $question = LearningQuizQuestion::query()->create([
            'learning_quiz_id' => $quiz->id,
            'question' => 'Welche Antwort stimmt?',
            'options' => ['A', 'B'],
            'correct_options' => ['B'],
            'position' => 1,
        ]);
        $assignment = LearningAssignment::query()->create([
            'learning_course_id' => $course->id,
            'learning_lesson_id' => $lesson->id,
            'title' => 'Praxisfall',
            'instructions' => 'Beschreibe die sichere Lösung.',
            'is_required' => true,
        ]);
        LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($student);

        $detail = $this->getJson("/api/v1/learning/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.quizzes.0.questions.0.question', 'Welche Antwort stimmt?');

        $this->assertArrayNotHasKey(
            'correct_options',
            $detail->json('data.quizzes.0.questions.0'),
        );

        $this->postJson("/api/v1/learning/courses/{$course->id}/quizzes/{$quiz->id}/attempts", [
            'answers' => [(string) $question->id => 'A'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.passed', false)
            ->assertJsonPath('data.score_percent', 0);

        $this->postJson("/api/v1/learning/courses/{$course->id}/quizzes/{$quiz->id}/attempts", [
            'answers' => [(string) $question->id => 'B'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.score_percent', 100);

        $this->postJson(
            "/api/v1/learning/courses/{$course->id}/assignments/{$assignment->id}/submissions",
            ['body' => 'Nur notwendige Daten verarbeiten.'],
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('learning_assignment_submissions', [
            'learning_assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'status' => 'submitted',
        ]);
    }
}
