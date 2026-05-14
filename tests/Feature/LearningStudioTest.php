<?php

namespace Tests\Feature;

use App\Models\LearningCourse;
use App\Models\LearningLesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
