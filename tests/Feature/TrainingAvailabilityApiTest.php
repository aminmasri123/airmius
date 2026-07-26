<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingAvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_athlete_can_set_and_clear_a_private_training_status(): void
    {
        $athlete = User::factory()->create();
        Sanctum::actingAs($athlete);

        $created = $this->postJson('/api/v1/training/availability', [
            'status' => 'limited',
            'visibility' => 'private',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(3)->toDateString(),
            'note' => 'Heute nur Technik und leichte Belastung.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'limited')
            ->assertJsonPath('data.note', 'Heute nur Technik und leichte Belastung.')
            ->assertJsonPath('data.is_active', true);

        $statusId = $created->json('data.id');

        $this->getJson('/api/v1/training/availability')
            ->assertOk()
            ->assertJsonPath('data.current.id', $statusId)
            ->assertJsonPath('data.privacy.notes_visible', true);

        $this->deleteJson("/api/v1/training/availability/{$statusId}")
            ->assertOk()
            ->assertJsonPath('data.cleared', true);

        $this->getJson('/api/v1/training/availability')
            ->assertOk()
            ->assertJsonPath('data.current', null);
    }

    public function test_staff_can_read_shared_status_without_personal_note_and_outsider_is_blocked(): void
    {
        $owner = User::factory()->create();
        $coach = User::factory()->create();
        $athlete = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Airmius Club']);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);

        Sanctum::actingAs($athlete);
        $statusId = $this->postJson('/api/v1/training/availability', [
            'status' => 'injured',
            'visibility' => 'trainer',
            'starts_on' => now()->toDateString(),
            'note' => 'Persönlicher Hinweis mit sensiblen Details.',
        ])->json('data.id');

        Sanctum::actingAs($coach);
        $this->getJson("/api/v1/training/availability?user_id={$athlete->id}")
            ->assertOk()
            ->assertJsonPath('data.current.id', $statusId)
            ->assertJsonPath('data.current.status', 'injured')
            ->assertJsonPath('data.current.note', null)
            ->assertJsonPath('data.privacy.notes_visible', false);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/training/availability?user_id={$athlete->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('training_availability_statuses', [
            'id' => $statusId,
            'note' => 'Persönlicher Hinweis mit sensiblen Details.',
        ]);
    }

    public function test_team_visibility_is_shared_only_with_teammates(): void
    {
        $owner = User::factory()->create();
        $athlete = User::factory()->create();
        $teammate = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Airmius Club']);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);
        $team->users()->attach($teammate->id, ['role' => TeamRoles::PLAYER]);

        Sanctum::actingAs($athlete);
        $this->postJson('/api/v1/training/availability', [
            'status' => 'unavailable',
            'visibility' => 'team',
            'starts_on' => now()->toDateString(),
        ])->assertCreated();

        Sanctum::actingAs($teammate);
        $this->getJson("/api/v1/training/availability?user_id={$athlete->id}")
            ->assertOk()
            ->assertJsonPath('data.current.status', 'unavailable')
            ->assertJsonPath('data.current.note', null);
    }
}
