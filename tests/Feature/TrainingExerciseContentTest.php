<?php

namespace Tests\Feature;

use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Training\TrainingExerciseContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrainingExerciseContentTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create(['language' => 'de']);
        $plan = TrainingPlan::create(['created_by' => $user->id, 'title' => 'Training', 'cadence' => 'weekly', 'status' => 'draft', 'share_permission' => 'read', 'settings' => ['target_type' => 'self']]);
        $this->actingAs($user);
        Storage::fake('public');

        return [$user, $plan];
    }

    public function test_editor_pages_require_write_access_and_matching_plan(): void
    {
        [, $plan] = $this->fixture();
        $item = $plan->items()->create(['title' => 'Original', 'original_locale' => 'de', 'translations' => ['fr' => ['title' => 'Traduction']]]);
        $this->get(route('auth.training.plans.items.create', $plan))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Dashboard/Training/PlanItemEdit'));
        $this->withHeader('X-Locale', 'fr')->get(route('auth.training.plans.items.edit', [$plan, $item]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('item.title', 'Original')->where('item.translations.fr.title', 'Traduction'));
        $other = TrainingPlan::create(['created_by' => $plan->created_by, 'title' => 'Other', 'cadence' => 'weekly', 'settings' => ['target_type' => 'self']]);
        $this->get(route('auth.training.plans.items.edit', [$other, $item]))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('auth.training.plans.items.create', $plan))->assertForbidden();
        $this->get(route('auth.training.plans.items.edit', [$plan, $item]))->assertForbidden();
    }

    public function test_create_stores_multiple_images_and_translations_then_redirects_to_detail(): void
    {
        [, $plan] = $this->fixture();
        $response = $this->post(route('auth.training.plans.items.store', $plan), [
            'title' => 'Kniebeuge', 'original_locale' => 'de', 'instructions' => 'Langsam absenken.', 'editor_page' => '1',
            'translations' => ['fr' => ['title' => 'Squat', 'instructions' => 'Descendez lentement.']],
            'explanation_images' => [
                ['file' => UploadedFile::fake()->image('start.jpg'), 'texts' => ['de' => ['caption' => 'Start', 'alt' => 'Aufrecht stehen']]],
                ['file' => UploadedFile::fake()->image('end.png'), 'texts' => ['de' => ['caption' => 'Ende']]],
            ],
        ])->assertSessionHasNoErrors();
        $item = $plan->items()->firstOrFail();
        $response->assertRedirect(route('auth.training.plans.items.show', [$plan, $item]));
        $this->assertSame('Descendez lentement.', $item->translations['fr']['instructions']);
        $this->assertCount(2, $item->explanation_images);
        $this->assertSame(route('auth.training.items.images.show', [$item, $item->explanation_images[0]['id']]), app(TrainingExerciseContentService::class)->present($item)['image_url']);
        foreach ($item->explanation_images as $image) {
            Storage::disk('public')->assertExists($image['path']);
        }
        $this->withHeader('X-Locale', 'fr')->get(route('auth.training.plans.items.show', [$plan, $item]))->assertInertia(fn (Assert $page) => $page->where('item.title', 'Squat')->where('item.instructions', 'Descendez lentement.')->where('item.explanation_images.0.caption', 'Start')->where('item.uses_original_fallback', true));
    }

    public function test_partial_translation_falls_back_per_field_and_original_locale_ignores_stale_translation(): void
    {
        [, $plan] = $this->fixture();
        $item = $plan->items()->create([
            'title' => 'Original', 'description' => 'Beschreibung', 'instructions' => 'Anleitung', 'todos' => ['Atmen'], 'original_locale' => 'de',
            'translations' => ['fr' => ['title' => 'Traduit', 'instructions' => '  ', 'todos' => "Respirez\nRépétez"], 'de' => ['title' => 'Veraltet']],
        ]);
        $service = app(TrainingExerciseContentService::class);
        $fr = $service->present($item, 'fr-FR');
        $this->assertSame('Traduit', $fr['title']);
        $this->assertSame('Anleitung', $fr['instructions']);
        $this->assertSame(['Respirez', 'Répétez'], $fr['todos']);
        $this->assertTrue($fr['uses_original_fallback']);
        $this->assertSame('Original', $service->present($item, 'ar')['title']);
        $this->assertSame('Original', $service->present($item, 'de')['title']);
        $this->assertFalse($service->present($item, 'de')['uses_original_fallback']);
        $legacy = $plan->items()->create(['title' => 'Bestehend', 'todos' => ['Weiter']]);
        $this->assertSame('Bestehend', $service->present($legacy, 'fr')['title']);
    }

    public function test_images_can_be_reordered_removed_and_cleared_without_deleting_shared_files(): void
    {
        [, $plan] = $this->fixture();
        $images = [
            ['id' => 'first', 'path' => 'training-plans/explanations/first.jpg', 'texts' => ['de' => ['caption' => 'Start']]],
            ['id' => 'second', 'path' => 'training-plans/explanations/second.jpg', 'texts' => []],
        ];
        foreach ($images as $image) {
            Storage::disk('public')->put($image['path'], 'image');
        }
        $item = $plan->items()->create(['title' => 'Original', 'original_locale' => 'de', 'explanation_images' => $images, 'translations' => ['fr' => ['title' => 'Traduction']]]);
        $copy = $item->replicate();
        $copy->save();
        $url = route('auth.training.plans.items.update', [$plan, $item]);
        $this->put($url, ['title' => 'Original', 'explanation_images' => [['id' => 'second'], ['id' => 'first']]])->assertSessionHasNoErrors();
        $this->assertSame('second', $item->fresh()->explanation_images[0]['id']);
        $this->put($url, ['title' => 'Original', 'explanation_images_empty' => '1'])->assertSessionHasNoErrors();
        $this->assertSame([], $item->fresh()->explanation_images);
        $this->assertSame('Traduction', $item->fresh()->translations['fr']['title']);
        Storage::disk('public')->assertExists($images[0]['path']);
        $copy->delete();
        Storage::disk('public')->assertMissing($images[0]['path']);
        Storage::disk('public')->assertMissing($images[1]['path']);
    }

    public function test_validation_rejects_foreign_image_ids_unsupported_languages_and_non_images(): void
    {
        [, $plan] = $this->fixture();
        $item = $plan->items()->create(['title' => 'Original']);
        $url = route('auth.training.plans.items.update', [$plan, $item]);
        $this->put($url, ['title' => 'Original', 'explanation_images' => [['id' => 'foreign']]])->assertSessionHasErrors('explanation_images.0.id');
        $this->put($url, ['title' => 'Original', 'original_locale' => 'es'])->assertSessionHasErrors('original_locale');
        $this->put($url, ['title' => 'Original', 'translations' => ['es' => ['title' => 'Hola']]])->assertSessionHasErrors('translations');
        $this->post(route('auth.training.plans.items.store', $plan), ['title' => 'Original', 'explanation_images' => [['file' => UploadedFile::fake()->create('script.txt', 1, 'text/plain')]]])->assertSessionHasErrors('explanation_images.0.file');
        $this->assertCount(1, $plan->items()->get());
    }

    public function test_schedule_only_update_preserves_explanation_texts_and_images(): void
    {
        [, $plan] = $this->fixture();
        $item = $plan->items()->create(['title' => 'Original', 'original_locale' => 'de', 'instructions' => 'Anleitung', 'translations' => ['fr' => ['title' => 'Traduction']], 'explanation_images' => [['id' => 'a', 'path' => 'a.jpg', 'texts' => []]]]);
        $this->put(route('auth.training.plans.items.update', [$plan, $item]), ['title' => 'Original', 'scheduled_at' => '2026-12-01T12:00'])->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertSame('Anleitung', $item->instructions);
        $this->assertSame('Traduction', $item->translations['fr']['title']);
        $this->assertSame('a', $item->explanation_images[0]['id']);
    }
}
