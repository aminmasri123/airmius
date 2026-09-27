<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubTask;
use App\Models\Event;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClubWorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Club $own;

    private Club $foreign;

    private Team $ownTeam;

    private Team $foreignTeam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole('club_manager');
        $this->own = Club::factory()->create([
            'owner_id' => User::factory()->create()->id,
            'is_listed' => false,
        ]);
        $this->foreign = Club::factory()->create([
            'owner_id' => User::factory()->create()->id,
            'verification_status' => 'verified',
            'is_listed' => true,
        ]);
        $this->own->users()->attach($this->manager->id, [
            'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
        ]);
        $this->ownTeam = Team::factory()->create(['club_id' => $this->own->id]);
        $this->foreignTeam = Team::factory()->create(['club_id' => $this->foreign->id]);
    }

    public static function clubRoles(): array
    {
        return [
            'owner' => ['club_owner'],
            'club admin' => ['club_admin'],
            'manager' => ['club_manager'],
        ];
    }

    #[DataProvider('clubRoles')]
    public function test_seeded_view_permissions_never_grant_cross_club_api_access(string $role): void
    {
        $this->manager->syncRoles([$role]);
        $this->assertTrue($this->manager->can('teams.view'));
        Sanctum::actingAs($this->manager);

        foreach (['', '?mine=0', '?q='.$this->foreign->name] as $query) {
            $response = $this->getJson('/api/v1/clubs'.$query)->assertOk();
            $this->assertNotContains($this->foreign->id, array_column($response->json('data'), 'id'));
        }
        $this->getJson('/api/v1/clubs')->assertJsonPath('data.0.id', $this->own->id);
        $this->getJson('/api/v1/teams')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->ownTeam->id);
        $this->getJson('/api/v1/teams?club_id='.$this->foreign->id)->assertForbidden();
        $this->getJson('/api/v1/teams/'.$this->foreignTeam->id)->assertNotFound();
        $this->getJson('/api/v1/clubs/'.$this->foreign->id)->assertNotFound();
        $this->getJson('/api/v1/teams/'.$this->ownTeam->id)->assertOk();
        $this->getJson('/api/v1/clubs/'.$this->own->id)->assertOk();
    }

    public function test_members_finance_and_tasks_reject_foreign_club_ids_and_records(): void
    {
        Sanctum::actingAs($this->manager);
        foreach (['members', 'billing', 'tasks'] as $area) {
            $this->getJson('/api/v1/clubs/'.$this->own->id.'/'.$area)->assertOk();
            $response = $this->getJson('/api/v1/clubs/'.$this->foreign->id.'/'.$area);
            $this->assertContains($response->status(), [403, 404], $area);
        }
        $task = ClubTask::create([
            'club_id' => $this->foreign->id,
            'created_by' => $this->foreign->owner_id,
            'title' => 'Foreign confidential task',
        ]);
        $this->putJson('/api/v1/clubs/'.$this->own->id.'/tasks/'.$task->id, ['completed' => true])->assertNotFound();
        $this->postJson('/api/v1/clubs/'.$this->foreign->id.'/tasks', ['title' => 'Forbidden'])->assertForbidden();
        $this->postJson('/api/v1/clubs/'.$this->foreign->id.'/finance-entries', [])->assertNotFound();
        $this->assertDatabaseHas('club_tasks', ['id' => $task->id, 'completed_at' => null]);
    }

    public function test_calendar_and_task_metadata_only_include_own_club_events(): void
    {
        $ownEvent = $this->event($this->own);
        $foreignEvent = $this->event($this->foreign);
        Sanctum::actingAs($this->manager);
        $this->getJson('/api/v1/events')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownEvent->id);
        $this->getJson('/api/v1/events?club_id='.$this->foreign->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/events/'.$foreignEvent->id)->assertNotFound();
        $this->getJson('/api/v1/clubs/'.$this->own->id.'/tasks')->assertOk()
            ->assertJsonCount(1, 'meta.calendar_events')
            ->assertJsonPath('meta.calendar_events.0.id', $ownEvent->id);
    }

    public function test_task_creator_cannot_delete_tasks_after_losing_access_to_the_club(): void
    {
        $task = ClubTask::create([
            'club_id' => $this->own->id,
            'created_by' => $this->manager->id,
            'title' => 'Former member task',
        ]);
        $this->own->users()->detach($this->manager->id);
        Sanctum::actingAs($this->manager);
        $this->deleteJson('/api/v1/clubs/'.$this->own->id.'/tasks/'.$task->id)->assertForbidden();
        $this->assertDatabaseHas('club_tasks', ['id' => $task->id]);
    }

    public function test_web_lists_and_direct_links_enforce_the_same_club_boundary(): void
    {
        $ownEvent = $this->event($this->own);
        $foreignEvent = $this->event($this->foreign);
        $this->actingAs($this->manager);
        foreach (['auth.teams.index', 'auth.club-cockpit.index', 'auth.club-memberships.index'] as $route) {
            $this->get(route($route, ['club_id' => $this->foreign->id]))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has('clubs', 1)->where('clubs.0.id', $this->own->id));
        }
        $this->withSession(['club_id' => $this->own->id])->get('/clubs')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('clubs', 1)->where('clubs.0.id', $this->own->id));
        $this->get(route('auth.events.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.id', $ownEvent->id));
        $this->getJson(route('auth.clubs.show', $this->foreign))->assertForbidden();
        $this->getJson(route('auth.teams.show', $this->foreignTeam))->assertForbidden();
        $this->getJson(route('auth.events.show', $foreignEvent))->assertForbidden();
        $this->postJson(route('auth.club-memberships.finance-entries.store', $this->foreign), [])->assertForbidden();
        $this->putJson(route('auth.club-memberships.members.update', [$this->foreign, $this->foreign->owner_id]), [])->assertForbidden();
    }

    public function test_club_role_without_memberships_has_empty_lists_and_platform_admin_keeps_access(): void
    {
        $this->own->users()->detach($this->manager->id);
        Sanctum::actingAs($this->manager);
        $this->getJson('/api/v1/clubs')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/teams')->assertOk()->assertJsonCount(0, 'data');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/teams')->assertOk()->assertJsonCount(2, 'data');
        $this->assertTrue($admin->can('view', $this->foreign));
    }

    public function test_local_manager_without_global_role_is_scoped_but_athletes_can_discover_public_clubs(): void
    {
        $this->manager->removeRole('club_manager');
        Sanctum::actingAs($this->manager);
        $this->getJson('/api/v1/clubs')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->own->id);

        $athlete = User::factory()->create();
        $athlete->assignRole('player');
        Sanctum::actingAs($athlete);
        $this->getJson('/api/v1/clubs')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->foreign->id);
        $this->getJson('/api/v1/teams')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_club_owner_cannot_use_platform_finance_permissions_inherited_from_legacy_roles(): void
    {
        Role::findByName('club_owner')->givePermissionTo(['finance.view', 'billing.manage']);
        $owner = $this->own->owner;
        $owner->assignRole('club_owner');
        foreach (['billing.manage', 'finance.view'] as $permission) {
            $this->assertFalse($owner->can($permission));
            $this->assertNotContains($permission, $owner->getAllPermissions()->pluck('name')->all());
        }

        $this->actingAs($owner);
        foreach (['invoices.index', 'payments.index', 'admin.operating-contracts.index'] as $route) {
            $this->getJson(route($route))->assertForbidden();
        }
        $this->postJson(route('invoices.store'), [])->assertForbidden();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/backoffice')->assertForbidden();
        $this->postJson('/api/v1/admin/backoffice/invoices', [])->assertForbidden();
        $this->getJson('/api/v1/clubs/'.$this->own->id.'/billing')->assertOk();

        // A separate explicit platform grant is independent of the club role.
        $owner->givePermissionTo('finance.view');
        $this->assertTrue($owner->can('finance.view'));
        $this->assertContains('finance.view', $owner->getAllPermissions()->pluck('name')->all());
    }

    private function event(Club $club): Event
    {
        return Event::create([
            'club_id' => $club->id, 'user_id' => $club->owner_id,
            'title' => 'Calendar '.$club->id, 'type' => 'meeting',
            'visibility' => 'public', 'status' => 'scheduled', 'start_time' => now()->addDay(),
        ]);
    }
}
