<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingLogApiCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_user_can_create_update_and_delete_own_training_log(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/training/logs', [
            'title' => 'Lockerer Dauerlauf',
            'sport_type' => 'laufen',
            'status' => 'completed',
            'performed_at' => now()->toIso8601String(),
            'duration_minutes' => 42,
            'distance_km' => 6.2,
            'calories' => 430,
            'intensity' => 'locker',
            'privacy_scope' => 'trainer',
            'wellness' => [
                'rpe' => 5,
                'energy' => 7,
                'pain' => 0,
                'sleep_hours' => 7.5,
            ],
            'notes' => 'Gute, ruhige Einheit.',
            'entries' => [[
                'title' => 'Dauerlauf',
                'duration_minutes' => 42,
                'distance_km' => 6.2,
                'intensity' => 'locker',
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Lockerer Dauerlauf')
            ->assertJsonPath('data.distance_meters', 6200)
            ->assertJsonPath('data.entries.0.duration_seconds', 2520)
            ->assertJsonPath('data.metrics.privacy_scope', 'trainer')
            ->assertJsonPath('data.metrics.wellness.rpe', 5);

        $logId = $created->json('data.id');

        $this->putJson("/api/v1/training/logs/{$logId}", [
            'title' => 'Dauerlauf aktualisiert',
            'sport_type' => 'laufen',
            'status' => 'completed',
            'duration_minutes' => 45,
            'distance_km' => 6.5,
            'intensity' => 'mittel',
            'privacy_scope' => 'private',
            'notes' => 'Etwas schneller beendet.',
            'entries' => [[
                'title' => 'Progressiver Lauf',
                'duration_minutes' => 45,
                'distance_km' => 6.5,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Dauerlauf aktualisiert')
            ->assertJsonPath('data.distance_meters', 6500)
            ->assertJsonPath('data.metrics.privacy_scope', 'private');

        $this->deleteJson("/api/v1/training/logs/{$logId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('training_logs', ['id' => $logId]);
    }

    public function test_mobile_user_cannot_change_another_users_training_log(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $log = $owner->trainingLogs()->create([
            'created_by' => $owner->id,
            'title' => 'Privater Log',
            'status' => 'completed',
            'performed_at' => now(),
        ]);

        Sanctum::actingAs($other);

        $this->putJson("/api/v1/training/logs/{$log->id}", [
            'title' => 'Nicht erlaubt',
            'status' => 'completed',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/training/logs/{$log->id}")
            ->assertForbidden();
    }
}
