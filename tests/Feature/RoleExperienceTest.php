<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Sponsor;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_personas_are_sent_to_their_useful_home(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        foreach ([
            'player' => route('auth.feed.index'),
            'coach' => route('auth.trainer-cockpit.index'),
            'club_owner' => route('auth.club-cockpit.index'),
            'sponsor' => route('auth.sponsor-workspace.index'),
            'admin' => route('auth.dashboard'),
        ] as $role => $destination) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)
                ->get(route('auth.home'))
                ->assertRedirect($destination);
        }
    }

    public function test_multi_persona_user_is_sent_to_workspace_selection(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['coach', 'sponsor']);

        $this->actingAs($user)
            ->get(route('auth.home'))
            ->assertRedirect(route('auth.workspaces.index'));
    }

    public function test_sponsor_workspace_is_owner_scoped_and_profile_is_self_service(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $sponsorUser = User::factory()->create();
        $foreignUser = User::factory()->create();
        $sponsorUser->assignRole('sponsor');

        $ownSponsor = Sponsor::query()->create([
            'owner_user_id' => $sponsorUser->id,
            'scope' => 'platform',
            'name' => 'Eigene Marke',
            'email' => $sponsorUser->email,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        Sponsor::query()->create([
            'owner_user_id' => $foreignUser->id,
            'scope' => 'platform',
            'name' => 'Fremde Marke',
            'email' => $foreignUser->email,
        ]);
        AdCampaign::query()->create([
            'user_id' => $sponsorUser->id,
            'sponsor_id' => $ownSponsor->id,
            'name' => 'Eigene Kampagne',
            'status' => 'active',
            'budget_cents' => 50000,
            'spent_cents' => 10000,
            'impressions' => 1000,
            'clicks' => 50,
        ]);
        AdCampaign::query()->create([
            'user_id' => $foreignUser->id,
            'name' => 'Fremde Kampagne',
            'status' => 'active',
        ]);

        $this->actingAs($sponsorUser)
            ->getJson(route('api.v1.sponsor-workspace.index'))
            ->assertOk()
            ->assertJsonPath('data.summary.partners', 1)
            ->assertJsonPath('data.summary.campaigns', 1)
            ->assertJsonPath('data.summary.ctr', 5)
            ->assertJsonPath('data.partners.0.name', 'Eigene Marke')
            ->assertJsonPath('data.campaigns.0.name', 'Eigene Kampagne');

        $this->actingAs($sponsorUser)
            ->putJson(route('api.v1.sponsor-workspace.profile.update'), [
                'name' => 'Neue Marke',
                'contact_name' => 'Kontakt Person',
                'email' => 'kontakt@example.com',
                'website' => 'https://example.com',
            ])
            ->assertOk();

        $this->assertDatabaseHas('sponsors', [
            'id' => $ownSponsor->id,
            'owner_user_id' => $sponsorUser->id,
            'name' => 'Neue Marke',
            'email' => 'kontakt@example.com',
        ]);
    }

    public function test_regular_athlete_cannot_open_sponsor_workspace(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $athlete = User::factory()->create();
        $athlete->assignRole('player');

        $this->actingAs($athlete)
            ->getJson(route('api.v1.sponsor-workspace.index'))
            ->assertForbidden();
    }
}
