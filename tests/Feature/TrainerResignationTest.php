<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Team;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class TrainerResignationTest extends TestCase
{
    use RefreshDatabase;
    public function test_team_resignation_is_scoped_confirmed_and_notified(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('coach');
        $team = Team::factory()->create(['club_id' => \App\Models\Club::factory()->create(['owner_id' => User::factory()->create()->id])->id]);
        $other = Team::factory()->create(['club_id' => \App\Models\Club::factory()->create(['owner_id' => User::factory()->create()->id])->id]);
        $team->users()->attach($user, ['role' => 'Coach']);
        $other->users()->attach($user, ['role' => 'Coach']);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/trainer-resignation', ['team_id' => $team->id])->assertUnprocessable();
        $this->postJson('/api/v1/trainer-resignation', ['confirmed' => true])->assertUnprocessable();
        $this->postJson('/api/v1/trainer-resignation', ['confirmed' => true, 'team_id' => $team->id])->assertOk();
        $this->assertDatabaseHas('notifications', ['user_id' => $team->club->owner_id, 'type' => 'role.trainer_resigned']);
        $this->assertSame('Player', $user->teams()->find($team->id)->pivot->role);
        $this->assertSame('Coach', $user->teams()->find($other->id)->pivot->role);
        $this->assertTrue($user->fresh()->hasRole('coach'));
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'role.trainer_resigned']);
        $this->postJson('/api/v1/trainer-resignation', ['confirmed' => true, 'team_id' => $team->id])->assertForbidden();
    }
    public function test_web_confirmation_and_resignation(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('coach');
        $this->actingAs($user)->post('/trainer-resignation', [])->assertSessionHasErrors('confirmed');
        $this->actingAs($user)->post('/trainer-resignation', ['confirmed' => true])->assertRedirect();
        $this->assertFalse($user->fresh()->hasRole('coach'));
    }
    public function test_global_resignation_preserves_other_roles_and_withdraws_application(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(['coach', 'player']);
        $application = $user->roleApplications()->create(['type' => 'trainer', 'status' => 'pending', 'role_activated' => true]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/trainer-resignation', ['confirmed' => true])->assertOk();
        $this->assertFalse($user->fresh()->hasRole('coach'));
        $this->assertTrue($user->fresh()->hasRole('player'));
        $this->assertSame('withdrawn', $application->fresh()->status);
    }
}
