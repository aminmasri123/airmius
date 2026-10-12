<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventAttendanceFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_requires_verified_attendance_before_finalization_and_stats_use_actual_presence(): void
    {
        $owner = User::factory()->create();
        $present = User::factory()->create(['name' => 'Anwesend']);
        $excused = User::factory()->create(['name' => 'Entschuldigt']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $team->users()->attach([
            $owner->id => ['role' => TeamRoles::COACH],
            $present->id => ['role' => TeamRoles::PLAYER],
            $excused->id => ['role' => TeamRoles::PLAYER],
        ]);

        $event = Event::create([
            'user_id' => $owner->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'title' => 'Abgeschlossenes Training',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->subHour(),
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/events/{$event->id}/attendance", [
            'attendance' => [
                ['user_id' => $owner->id, 'attendance_status' => 'present'],
                ['user_id' => $present->id, 'attendance_status' => 'present'],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/events/{$event->id}/attendance/finalize")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attendance');

        $this->putJson("/api/v1/events/{$event->id}/attendance", [
            'attendance' => [
                ['user_id' => $excused->id, 'attendance_status' => 'excused'],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/events/{$event->id}/attendance/finalize")
            ->assertOk()
            ->assertJsonPath('data.completed_at', fn ($value) => filled($value));

        $this->assertNotNull($event->fresh()->completed_at);
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $present->id,
            'attendance_status' => 'present',
        ]);

        $response = $this->getJson("/api/v1/teams/{$team->id}/attendance-stats")
            ->assertOk()
            ->assertJsonPath('data.trainings_total', 1);

        $members = collect($response->json('data.members'))->keyBy('name');
        $this->assertSame(100, $members['Anwesend']['attendance_rate']);
        $this->assertSame(1, $members['Entschuldigt']['excused']);
        $this->assertSame(0, $members['Entschuldigt']['attendance_rate']);
    }
}
