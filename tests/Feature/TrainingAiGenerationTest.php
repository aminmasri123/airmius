<?php

namespace Tests\Feature;

use App\Jobs\GenerateTrainingAiPreview;
use App\Models\TrainingAiGeneration;
use App\Models\User;
use App\Services\Training\AthleteSportProfileService;
use App\Services\Training\TrainingAiPreviewGenerator;
use App\Services\Training\TrainingResourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingAiGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(TrainingResourceService::class, fn ($mock) => $mock->shouldReceive('canManageTrainingPlans')->andReturn(true));
    }

    public function test_creation_is_queued_and_duplicate_clicks_reuse_one_job(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());
        $this->mock(AthleteSportProfileService::class, fn ($mock) => $mock->shouldReceive('readiness')->andReturn([
            'ready' => true, 'profile' => [], 'sport' => ['name' => 'Laufen'],
        ]));
        $payload = ['goal' => 'Locker laufen', 'sport_type' => 'laufen', 'level' => 'beginner', 'phase' => 'base', 'weeks' => 1, 'sessions_per_week' => 1, 'async' => true];
        $first = $this->postJson('/api/v1/training/ai/plans/preview', $payload)->assertStatus(202)->json('id');
        $this->postJson('/api/v1/training/ai/plans/preview', $payload)->assertStatus(202)->assertJsonPath('id', $first);
        Queue::assertPushed(GenerateTrainingAiPreview::class, 1);
        $this->assertDatabaseCount('training_ai_generations', 1);
    }

    public function test_completed_preview_is_recoverable_only_by_its_owner(): void
    {
        $user = User::factory()->create();
        $generation = TrainingAiGeneration::create(['user_id' => $user->id, 'context' => [], 'status' => 'completed', 'result' => ['title' => 'Mein Plan']]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/training/ai/generations/latest')->assertOk()->assertJsonPath('plan.title', 'Mein Plan')->assertJsonStructure(['plan' => ['safety_token']]);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/training/ai/generations/'.$generation->id)->assertNotFound();
        $this->getJson('/api/v1/training/ai/generations/latest')->assertJsonPath('status', 'none');
    }

    public function test_worker_runs_once_and_preserves_result_after_page_is_closed(): void
    {
        $generation = TrainingAiGeneration::create(['user_id' => User::factory()->create()->id, 'context' => ['data' => [], 'readiness' => []]]);
        $generator = $this->mock(TrainingAiPreviewGenerator::class, fn ($mock) => $mock->shouldReceive('generate')->once()->andReturn(['title' => 'Fertig']));
        $job = new GenerateTrainingAiPreview($generation->id);
        $job->handle($generator);
        $job->handle($generator);
        $this->assertSame('completed', $generation->fresh()->status);
        $this->assertSame('Fertig', $generation->fresh()->result['title']);
    }

    public function test_stuck_worker_has_a_recoverable_failure_instead_of_endless_waiting(): void
    {
        $user = User::factory()->create();
        $generation = TrainingAiGeneration::create(['user_id' => $user->id, 'context' => [], 'status' => 'running']);
        $this->travel(16)->minutes();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/training/ai/generations/'.$generation->id)->assertOk()->assertJsonPath('status', 'failed');
    }

    public function test_unstarted_job_expires_after_three_minutes_and_cannot_run_late(): void
    {
        $user = User::factory()->create();
        $generation = TrainingAiGeneration::create(['user_id' => $user->id, 'context' => [], 'status' => 'queued']);
        Sanctum::actingAs($user);
        $this->travel(2)->minutes();
        $this->getJson('/api/v1/training/ai/generations/'.$generation->id)->assertJsonPath('status', 'queued');
        $this->travel(2)->minutes();
        $this->getJson('/api/v1/training/ai/generations/'.$generation->id)
            ->assertOk()->assertJsonPath('status', 'failed')
            ->assertJsonPath('plan', null);
        $generator = $this->mock(TrainingAiPreviewGenerator::class, fn ($mock) => $mock->shouldNotReceive('generate'));
        (new GenerateTrainingAiPreview($generation->id))->handle($generator);
        $this->assertSame('failed', $generation->fresh()->status);
    }

    public function test_provider_failure_is_safe_and_is_not_retried(): void
    {
        $generation = TrainingAiGeneration::create(['user_id' => User::factory()->create()->id, 'context' => ['data' => [], 'readiness' => []]]);
        $generator = $this->mock(TrainingAiPreviewGenerator::class, fn ($mock) => $mock->shouldReceive('generate')->once()->andThrow(new \RuntimeException('private provider diagnostics')));
        $job = new GenerateTrainingAiPreview($generation->id);
        $job->handle($generator);
        $job->handle($generator);
        $this->assertSame('failed', $generation->fresh()->status);
        $this->assertStringNotContainsString('private', $generation->fresh()->error);
    }

    public function test_expired_queued_job_does_not_generate_after_a_new_request(): void
    {
        $generation = TrainingAiGeneration::create(['user_id' => User::factory()->create()->id, 'context' => []]);
        $this->travel(16)->minutes();
        $generator = $this->mock(TrainingAiPreviewGenerator::class, fn ($mock) => $mock->shouldNotReceive('generate'));
        (new GenerateTrainingAiPreview($generation->id))->handle($generator);
        $this->assertNull($generation->fresh()->result);
    }
}
