<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaPayloadBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_teams_index_does_not_ship_unused_global_user_payloads(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);
        $club = Club::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Payload Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Payload Team',
        ]);

        $club->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'roles' => ['owner']],
        ]);
        $team->users()->attach($user->id, ['role' => TeamRoles::PLAYER]);

        foreach (range(1, 220) as $index) {
            User::factory()->create([
                'name' => "Payload Outsider {$index}",
                'email' => "payload-outsider-{$index}@example.com",
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->get(route('auth.teams.index'));

        $response->assertOk()
            ->assertDontSee('payload-outsider-1@example.com', false)
            ->assertDontSee('payload-outsider-200@example.com', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Teams/Index')
                ->missing('availableUsers')
                ->missing('clubs.0.admins')
                ->missing('clubs.0.teams.0.invitations')
                ->has('clubs.0.users', 1)
                ->has('clubs.0.teams.0.users', 1));

        $page = $response->viewData('page');

        $this->assertLessThan(35000, strlen(json_encode($page['props'], JSON_THROW_ON_ERROR)));
    }
}
