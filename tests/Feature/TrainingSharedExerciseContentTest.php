<?php

namespace Tests\Feature;

use App\Models\TrainingExercise;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingSharedExerciseContentTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('public');
        $user = User::factory()->create(['language' => 'de']);
        $plan = TrainingPlan::create(['created_by' => $user->id, 'title' => 'Gemeinsamer Plan', 'cadence' => 'weekly', 'status' => 'draft', 'share_permission' => 'read', 'settings' => ['target_type' => 'self']]);
        $this->actingAs($user);
        Sanctum::actingAs($user);

        return [$user, $plan];
    }

    public function test_web_library_and_native_api_edit_the_same_texts_and_images(): void
    {
        $this->fixture();
        $this->post(route('auth.training.exercises.store'), [
            'scope' => 'personal', 'name' => 'Kniebeuge', 'difficulty' => 'all', 'original_locale' => 'de',
            'instructions' => 'Langsam absenken', 'notes' => 'Kontrolliert bewegen', 'todos' => ['Atmen'],
            'translations' => ['fr' => ['title' => 'Squat', 'instructions' => 'Descendez lentement']],
            'video_url' => 'https://example.com/video',
            'explanation_images' => [['file' => UploadedFile::fake()->image('start.jpg'), 'texts' => ['de' => ['caption' => 'Start'], 'fr' => ['caption' => 'Départ']]]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('auth.training.exercises.index'));
        $exercise = TrainingExercise::firstOrFail();
        $this->withHeader('X-Locale', 'fr')->getJson("/api/v1/training/exercises/{$exercise->id}")
            ->assertOk()->assertJsonPath('data.name', 'Squat')->assertJsonPath('data.original_content.title', 'Kniebeuge')
            ->assertJsonPath('data.instructions', 'Descendez lentement')->assertJsonPath('data.notes', 'Kontrolliert bewegen')
            ->assertJsonPath('data.explanation_images.0.caption', 'Départ')->assertJsonPath('data.video_url', 'https://example.com/video');
        $image = $exercise->explanation_images[0];
        $this->putJson("/api/v1/training/exercises/{$exercise->id}", [
            'name' => 'Kniebeuge aus der App', 'difficulty' => 'beginner', 'instructions' => 'Neue Originalanleitung',
            'translations' => ['en' => ['title' => 'Squat from the app']],
            'explanation_images' => [['id' => $image['id'], 'texts' => ['de' => ['caption' => 'Ausgangsposition']]]],
        ])->assertOk();
        $this->get(route('auth.training.exercises.edit', $exercise))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Dashboard/Training/ExerciseEdit')->where('exercise.original_content.title', 'Kniebeuge aus der App')
            ->where('exercise.instructions', 'Neue Originalanleitung')->where('exercise.translations.en.title', 'Squat from the app')
            ->where('exercise.explanation_images.0.caption', 'Ausgangsposition'));
        Storage::disk('public')->assertExists($image['path']);
    }

    public function test_native_plan_upload_is_readable_and_editable_in_web_without_losing_original_text(): void
    {
        [, $plan] = $this->fixture();
        $this->post("/api/v1/training/plans/{$plan->id}/items", [
            'title' => 'Original aus App', 'original_locale' => 'de', 'instructions' => 'Originalanleitung', 'notes' => 'Hinweis',
            'translations' => ['fr' => ['title' => 'Depuis l’application']],
            'explanation_images' => [['file' => UploadedFile::fake()->image('mobile.png'), 'texts' => ['de' => ['caption' => 'Position']]]],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.items.0.instructions', 'Originalanleitung');
        $item = $plan->items()->firstOrFail();
        $this->withHeader('X-Locale', 'fr')->get(route('auth.training.plans.items.show', [$plan, $item]))->assertInertia(fn (Assert $page) => $page
            ->where('item.title', 'Depuis l’application')->where('item.instructions', 'Originalanleitung')->where('item.explanation_images.0.caption', 'Position'));
        $this->get(route('auth.training.plans.items.edit', [$plan, $item]))->assertInertia(fn (Assert $page) => $page->where('item.title', 'Original aus App'));
        $this->put(route('auth.training.plans.items.update', [$plan, $item]), [
            'title' => 'Im Web bearbeitet', 'instructions' => 'Web-Anleitung', 'original_locale' => 'de',
            'translations' => ['en' => ['title' => 'Edited on web']],
        ])->assertSessionHasNoErrors();
        $this->withHeader('X-Locale', 'en')->getJson("/api/v1/training/plans/{$plan->id}")->assertOk()
            ->assertJsonPath('data.items.0.title', 'Edited on web')->assertJsonPath('data.items.0.instructions', 'Web-Anleitung')
            ->assertJsonPath('data.items.0.original_content.title', 'Im Web bearbeitet')->assertJsonCount(1, 'data.items.0.explanation_images');
    }

    public function test_library_snapshot_preserves_all_languages_and_shared_images_after_source_changes(): void
    {
        [, $plan] = $this->fixture();
        $this->post('/api/v1/training/exercises', [
            'scope' => 'personal', 'name' => 'Bibliotheksübung', 'difficulty' => 'all', 'original_locale' => 'de',
            'description' => 'Beschreibung', 'instructions' => 'Anleitung', 'notes' => 'Hinweis', 'todos' => ['Aufgabe'], 'equipment' => ['Matte'],
            'translations' => ['fr' => ['title' => 'Exercice', 'instructions' => 'Instructions']], 'video_url' => 'https://example.com/video',
            'explanation_images' => [['file' => UploadedFile::fake()->image('source.jpg')]],
        ], ['Accept' => 'application/json'])->assertCreated();
        $exercise = TrainingExercise::firstOrFail();
        $this->post(route('auth.training.exercises.add-to-plan', $exercise), ['training_plan_id' => $plan->id])->assertRedirect();
        $item = $plan->items()->firstOrFail();
        $this->assertSame('Beschreibung', $item->description);
        $this->assertSame('Anleitung', $item->instructions);
        $this->assertSame(['Aufgabe'], $item->todos);
        $this->assertSame('Matte', $item->metrics['Equipment']);
        $this->assertSame($exercise->translations, $item->translations);
        $this->assertSame($exercise->explanation_images, $item->explanation_images);
        $this->assertSame($exercise->video_url, $item->video_url);
        $this->putJson("/api/v1/training/exercises/{$exercise->id}", ['name' => 'Geändert', 'difficulty' => 'all', 'explanation_images' => []])->assertOk();
        Storage::disk('public')->assertExists($item->explanation_images[0]['path']);
        $this->withHeader('X-Locale', 'fr')->getJson("/api/v1/training/plans/{$plan->id}")->assertJsonPath('data.items.0.instructions', 'Instructions');
        $path = $item->explanation_images[0]['path'];
        $item->delete();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_library_cannot_attach_another_exercises_image_or_exceed_image_limits(): void
    {
        $this->fixture();
        $exercise = TrainingExercise::create(['created_by' => auth()->id(), 'name' => 'Original', 'difficulty' => 'all']);
        $this->putJson("/api/v1/training/exercises/{$exercise->id}", ['name' => 'Original', 'difficulty' => 'all', 'explanation_images' => [['id' => 'foreign']]])->assertUnprocessable()->assertJsonValidationErrors('explanation_images.0.id');
        $this->putJson("/api/v1/training/exercises/{$exercise->id}", ['name' => 'Original', 'difficulty' => 'all', 'explanation_images' => array_fill(0, 13, ['id' => 'foreign'])])->assertUnprocessable()->assertJsonValidationErrors('explanation_images');
        $this->putJson("/api/v1/training/exercises/{$exercise->id}", ['name' => 'Original', 'difficulty' => 'all', 'original_locale' => 'es'])->assertUnprocessable()->assertJsonValidationErrors('original_locale');
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        Sanctum::actingAs($outsider);
        $this->get(route('auth.training.exercises.edit', $exercise))->assertNotFound();
        $this->putJson("/api/v1/training/exercises/{$exercise->id}", ['name' => 'Hijack', 'difficulty' => 'all'])->assertForbidden();
    }

    public function test_multipart_upload_can_clear_tasks_and_equipment_without_erasing_omitted_filters(): void
    {
        $this->fixture();
        $exercise = TrainingExercise::create(['created_by' => auth()->id(), 'name' => 'Original', 'difficulty' => 'all', 'todos' => ['Aufgabe'], 'equipment' => ['Matte'], 'focus_areas' => ['Technik'], 'target_age_group' => 'U18']);
        $this->post("/api/v1/training/exercises/{$exercise->id}", [
            '_method' => 'PUT', 'name' => 'Original', 'difficulty' => 'all',
            'content_empty_arrays' => ['todos', 'equipment'],
            'explanation_images' => [['file' => UploadedFile::fake()->image('new.jpg')]],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.todos', [])->assertJsonPath('data.equipment', [])
            ->assertJsonPath('data.focus_areas', ['Technik'])->assertJsonPath('data.target_age_group', 'U18');
        $this->assertCount(1, $exercise->fresh()->explanation_images);
    }

    public function test_deleting_a_plan_in_the_app_removes_its_unshared_explanation_images(): void
    {
        [, $plan] = $this->fixture();
        $this->post("/api/v1/training/plans/{$plan->id}/items", [
            'title' => 'App-Bilder', 'explanation_images' => [['file' => UploadedFile::fake()->image('app.jpg')]],
        ], ['Accept' => 'application/json'])->assertCreated();
        $path = $plan->items()->firstOrFail()->explanation_images[0]['path'];
        $this->deleteJson("/api/v1/training/plans/{$plan->id}")->assertOk();
        Storage::disk('public')->assertMissing($path);
    }
}
