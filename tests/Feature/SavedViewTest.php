<?php

namespace Tests\Feature;

use App\Http\Controllers\SavedViewController;
use App\Models\SavedView;
use App\Models\User;
use App\Services\UserDataErasureService;
use App\Services\UserPrivacyExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_their_saved_search_views_through_web_and_api_routes(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user)->postJson(route('auth.saved-views.store'), [
            'workspace' => 'global_search',
            'name' => 'Offene Rechnungen',
            'configuration' => [
                'query' => 'Beitrag',
                'types' => ['invoice'],
            ],
            'is_favorite' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Offene Rechnungen')
            ->assertJsonPath('data.configuration.types.0', 'invoice')
            ->assertJsonPath('data.is_favorite', true);

        $viewId = $created->json('data.id');

        $this->getJson(route('auth.saved-views.index', ['workspace' => 'global_search']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $viewId);

        $this->putJson("/api/v1/saved-views/{$viewId}", [
            'workspace' => 'global_search',
            'name' => 'Meine Rechnungen',
            'configuration' => ['query' => 'AIR-', 'types' => ['invoice']],
            'is_favorite' => false,
            'sort_order' => 2,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Meine Rechnungen')
            ->assertJsonPath('data.is_favorite', false)
            ->assertJsonPath('data.sort_order', 2);

        $this->deleteJson("/api/v1/saved-views/{$viewId}")->assertNoContent();
        $this->assertDatabaseMissing('saved_views', ['id' => $viewId]);
    }

    public function test_saved_views_are_private_to_their_owner(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $view = SavedView::query()->create([
            'user_id' => $owner->id,
            'workspace' => 'global_search',
            'name' => 'Private Ansicht',
            'configuration' => ['query' => 'intern'],
        ]);

        $this->actingAs($outsider)
            ->getJson('/api/v1/saved-views?workspace=global_search')
            ->assertOk()
            ->assertJsonPath('data', []);

        $payload = [
            'workspace' => 'global_search',
            'name' => 'Geändert',
            'configuration' => ['query' => 'fremd'],
        ];
        $this->putJson("/api/v1/saved-views/{$view->id}", $payload)->assertNotFound();
        $this->deleteJson("/api/v1/saved-views/{$view->id}")->assertNotFound();

        $this->assertDatabaseHas('saved_views', [
            'id' => $view->id,
            'name' => 'Private Ansicht',
        ]);
    }

    public function test_saved_view_payload_and_workspace_are_strictly_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/saved-views', [
            'workspace' => 'unknown',
            'name' => 'Ungültig',
            'configuration' => ['types' => ['passwords']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['workspace', 'configuration.types.0']);

        $valid = [
            'workspace' => 'global_search',
            'name' => 'Doppelt',
            'configuration' => ['query' => 'Termin', 'types' => ['event']],
        ];
        $this->postJson('/api/v1/saved-views', $valid)->assertCreated();
        $this->postJson('/api/v1/saved-views', $valid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_saved_views_are_limited_per_user_and_workspace(): void
    {
        $user = User::factory()->create();
        foreach (range(1, SavedViewController::MAX_PER_WORKSPACE) as $position) {
            SavedView::query()->create([
                'user_id' => $user->id,
                'workspace' => 'global_search',
                'name' => "Ansicht {$position}",
                'configuration' => ['query' => "Suche {$position}"],
            ]);
        }

        $this->actingAs($user)->postJson('/api/v1/saved-views', [
            'workspace' => 'global_search',
            'name' => 'Eine zu viel',
            'configuration' => ['query' => 'Limit'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson('/api/v1/saved-views', [
            'workspace' => 'events',
            'name' => 'Terminansicht',
            'configuration' => ['filters' => ['status' => 'scheduled']],
        ])->assertCreated();
    }

    public function test_each_central_workspace_accepts_its_personal_filter_configuration(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['members', 'events', 'invoices', 'files'] as $workspace) {
            $this->postJson('/api/v1/saved-views', [
                'workspace' => $workspace,
                'name' => "{$workspace} Ansicht",
                'configuration' => [
                    'query' => 'Vereinsarbeit',
                    'filters' => ['club_id' => 7, 'status' => 'open'],
                    'sort' => 'newest',
                ],
            ])->assertCreated()
                ->assertJsonPath('data.workspace', $workspace)
                ->assertJsonPath('data.configuration.filters.club_id', 7);
        }

        $this->assertDatabaseCount('saved_views', 4);
    }

    public function test_saved_views_are_included_in_export_and_removed_with_profile_data(): void
    {
        $user = User::factory()->create();
        $view = SavedView::query()->create([
            'user_id' => $user->id,
            'workspace' => 'files',
            'name' => 'Meine Dokumente',
            'configuration' => ['filters' => ['type' => 'pdf']],
        ]);

        $export = app(UserPrivacyExportService::class)->export($user);
        $this->assertSame($view->id, $export['saved_views'][0]['id']);
        $this->assertSame('pdf', $export['saved_views'][0]['configuration']['filters']['type']);

        app(UserDataErasureService::class)->erase($user, ['profile']);
        $this->assertDatabaseMissing('saved_views', ['id' => $view->id]);
    }
}
