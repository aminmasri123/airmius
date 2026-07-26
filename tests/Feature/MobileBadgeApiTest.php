<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileBadgeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_open_only_their_real_badge_awards(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $badge = Badge::query()->create([
            'key' => 'first-training',
            'name' => 'Trainingsstart',
            'description' => 'Erstes Training erfolgreich dokumentiert.',
            'icon' => 'las la-medal',
            'actor_type' => 'sportler',
            'trigger' => 'training_logged',
            'threshold' => 1,
        ]);
        $award = UserBadge::query()->create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'reason' => 'Erstes Training',
            'meta' => ['xp' => 25, 'level' => 2],
        ]);
        $otherAward = UserBadge::query()->create([
            'user_id' => $otherUser->id,
            'badge_id' => $badge->id,
            'reason' => 'Fremde Auszeichnung',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/badges')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $award->id)
            ->assertJsonPath('data.0.badge.name', 'Trainingsstart')
            ->assertJsonPath('data.0.reason', 'Erstes Training')
            ->assertJsonPath('data.0.meta.xp', 25)
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/badges/{$award->id}")
            ->assertOk()
            ->assertJsonPath('data.badge.trigger', 'training_logged')
            ->assertJsonPath('data.meta.level', 2);

        $this->getJson("/api/v1/badges/{$otherAward->id}")
            ->assertForbidden();
    }
}
