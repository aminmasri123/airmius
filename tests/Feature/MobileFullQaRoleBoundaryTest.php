<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MobileFullQaRoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public static function personas(): array
    {
        return [
            'athlete' => ['player'],
            'coach' => ['coach'],
            'club owner' => ['club_owner'],
            'sponsor' => ['sponsor'],
        ];
    }

    #[DataProvider('personas')]
    public function test_personas_retain_personal_features_without_foreign_member_administration(string $role): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $actor = User::factory()->create(['name' => 'QA '.$role]);
        $actor->assignRole($role);
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'is_listed' => true,
            'verification_status' => 'verified',
        ]);
        $club->users()->attach($member, ['role' => 'member', 'membership_status' => 'active']);
        $initialMemberIds = $club->users()->orderBy('users.id')->pluck('users.id')->all();

        Sanctum::actingAs($actor);
        foreach (['/api/v1/challenges', '/api/v1/learning', '/api/v1/sports'] as $path) {
            $this->getJson($path)->assertOk();
        }

        $this->getJson("/api/v1/clubs/{$club->id}/members")->assertForbidden();
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role", ['role' => 'admin'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/clubs/{$club->id}/members/{$member->id}")->assertForbidden();
        $this->assertSame('member', $club->users()->findOrFail($member->id)->pivot->role);
        $this->assertSame($initialMemberIds, $club->users()->orderBy('users.id')->pluck('users.id')->all());

        $this->getJson('/api/v1/editorial/posts')->assertForbidden();
        $this->postJson('/api/v1/editorial/posts', ['title' => 'Unauthorized QA post'])->assertForbidden();
        $this->postJson('/api/v1/admin/platform/users', [])->assertForbidden();

        $workspace = $this->getJson('/api/v1/sponsor-workspace');
        if ($role === 'sponsor') {
            $workspace->assertOk();
            $this->getJson('/api/v1/trainer-cockpit')->assertForbidden();
        } else {
            $workspace->assertForbidden();
        }
    }
}
