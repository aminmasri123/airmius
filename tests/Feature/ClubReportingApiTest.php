<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ContentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubReportingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_report_a_visible_club_to_moderation(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'is_listed' => true,
            'verification_status' => 'verified',
        ]);

        $this->actingAs($reporter)
            ->postJson('/api/v1/reports', [
                'type' => 'club',
                'id' => $club->id,
                'reason' => 'spam',
                'details' => 'Dieses Vereinsprofil enthält irreführende Werbung.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'club')
            ->assertJsonPath('data.reportable_id', $club->id);

        $this->assertDatabaseHas(ContentReport::class, [
            'reporter_id' => $reporter->id,
            'reportable_type' => Club::class,
            'reportable_id' => $club->id,
            'reason' => 'spam',
            'status' => 'open',
        ]);
    }

    public function test_owner_cannot_report_their_own_club(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson('/api/v1/reports', [
                'type' => 'club',
                'id' => $club->id,
                'reason' => 'other',
            ])
            ->assertUnprocessable();
    }
}
