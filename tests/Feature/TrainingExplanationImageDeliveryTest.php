<?php

namespace Tests\Feature;

use App\Models\TrainingExercise;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingExplanationImageDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_image_is_readable_in_app_and_web_without_storage_url_or_symlink(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.public.url' => 'https://unreachable.invalid/storage']);
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $url = $this->post('/api/v1/training/exercises', [
            'scope' => 'personal', 'name' => 'Image delivery', 'difficulty' => 'all',
            'explanation_images' => [['file' => UploadedFile::fake()->image('position.png')]],
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.explanation_images.0.url');
        $exercise = TrainingExercise::sole();
        $image = $exercise->explanation_images[0];
        $bytes = Storage::disk('public')->get($image['path']);
        $this->assertStringNotContainsString('/storage/', $url);
        $this->assertStringContainsString('/api/v1/training/exercises/', $url);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertStreamedContent($bytes);
        $webUrl = route('auth.training.exercises.images.show', [$exercise, $image['id']]);
        $this->actingAs($owner)->get($webUrl)->assertOk()->assertStreamedContent($bytes);
        $this->get($webUrl)->assertHeader('Cache-Control', 'no-store, private');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertNotFound();
        $this->getJson($webUrl)->assertNotFound();
    }

    public function test_plan_image_is_private_until_publication_and_hidden_again_after_withdrawal(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $path = UploadedFile::fake()->image('explanation.jpg')->store('training-plans/explanations', 'public');
        $plan = TrainingPlan::create(['created_by' => $owner->id, 'title' => 'Private draft', 'cadence' => 'weekly', 'status' => 'draft']);
        $plan->assignments()->create(['user_id' => $recipient->id, 'permission' => 'read']);
        $item = $plan->items()->create(['title' => 'Exercise', 'explanation_images' => [['id' => 'position', 'path' => $path]]]);
        $apiUrl = route('api.v1.training.items.images.show', [$item, 'position']);
        $webUrl = route('auth.training.items.images.show', [$item, 'position']);
        Sanctum::actingAs($owner);
        $this->get($apiUrl)->assertOk();
        Sanctum::actingAs($recipient);
        $this->getJson($apiUrl)->assertNotFound();
        $this->actingAs($recipient)->getJson($webUrl)->assertNotFound();
        $plan->update(['status' => 'published']);
        $this->get($apiUrl)->assertOk()->assertStreamedContent(Storage::disk('public')->get($path));
        $this->get($webUrl)->assertOk();
        $plan->update(['status' => 'draft']);
        $this->getJson($apiUrl)->assertNotFound();
    }

    public function test_images_require_login_and_missing_images_have_a_clean_404(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $exercise = TrainingExercise::create(['created_by' => $owner->id, 'name' => 'Missing image', 'difficulty' => 'all', 'explanation_images' => [['id' => 'missing', 'path' => 'training-plans/explanations/missing.jpg']]]);
        $url = route('api.v1.training.exercises.images.show', [$exercise, 'missing']);
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs($owner);
        $this->getJson($url)->assertNotFound();
        $this->getJson(route('api.v1.training.exercises.images.show', [$exercise, 'unknown']))->assertNotFound();
        $exercise->update(['explanation_images' => [['id' => 'missing', 'path' => '../private-secret.jpg']]]);
        $this->getJson($url)->assertNotFound();
    }

    public function test_legacy_cover_image_is_served_from_the_same_authorized_endpoint(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $path = UploadedFile::fake()->image('cover.jpg')->store('training-plans', 'public');
        $plan = TrainingPlan::create(['created_by' => $owner->id, 'title' => 'Cover plan', 'cadence' => 'weekly', 'status' => 'draft']);
        $item = $plan->items()->create(['title' => 'Legacy cover', 'image_path' => $path]);
        Sanctum::actingAs($owner);
        $url = $this->getJson('/api/v1/training/plans/'.$plan->id)->assertOk()->json('data.items.0.image_url');
        $this->assertStringContainsString('/images/cover', $url);
        $this->get($url)->assertOk()->assertStreamedContent(Storage::disk('public')->get($path));
    }
}
