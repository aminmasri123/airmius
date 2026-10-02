<?php

namespace Tests\Feature;

use App\Models\Sponsor;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SponsorProfileParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    private function profileInput(): array
    {
        return [
            'name' => 'Native Sponsor',
            'legal_name' => 'Native Sponsor GmbH',
            'country_code' => 'de',
            'registration_number' => 'HRB 123',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Private Contact',
            'email' => 'sponsor@example.test',
            'website' => 'https://example.test',
            'logo' => 'https://example.test/logo.png',
            'logo_light' => 'https://example.test/light.png',
            'logo_dark' => 'https://example.test/dark.png',
            'rule_legal_accuracy' => true,
            'rule_data_privacy' => true,
        ];
    }

    public function test_new_sponsor_can_create_and_round_trip_every_native_profile_field(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sponsor');
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/sponsor-workspace')
            ->assertOk()
            ->assertJsonPath('data.own_profile', null)
            ->assertJsonPath('data.capabilities.edit_own_profile', true);
        $this->putJson('/api/v1/sponsor-workspace/profile', $this->profileInput())->assertOk();
        $response = $this->getJson('/api/v1/sponsor-workspace')->assertOk();
        foreach ($this->profileInput() as $key => $value) {
            if (! str_starts_with($key, 'rule_')) {
                $response->assertJsonPath('data.own_profile.'.$key, $key === 'country_code' ? 'DE' : $value);
            }
        }
        $response->assertJsonPath('data.own_profile.legal_accuracy_accepted', true)
            ->assertJsonPath('data.own_profile.data_privacy_accepted', true)
            ->assertJsonPath('data.own_profile.verification_status', 'pending_review')
            ->assertJsonPath('data.own_profile.readiness.can_approve', true);
        $this->getJson('/api/v1/public/sponsors')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_owned_profiles_in_every_review_state_can_be_corrected_without_sponsor_role(): void
    {
        foreach (['pending_review', 'verified', 'rejected'] as $status) {
            $user = User::factory()->create();
            $user->givePermissionTo('sponsor.workspace.view');
            $profile = Sponsor::query()->create([
                'owner_user_id' => $user->id,
                'name' => 'Legacy sponsor',
                'verification_status' => $status,
                'accepted_rules' => [],
                'verified_by' => $status === 'verified' ? $user->id : null,
                'verified_at' => $status === 'verified' ? now() : null,
            ]);
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/sponsor-workspace')
                ->assertOk()
                ->assertJsonPath('data.capabilities.edit_own_profile', true)
                ->assertJsonPath('data.own_profile.legal_accuracy_accepted', false);
            $this->putJson('/api/v1/sponsor-workspace/profile', $this->profileInput())
                ->assertOk()->assertJsonPath('data.id', $profile->id);
            $profile->refresh();
            $this->assertSame('pending_review', $profile->verification_status);
            $this->assertNull($profile->verified_by);
            $this->assertNull($profile->verified_at);
            $this->assertSame(['legal_accuracy' => true, 'data_privacy' => true], $profile->accepted_rules);
            $this->assertSame(1, Sponsor::query()->where('owner_user_id', $user->id)->count());
        }
    }

    public function test_view_and_management_access_does_not_grant_self_service_creation(): void
    {
        foreach (['sponsor_manager', 'viewer'] as $role) {
            $user = User::factory()->create();
            if ($role === 'viewer') {
                $user->givePermissionTo('sponsor.workspace.view');
            } else {
                $user->assignRole($role);
            }
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/sponsor-workspace')
                ->assertOk()->assertJsonPath('data.capabilities.edit_own_profile', false);
            $this->putJson('/api/v1/sponsor-workspace/profile', $this->profileInput())->assertForbidden();
        }
        $this->assertDatabaseCount('sponsors', 0);
    }

    public function test_validation_rejects_invalid_fields_and_false_consents_without_changing_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sponsor');
        Sanctum::actingAs($user);
        $invalid = array_replace($this->profileInput(), [
            'name' => '',
            'legal_name' => str_repeat('x', 256),
            'country_code' => 'DEU',
            'registration_number' => str_repeat('x', 121),
            'vat_id' => str_repeat('x', 81),
            'email' => 'invalid',
            'website' => 'invalid',
            'logo' => 'invalid',
            'logo_light' => 'invalid',
            'logo_dark' => 'invalid',
            'rule_legal_accuracy' => false,
            'rule_data_privacy' => false,
        ]);
        $this->putJson('/api/v1/sponsor-workspace/profile', $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_diff(array_keys($invalid), ['contact_name']));
        $this->assertDatabaseCount('sponsors', 0);
    }

    public function test_self_service_cannot_assign_ownership_scope_or_approve_a_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sponsor');
        $foreign = User::factory()->create();
        $foreignProfile = Sponsor::query()->create([
            'owner_user_id' => $foreign->id, 'name' => 'Foreign sponsor',
        ]);
        Sanctum::actingAs($user);
        $response = $this->putJson('/api/v1/sponsor-workspace/profile', [
            ...$this->profileInput(),
            'id' => $foreignProfile->id,
            'owner_user_id' => $foreign->id,
            'scope' => 'club',
            'club_id' => 999,
            'verification_status' => 'verified',
            'verified_by' => $user->id,
            'accepted_rules' => ['bypass' => true],
        ])->assertOk();
        $profile = Sponsor::query()->findOrFail($response->json('data.id'));
        $this->assertSame($user->id, $profile->owner_user_id);
        $this->assertSame('platform', $profile->scope);
        $this->assertNull($profile->club_id);
        $this->assertNull($profile->verified_by);
        $this->assertSame('pending_review', $profile->verification_status);
        $this->assertArrayNotHasKey('bypass', $profile->accepted_rules);
        $this->assertSame('Foreign sponsor', $foreignProfile->fresh()->name);
    }
}
