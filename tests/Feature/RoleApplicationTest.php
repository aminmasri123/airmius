<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRoleApplication;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_athlete_gets_immediate_trainer_access_while_application_is_pending(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $athlete = User::factory()->create();
        $athlete->assignRole('player');

        $response = $this->actingAs($athlete)->post(route('auth.role-applications.store'), [
            'type' => 'trainer',
            'message' => 'Ich trainiere seit mehreren Jahren.',
        ]);

        $response->assertRedirect();
        $athlete->refresh();

        $this->assertTrue($athlete->hasRole('coach'));
        $this->assertDatabaseHas('user_role_applications', [
            'user_id' => $athlete->id,
            'type' => UserRoleApplication::TYPE_TRAINER,
            'status' => UserRoleApplication::STATUS_PENDING,
            'role_activated' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $athlete->id,
            'type' => 'role.application_submitted',
        ]);
    }

    public function test_mobile_athlete_can_submit_the_same_trainer_application(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $athlete = User::factory()->create();
        $athlete->assignRole('player');
        Sanctum::actingAs($athlete);

        $response = $this->postJson('/api/v1/role-applications', [
            'type' => 'trainer',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.application.type', 'trainer')
            ->assertJsonPath('data.application.status', 'pending');

        $this->assertTrue($athlete->fresh()->hasRole('coach'));
    }

    public function test_athlete_can_register_a_club_and_is_activated_as_owner_immediately(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $athlete = User::factory()->create();
        $athlete->assignRole('player');

        $response = $this->actingAs($athlete)->post(route('auth.clubs.store'), [
            'name' => 'Airmius Testverein',
            'sport_type' => 'fussball',
            'country' => 'DE',
        ]);

        $response->assertRedirect();
        $this->assertTrue($athlete->fresh()->hasRole('club_owner'));
        $this->assertDatabaseHas('clubs', [
            'name' => 'Airmius Testverein',
            'owner_id' => $athlete->id,
            'verification_status' => 'pending_verification',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $athlete->id,
            'type' => 'club.registration_submitted',
        ]);
    }

    public function test_rejecting_an_application_removes_only_the_role_activated_by_that_application(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->forceFill([
            'two_factor_secret' => 'encrypted-test-secret',
            'two_factor_confirmed_at' => now(),
        ])->save();
        $athlete = User::factory()->create();
        $athlete->assignRole('player');

        $this->actingAs($athlete)->post(route('auth.role-applications.store'), [
            'type' => 'trainer',
        ]);
        $application = UserRoleApplication::query()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.trainer-applications.reject', $application), [
                'review_notes' => 'Bitte reiche noch einen Nachweis ein.',
            ])
            ->assertRedirect();

        $this->assertSame(UserRoleApplication::STATUS_REJECTED, $application->fresh()->status);
        $this->assertFalse($athlete->fresh()->hasRole('coach'));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $athlete->id,
            'type' => 'role.application_status_updated',
        ]);
    }
}
