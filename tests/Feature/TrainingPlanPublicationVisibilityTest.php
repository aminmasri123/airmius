<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification;
use App\Models\Team;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingPlanPublicationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $teamAssignment = false): array
    {
        $coach = User::factory()->create();
        $athlete = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $coach->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);
        $plan = TrainingPlan::create([
            'created_by' => $coach->id, 'team_id' => $team->id, 'title' => 'Private draft plan',
            'cadence' => 'weekly', 'status' => 'draft', 'share_permission' => 'read',
            'settings' => ['target_type' => 'team', 'team_mode' => $teamAssignment ? 'team' : 'individual'],
        ]);
        $plan->assignments()->create([$teamAssignment ? 'team_id' : 'user_id' => $teamAssignment ? $team->id : $athlete->id, 'permission' => 'read']);
        $item = $plan->items()->create(['title' => 'Secret draft exercise', 'scheduled_at' => now(), 'sort_order' => 1]);

        return [$coach, $athlete, $outsider, $plan, $item, $team];
    }

    public function test_draft_is_hidden_from_individual_and_team_recipients_but_available_to_creator(): void
    {
        foreach ([false, true] as $teamAssignment) {
            [$coach, $athlete, $outsider, $plan, $item] = $this->fixture($teamAssignment);
            Sanctum::actingAs($athlete);
            $this->getJson('/api/v1/training/plans?include_items=1')->assertOk()->assertJsonMissing(['id' => $plan->id, 'title' => $plan->title]);
            $this->getJson('/api/v1/training/plans/'.$plan->id)->assertNotFound();
            $this->actingAs($athlete)->get(route('auth.training.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('plans', []));
            $this->get(route('auth.training.plans.items.show', [$plan, $item]))->assertForbidden();
            Sanctum::actingAs($coach);
            $this->getJson('/api/v1/training/plans/'.$plan->id)->assertOk()->assertJsonPath('data.status', 'draft');
            $this->actingAs($coach)->get(route('auth.training.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('plans.0.id', $plan->id));
            Sanctum::actingAs($outsider);
            $this->getJson('/api/v1/training/plans/'.$plan->id)->assertNotFound();
        }
    }

    public function test_publish_releases_to_recipient_and_notifies_once_even_when_retried(): void
    {
        [$coach, $athlete, $outsider, $plan, $item] = $this->fixture();
        Sanctum::actingAs($coach);
        $this->postJson('/api/v1/training/plans/'.$plan->id.'/items', ['title' => 'Another draft exercise'])->assertCreated();
        $this->assertSame(0, Notification::where('user_id', $athlete->id)->where('type', 'training.plan.changed')->count());
        $this->postJson('/api/v1/training/plans/'.$plan->id.'/publish')->assertOk();
        $this->postJson('/api/v1/training/plans/'.$plan->id.'/publish')->assertOk();
        $this->assertSame(1, Notification::where('user_id', $athlete->id)->where('type', 'training.plan.changed')->count());
        $alert = Notification::where('user_id', $athlete->id)->where('type', 'training.plan.changed')->sole();
        $this->assertSame('server.training.notifications.plan_published_title', $alert->data['i18n']['title_key']);
        $this->assertSame(0, Notification::where('user_id', $outsider->id)->count());
        Sanctum::actingAs($athlete);
        $this->getJson('/api/v1/training/plans/'.$plan->id)->assertOk()->assertJsonPath('data.status', 'published');
        $this->actingAs($athlete)->get(route('auth.training.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('plans.0.id', $plan->id));
        $this->get(route('auth.training.plans.items.show', [$plan, $item]))->assertOk();
        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/training/plans/'.$plan->id)->assertNotFound();
    }

    public function test_web_draft_edits_are_silent_and_save_as_published_announces_publication(): void
    {
        [$coach, $athlete, $outsider, $plan, $item, $team] = $this->fixture();
        $this->actingAs($coach);
        $payload = ['title' => $plan->title, 'cadence' => 'weekly', 'status' => 'draft', 'share_permission' => 'read', 'target_type' => 'team', 'team_mode' => 'individual', 'team_id' => $team->id, 'user_ids' => [$athlete->id]];
        $this->put(route('auth.training.plans.update', $plan), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, Notification::where('user_id', $athlete->id)->count());
        $this->post(route('auth.training.plans.items.store', $plan), ['title' => 'Draft edit'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, Notification::where('user_id', $athlete->id)->count());
        $this->put(route('auth.training.plans.update', $plan), [...$payload, 'status' => 'published'])->assertRedirect()->assertSessionHasNoErrors();
        $alert = Notification::where('user_id', $athlete->id)->where('type', 'training.plan.changed')->sole();
        $this->assertSame('server.training.notifications.plan_published_title', $alert->data['i18n']['title_key']);
    }

    public function test_assigned_recipient_cannot_publish_or_change_a_draft_even_with_write_assignment(): void
    {
        [$coach, $athlete, $outsider, $plan, $item, $team] = $this->fixture();
        $plan->assignments()->update(['permission' => 'write']);
        $team->users()->updateExistingPivot($athlete->id, ['role' => TeamRoles::COACH]);
        Sanctum::actingAs($athlete);
        $this->postJson('/api/v1/training/plans/'.$plan->id.'/publish')->assertForbidden();
        $this->postJson('/api/v1/training/plans/'.$plan->id.'/items', ['title' => 'Unauthorized edit'])->assertForbidden();
        $this->assertSame('draft', $plan->fresh()->status);
        $this->assertSame(1, $plan->items()->count());
    }

    public function test_draft_is_absent_from_search_and_log_picker_and_cannot_be_logged(): void
    {
        [$coach, $athlete, $outsider, $plan, $item] = $this->fixture();
        Sanctum::actingAs($athlete);
        $this->getJson('/api/v1/maturity/search?type=training_plan&q=Private')->assertOk()->assertJsonCount(0, 'data.results')->assertJsonPath('data.counts.training_plans', 0);
        $this->actingAs($athlete)->get(route('auth.training.logs.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('plans', []));
        $this->postJson('/api/v1/training/logs', ['title' => 'Blocked draft workout', 'sport_type' => 'gym', 'status' => 'completed', 'performed_at' => now()->toIso8601String(), 'training_plan_item_id' => $item->id])->assertForbidden();
        $this->assertDatabaseMissing('training_logs', ['training_plan_item_id' => $item->id]);
        Sanctum::actingAs($coach);
        $this->postJson('/api/v1/training/plans/'.$plan->id.'/publish')->assertOk();
        Sanctum::actingAs($athlete);
        $this->getJson('/api/v1/maturity/search?type=training_plan&q=Private')->assertOk()->assertJsonPath('data.results.0.id', $plan->id);
        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/maturity/search?type=training_plan&q=Private')->assertOk()->assertJsonCount(0, 'data.results');
    }

    public function test_creating_published_plan_on_web_notifies_selected_recipient(): void
    {
        [$coach, $athlete, $outsider, $plan, $item, $team] = $this->fixture();
        $this->actingAs($coach)->post(route('auth.training.plans.store'), [
            'title' => 'Published immediately', 'item_title' => 'First published session', 'cadence' => 'weekly', 'status' => 'published',
            'share_permission' => 'read', 'target_type' => 'team', 'team_mode' => 'individual',
            'team_id' => $team->id, 'user_ids' => [$athlete->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $published = TrainingPlan::where('title', 'Published immediately')->sole();
        $alert = Notification::where('user_id', $athlete->id)->where('type', 'training.plan.changed')->sole();
        $this->assertSame($published->id, $alert->data['training_plan_id']);
        $this->assertSame('server.training.notifications.plan_published_title', $alert->data['i18n']['title_key']);
    }
}
