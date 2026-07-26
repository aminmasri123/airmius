<?php

namespace Tests\Feature;

use App\Models\Sport;
use App\Models\SportSkill;
use App\Models\User;
use App\Models\UserSportSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileSportProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_update_and_remove_a_private_sport_profile(): void
    {
        $user = User::factory()->create();
        $sport = Sport::query()->create([
            'name' => 'Laufen',
            'slug' => 'running',
            'category' => 'endurance',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $skill = SportSkill::query()->create([
            'sport_id' => $sport->id,
            'key' => 'pace',
            'name' => 'Pace',
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/sport-profiles')
            ->assertOk()
            ->assertJsonPath('data.0.has_profile', false)
            ->assertJsonPath('data.0.sport.name', 'Laufen');

        $this->putJson("/api/v1/sport-profiles/{$sport->id}", [
            'status' => 'active',
            'experience_level' => 'intermediate',
            'visibility' => 'trainer',
            'metrics' => [
                'experience' => '2022-01-01',
                'weekly_km' => '35.5',
                'longest_run_km' => 15,
                'injuries' => '',
                'available_days' => 'Mo, Mi, Sa',
            ],
            'metric_visibility' => [
                'weekly_km' => 'trainer',
                'longest_run_km' => 'private',
            ],
            'unknown_metrics' => [
                'injuries' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.has_profile', true)
            ->assertJsonPath('data.visibility', 'trainer')
            ->assertJsonPath('data.metrics.weekly_km', 35.5)
            ->assertJsonPath('data.metrics._unknown_fields.0', 'injuries');

        $this->assertDatabaseHas('user_sports', [
            'user_id' => $user->id,
            'sport_id' => $sport->id,
            'experience_level' => 'intermediate',
            'visibility' => 'trainer',
        ]);
        $this->assertDatabaseHas('user_sport_skills', [
            'user_id' => $user->id,
            'sport_skill_id' => $skill->id,
        ]);

        $userSkill = UserSportSkill::query()
            ->where('user_id', $user->id)
            ->where('sport_skill_id', $skill->id)
            ->firstOrFail();

        $this->patchJson("/api/v1/sport-skills/{$userSkill->id}", [
            'self_level' => 'strong',
            'is_visible' => false,
            'notes' => 'Nur für mich.',
        ])
            ->assertOk()
            ->assertJsonPath('data.self_level', 'strong')
            ->assertJsonPath('data.is_visible', false);

        $this->deleteJson("/api/v1/sport-profiles/{$sport->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('user_sports', [
            'user_id' => $user->id,
            'sport_id' => $sport->id,
        ]);
        $this->assertDatabaseMissing('user_sport_skills', [
            'user_id' => $user->id,
            'sport_id' => $sport->id,
        ]);
    }

    public function test_user_cannot_change_another_users_skill(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $sport = Sport::query()->create([
            'name' => 'Tennis',
            'slug' => 'tennis',
            'category' => 'racket',
            'is_active' => true,
        ]);
        $skill = SportSkill::query()->create([
            'sport_id' => $sport->id,
            'key' => 'serve',
            'name' => 'Aufschlag',
        ]);
        $userSkill = UserSportSkill::query()->create([
            'user_id' => $owner->id,
            'sport_id' => $sport->id,
            'sport_skill_id' => $skill->id,
            'self_level' => 'solid',
            'is_visible' => true,
        ]);

        Sanctum::actingAs($attacker);

        $this->patchJson("/api/v1/sport-skills/{$userSkill->id}", [
            'self_level' => 'expert',
            'is_visible' => false,
        ])->assertForbidden();
    }
}
