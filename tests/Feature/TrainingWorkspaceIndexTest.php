<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrainingWorkspaceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_workspace_renders_all_initial_sections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.training.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Training/Index')
                ->has('plans')
                ->has('logs')
                ->has('activities')
                ->has('teams')
                ->has('sportRoutes')
                ->has('sportRouteTracks')
                ->has('aiCapabilities'));
    }

    public function test_ai_plan_partial_refresh_returns_only_requested_workspace_data(): void
    {
        $user = User::factory()->create();
        $assetVersion = app(HandleInertiaRequests::class)->version(request());

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'Auth/Dashboard/Training/Index',
                'X-Inertia-Partial-Data' => 'plans,aiCapabilities',
                'X-Inertia-Version' => $assetVersion,
            ])
            ->get(route('auth.training.index'))
            ->assertOk()
            ->assertJsonPath('component', 'Auth/Dashboard/Training/Index')
            ->assertJsonStructure(['props' => ['plans', 'aiCapabilities']])
            ->assertJsonMissingPath('props.logs')
            ->assertJsonMissingPath('props.activities')
            ->assertJsonMissingPath('props.teams')
            ->assertJsonMissingPath('props.sportRoutes')
            ->assertJsonMissingPath('props.sportRouteTracks');
    }

    public function test_completed_training_logs_link_to_their_detail_page(): void
    {
        $workspace = file_get_contents(base_path('resources/js/Pages/Auth/Dashboard/Training/Index.vue'));

        $this->assertStringContainsString(
            ":href=\"route('auth.training.logs.show', log.id)\"",
            $workspace
        );
        $this->assertStringContainsString("wc('actions.details')", $workspace);
    }
}
