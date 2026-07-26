<?php

namespace Tests\Feature;

use App\Models\LearningCourse;
use App\Models\LearningLessonComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileLearningStudioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_build_course_structure_with_mobile_api(): void
    {
        $creator = User::factory()->create();
        Sanctum::actingAs($creator);

        $courseResponse = $this->postJson('/api/v1/learning-studio/courses', [
            'title' => 'Sicheres Lauftraining',
            'subtitle' => 'Von null auf fünf Kilometer',
            'description' => 'Ein verständlicher Laufkurs.',
            'category' => 'training',
            'sport_type' => 'Laufen',
            'level' => 'beginner',
            'language' => 'de',
            'status' => 'draft',
            'is_public' => false,
            'is_free' => true,
            'price_cents' => 0,
            'learning_goals_text' => "Sicher aufwärmen\nBelastung einschätzen",
            'requirements_text' => 'Bequeme Laufschuhe',
            'target_groups_text' => "Einsteiger\nWiedereinsteiger",
            'sales_points_text' => 'Kurze, verständliche Einheiten',
            'faq_items_text' => 'Brauche ich Erfahrung? | Nein.',
            'guarantee_text' => 'Fragen werden innerhalb von zwei Werktagen beantwortet.',
            'tags_text' => 'laufen, einsteiger, sicherheit',
            'certificate_logo_url' => 'https://example.test/certificate-logo.png',
            'certificate_signature_name' => 'Mira Trainerin',
            'certificate_footer_text' => 'Airmius Lernstudio',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Sicheres Lauftraining')
            ->assertJsonPath('data.learning_goals.1', 'Belastung einschätzen')
            ->assertJsonPath('data.target_groups.1', 'Wiedereinsteiger')
            ->assertJsonPath('data.faq_items.0.answer', 'Nein.')
            ->assertJsonPath('data.certificate_signature_name', 'Mira Trainerin')
            ->assertJsonCount(1, 'data.sections');

        $courseId = (int) $courseResponse->json('data.id');
        $sectionId = (int) $courseResponse->json('data.sections.0.id');

        $this->postJson("/api/v1/learning-studio/courses/{$courseId}/sections", [
            'title' => 'Trainingswoche 1',
            'description' => 'Sanfter Einstieg.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Trainingswoche 1');

        $lesson = $this->postJson("/api/v1/learning-studio/courses/{$courseId}/lessons", [
            'learning_course_section_id' => $sectionId,
            'title' => 'Der erste Lauf',
            'type' => 'lesson',
            'summary' => 'Locker beginnen.',
            'content' => 'Starte mit fünf Minuten Gehen.',
            'duration_minutes' => 15,
            'is_preview' => true,
            'unlock_after_days' => 3,
            'attachments_text' => "https://example.test/warmup.pdf\nhttps://example.test/checklist.pdf",
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Der erste Lauf')
            ->assertJsonPath('data.unlock_after_days', 3)
            ->assertJsonPath('data.attachments.1.url', 'https://example.test/checklist.pdf');

        $lessonId = (int) $lesson->json('data.id');
        $this->putJson("/api/v1/learning-studio/courses/{$courseId}/lessons/{$lessonId}", [
            'learning_course_section_id' => $sectionId,
            'title' => 'Der erste sichere Lauf',
            'type' => 'lesson',
            'summary' => 'Locker beginnen.',
            'content' => 'Starte mit fünf Minuten Gehen.',
            'duration_minutes' => 20,
            'is_preview' => true,
            'unlock_after_days' => 5,
            'attachments_text' => 'https://example.test/update.pdf',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Der erste sichere Lauf')
            ->assertJsonPath('data.unlock_after_days', 5)
            ->assertJsonPath('data.attachments.0.url', 'https://example.test/update.pdf');

        $this->getJson("/api/v1/learning-studio?course={$courseId}")
            ->assertOk()
            ->assertJsonPath('data.selectedCourse.id', $courseId)
            ->assertJsonPath('data.selectedCourse.analytics.average_progress', 0);
    }

    public function test_creator_can_grant_and_revoke_enrollment_but_not_foreign_course(): void
    {
        $creator = User::factory()->create();
        $student = User::factory()->create(['email' => 'student@example.test']);
        $course = $this->courseFor($creator);
        $foreignCourse = $this->courseFor(User::factory()->create(), [
            'title' => 'Fremder Kurs',
            'slug' => 'fremder-kurs',
        ]);
        Sanctum::actingAs($creator);

        $enrollment = $this->postJson("/api/v1/learning-studio/courses/{$course->id}/enrollments", [
            'email' => $student->email,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', $student->email);

        $enrollmentId = (int) $enrollment->json('data.id');
        $this->putJson("/api/v1/learning-studio/courses/{$course->id}/enrollments/{$enrollmentId}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/v1/learning-studio/courses/{$foreignCourse->id}/sections", [
            'title' => 'Unbefugt',
        ])->assertForbidden();
    }

    public function test_other_creator_cannot_update_or_delete_course_lessons(): void
    {
        $owner = User::factory()->create();
        $course = $this->courseFor($owner);
        $section = $course->sections()->create([
            'title' => 'Start',
            'position' => 1,
        ]);
        $lesson = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Privat',
            'type' => 'lesson',
            'position' => 1,
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/learning-studio/courses/{$course->id}", [
            'title' => 'Manipuliert',
            'category' => 'training',
            'level' => 'beginner',
            'language' => 'de',
            'status' => 'draft',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/learning-studio/courses/{$course->id}/lessons/{$lesson->id}")
            ->assertForbidden();
    }

    public function test_creator_can_reorder_lessons_moderate_questions_and_export_report(): void
    {
        $creator = User::factory()->create();
        $student = User::factory()->create();
        $course = $this->courseFor($creator);
        $section = $course->sections()->create([
            'title' => 'Start',
            'position' => 1,
        ]);
        $first = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Erste Lektion',
            'type' => 'lesson',
            'position' => 1,
        ]);
        $second = $course->lessons()->create([
            'learning_course_section_id' => $section->id,
            'title' => 'Zweite Lektion',
            'type' => 'lesson',
            'position' => 2,
        ]);
        $question = LearningLessonComment::query()->create([
            'learning_lesson_id' => $first->id,
            'user_id' => $student->id,
            'body' => 'Wie oft soll ich üben?',
            'visibility' => 'course',
            'status' => 'open',
        ]);
        Sanctum::actingAs($creator);

        $this->putJson("/api/v1/learning-studio/courses/{$course->id}/lessons/reorder", [
            'lessons' => [
                ['id' => $first->id, 'position' => 2],
                ['id' => $second->id, 'position' => 1],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.sections.0.lessons.0.id', $second->id);

        $this->postJson("/api/v1/learning-studio/courses/{$course->id}/comments/{$question->id}/replies", [
            'body' => 'Drei kurze Einheiten pro Woche sind ein guter Start.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.questions.0.status', 'answered')
            ->assertJsonPath('data.questions.0.replies.0.body', 'Drei kurze Einheiten pro Woche sind ein guter Start.');

        $this->putJson("/api/v1/learning-studio/courses/{$course->id}/comments/{$question->id}", [
            'status' => 'resolved',
        ])
            ->assertOk()
            ->assertJsonPath('data.questions.0.status', 'resolved');

        $this->get("/api/v1/learning-studio/courses/{$course->id}/report.csv", [
            'Accept' => 'text/csv',
        ])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('Fortschritt');
    }

    private function courseFor(User $creator, array $overrides = []): LearningCourse
    {
        return LearningCourse::query()->create(array_merge([
            'user_id' => $creator->id,
            'title' => 'Eigener Kurs',
            'slug' => 'eigener-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'language' => 'de',
            'status' => 'draft',
            'is_public' => false,
            'is_free' => true,
            'price_cents' => 0,
        ], $overrides));
    }
}
