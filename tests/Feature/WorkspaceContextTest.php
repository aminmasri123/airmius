<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_select_and_clear_a_club_workspace(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $club->users()->attach($user->id, [
            'role' => 'member',
            'roles' => ['member'],
        ]);

        $this->actingAs($user)
            ->from(route('auth.workspaces.index'))
            ->post(route('auth.workspaces.club.select', $club))
            ->assertRedirect(route('auth.workspaces.index'))
            ->assertSessionHas('club_id', $club->id);

        $this->actingAs($user)
            ->withSession(['club_id' => $club->id])
            ->get(route('auth.workspaces.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspaceContext.current.id', $club->id)
                ->where('workspaceContext.current.name', $club->name)
                ->has('workspaceContext.clubs', 1));

        $this->actingAs($user)
            ->withSession(['club_id' => $club->id])
            ->from(route('auth.workspaces.index'))
            ->delete(route('auth.workspaces.club.clear'))
            ->assertRedirect(route('auth.workspaces.index'))
            ->assertSessionMissing('club_id');
    }

    public function test_user_cannot_select_an_unrelated_club_workspace(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs($user)
            ->from(route('auth.workspaces.index'))
            ->post(route('auth.workspaces.club.select', $club))
            ->assertRedirect(route('auth.workspaces.index'))
            ->assertSessionMissing('club_id');
    }

    public function test_stale_workspace_is_removed_when_membership_access_disappears(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs($user)
            ->withSession(['club_id' => $club->id])
            ->get(route('auth.workspaces.index'))
            ->assertOk()
            ->assertSessionMissing('club_id')
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspaceContext.current', null)
                ->has('workspaceContext.clubs', 0));
    }
}
