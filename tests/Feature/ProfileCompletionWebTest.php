<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileCompletionWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_child_can_open_and_save_profile_then_reach_parental_consent(): void
    {
        Notification::fake();
        $this->seed(RolesPermissionsSeeder::class);
        $child = User::factory()->create([
            'first_name' => null,
            'last_name' => null,
            'birth_date' => '2016-01-01',
            'gender' => null,
            'guardian_email' => 'parent@example.test',
        ]);
        $child->assignRole('minor_pending_consent');
        $this->actingAs($child);

        $this->get('/profile-completion')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/CompleteProfile')
                ->where('user.birth_date', '2016-01-01'));
        $this->put('/profile-completion', $this->profileData())
            ->assertRedirect(route('guardian-consent.pending'));
        $this->get('/guardian-consent/pending')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/GuardianConsent/Pending')
                ->where('guardianEmail', 'parent@example.test'));

        $child->refresh();
        $this->assertTrue($child->hasRole('minor_pending_consent'));
        $this->assertNull($child->guardian_consent_at);
        $this->assertNotNull($child->guardian_consent_token);
        $this->get('/home')->assertRedirect(route('guardian-consent.pending'));
    }

    public function test_pending_child_cannot_change_birth_date_to_bypass_parental_consent(): void
    {
        Notification::fake();
        $this->seed(RolesPermissionsSeeder::class);
        $child = User::factory()->create(['birth_date' => '2016-01-01', 'guardian_email' => 'parent@example.test']);
        $child->assignRole('minor_pending_consent');

        $this->actingAs($child)->from('/profile-completion')
            ->put('/profile-completion', [...$this->profileData(), 'birth_date' => '2000-01-01'])
            ->assertRedirect('/profile-completion')->assertSessionHasErrors('birth_date');
        $this->assertSame('2016-01-01', $child->fresh()->birth_date->toDateString());
        $this->assertTrue($child->fresh()->hasRole('minor_pending_consent'));
    }

    public function test_minor_profile_requires_parent_email_with_field_error(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $user = User::factory()->create(['birth_date' => null]);

        $this->actingAs($user)->from('/profile-completion')
            ->put('/profile-completion', [...$this->profileData(), 'guardian_email' => ''])
            ->assertRedirect('/profile-completion')->assertSessionHasErrors('guardian_email');
        $this->assertNull($user->fresh()->birth_date);
    }

    public function test_adult_profile_completion_does_not_require_parental_consent(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $user = User::factory()->create(['first_name' => null, 'birth_date' => null]);

        $this->actingAs($user)->put('/profile-completion', [
            ...$this->profileData(), 'birth_date' => '2000-01-01', 'guardian_email' => '',
        ])->assertRedirect(route('auth.home'));
        $this->assertSame('Kind', $user->fresh()->first_name);
        $this->assertFalse($user->fresh()->hasRole('minor_pending_consent'));
    }

    private function profileData(): array
    {
        return [
            'first_name' => 'Kind', 'last_name' => 'Airmius', 'birth_date' => '2016-01-01',
            'gender' => 'male', 'country' => 'DE', 'account_type' => 'athlete',
            'guardian_email' => 'parent@example.test',
        ];
    }
}
