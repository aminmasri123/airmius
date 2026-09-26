<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubInventoryItem;
use App\Models\ClubInventoryLoan;
use App\Models\ClubMembershipRequest;
use App\Models\ClubPolicyDocument;
use App\Models\Event;
use App\Models\File;
use App\Models\Invoice;
use App\Models\NutritionGoal;
use App\Models\NutritionMeal;
use App\Models\OperatingContract;
use App\Models\SportRoute;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Services\AthleteDailyFlowService;
use App\Support\ClubMembershipApplication;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardDailyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_daily_flow_for_athlete_day(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(10, 0));

        $user = User::factory()->create();

        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Morgenlauf',
            'sport_type' => 'running',
            'status' => 'completed',
            'performed_at' => now()->copy()->subHour(),
            'duration_minutes' => 35,
            'distance_meters' => 7200,
        ]);

        File::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Trainingsplan.pdf',
            'path' => 'uploads/trainingsplan.pdf',
            'type' => 'application/pdf',
            'size' => 4096,
        ]);

        NutritionMeal::query()->create([
            'user_id' => $user->id,
            'eaten_on' => '2026-06-01',
            'meal_type' => 'breakfast',
            'title' => 'Recovery Bowl',
            'calories' => 640,
            'protein_g' => 32,
            'carbs_g' => 85,
            'fat_g' => 14,
            'water_ml' => 800,
            'source' => 'manual',
        ]);

        SportRoute::query()->create([
            'user_id' => $user->id,
            'title' => 'Parkrunde',
            'sport_type' => 'running',
            'visibility' => 'private',
            'status' => 'planned',
            'distance_meters' => 5000,
            'waypoints' => [
                ['lat' => 52.52, 'lng' => 13.405],
                ['lat' => 52.53, 'lng' => 13.415],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('auth.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Index')
                ->has('dashboard.daily_flow.steps', 5)
                ->where('dashboard.daily_flow.steps.0.key', 'training')
                ->where('dashboard.daily_flow.steps.1.key', 'route')
                ->where('dashboard.daily_flow.steps.2.key', 'nutrition')
                ->where('dashboard.daily_flow.steps.3.key', 'hydration')
                ->where('dashboard.daily_flow.steps.4.key', 'reminders')
                ->where('dashboard.daily_flow.steps.0.progress', 78)
                ->where('dashboard.daily_flow.steps.1.body', 'Parkrunde')
                ->where('dashboard.daily_flow.steps.2.body', '1 Mahlzeiten heute')
                ->where('dashboard.daily_flow.steps.3.body', '800 ml getrunken')
                ->where('dashboard.daily_flow.score', 46)
            );
    }

    public function test_daily_flow_widget_can_be_saved_in_dashboard_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('auth.dashboard.preferences.update'), [
                'widget_keys' => ['daily_flow', 'training', 'nutrition'],
            ])
            ->assertNoContent();

        $this->assertSame(['daily_flow', 'training', 'nutrition'], $user->refresh()->dashboard_widget_keys);
    }

    public function test_dashboard_quick_actions_can_be_saved_as_ordered_personal_favorites(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('auth.dashboard.preferences.update'), [
                'quick_action_keys' => ['events', 'files', 'feed', 'events'],
            ])
            ->assertNoContent();

        $this->assertSame(['events', 'files', 'feed'], $user->refresh()->dashboard_quick_action_keys);

        $this->get(route('auth.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboard.preferences.quick_actions', ['events', 'files', 'feed']));

        $this->patch(route('auth.dashboard.preferences.update'), [
            'quick_action_keys' => ['events', 'files', 'feed', 'teams', 'notifications'],
        ])->assertSessionHasErrors('quick_action_keys');

        $this->patch(route('auth.dashboard.preferences.update'), [
            'quick_action_keys' => ['unknown'],
        ])->assertSessionHasErrors('quick_action_keys.0');
    }

    public function test_dashboard_combines_all_visible_event_types_and_excludes_foreign_private_events(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(10, 0));

        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $club->users()->attach($user->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);

        foreach (['training', 'match', 'meeting', 'public'] as $offset => $type) {
            Event::query()->create([
                'club_id' => $club->id,
                'user_id' => $club->owner_id,
                'title' => ucfirst($type).' im Verein',
                'type' => $type,
                'visibility' => 'organization',
                'status' => 'scheduled',
                'start_time' => now()->addDays($offset + 1),
            ]);
        }
        Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Fremder privater Termin',
            'type' => 'meeting',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->get(route('auth.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.events.upcoming_count', 4)
                ->has('dashboard.events.next', 4)
                ->where('dashboard.events.next.0.type', 'training')
                ->where('dashboard.events.next.1.type', 'match')
                ->where('dashboard.events.next.2.type', 'meeting')
                ->where('dashboard.events.next.3.type', 'public'));

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertJsonPath('data.steps.4.body', 'Training im Verein');
    }

    public function test_dashboard_attention_is_limited_to_authorized_club_work(): void
    {
        $manager = User::factory()->create();
        $regularMember = User::factory()->create();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'permission_overrides' => [
                ClubPermissions::MEMBERS_APPROVE => true,
                ClubPermissions::FINANCE_VIEW => true,
                ClubPermissions::INVENTORY_APPROVE => true,
            ],
        ]);
        $club->users()->attach($regularMember->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);

        ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => User::factory()->create()->id,
            'type' => 'join',
            'status' => 'pending',
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        TeamJoinRequest::query()->create([
            'team_id' => $team->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'pending',
        ]);
        Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $regularMember->id,
            'number' => 'DASH-OVERDUE-1',
            'title' => 'Offener Beitrag',
            'amount' => 42.50,
            'status' => 'overdue',
            'source' => 'membership',
            'due_date' => now()->subDay(),
            'issued_at' => now()->subMonth(),
        ]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Freigabepflichtiger Ball',
            'quantity_total' => 2,
            'quantity_available' => 2,
            'condition' => 'good',
            'status' => 'active',
            'requires_approval' => true,
        ]);
        ClubInventoryLoan::query()->create([
            'club_id' => $club->id,
            'club_inventory_item_id' => $item->id,
            'borrower_id' => $regularMember->id,
            'requested_by' => $regularMember->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);

        $foreignClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        ClubMembershipRequest::query()->create([
            'club_id' => $foreignClub->id,
            'user_id' => User::factory()->create()->id,
            'type' => 'join',
            'status' => 'pending',
        ]);

        $this->actingAs($manager)
            ->get(route('auth.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.attention.total', 4)
                ->has('dashboard.attention.items', 3)
                ->where('dashboard.attention.items.0.key', 'applications')
                ->where('dashboard.attention.items.0.count', 2)
                ->where('dashboard.attention.items.0.membership_count', 1)
                ->where('dashboard.attention.items.0.team_count', 1)
                ->where('dashboard.attention.items.1.key', 'payments')
                ->where('dashboard.attention.items.1.count', 1)
                ->where('dashboard.attention.items.1.amount_cents', 4250)
                ->where('dashboard.attention.items.2.key', 'approvals')
                ->where('dashboard.attention.items.2.inventory_count', 1));

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertJsonPath('data.attention.total', 4)
            ->assertJsonPath('data.attention.items.0.key', 'applications')
            ->assertJsonPath('data.attention.items.1.amount_cents', 4250)
            ->assertJsonPath('data.attention.items.2.inventory_count', 1);

        $this->actingAs($regularMember)
            ->get(route('auth.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.attention.total', 0)
                ->has('dashboard.attention.items', 0));
    }

    public function test_dashboard_attention_reports_missing_application_documents_and_expiring_records(): void
    {
        $manager = User::factory()->create([
            'athlete_license_number' => 'LIC-MANAGER',
            'athlete_license_valid_until' => today()->addDays(40),
        ]);
        Permission::findOrCreate('finance.view', 'web');
        $manager->givePermissionTo('finance.view');
        $requiredDocument = [
            'id' => 'current-statutes',
            'type' => 'statutes',
            'title' => 'Aktuelle Satzung',
            'url' => 'https://example.test/statutes.pdf',
            'is_visible' => true,
            'is_required' => true,
        ];
        $club = Club::factory()->create([
            'owner_id' => User::factory()->create()->id,
            'membership_application_documents' => [$requiredDocument],
            'tax_exemption_valid_until' => today()->addDays(30),
            'federation_affiliations' => [[
                'name' => 'Testverband',
                'valid_until' => today()->addDays(45)->toDateString(),
            ]],
        ]);
        $club->users()->attach($manager->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'permission_overrides' => [
                ClubPermissions::MEMBERS_APPROVE => true,
                ClubPermissions::MEMBERS_VIEW => true,
                ClubPermissions::POLICY_DOCUMENTS_VIEW => true,
                ClubPermissions::CLUB_LEGAL_EDIT => true,
            ],
        ]);
        ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => User::factory()->create()->id,
            'type' => 'join',
            'status' => 'pending',
            'accepted_documents' => [],
        ]);
        ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => User::factory()->create()->id,
            'type' => 'join',
            'status' => 'pending',
            'accepted_documents' => [[
                'id' => 'current-statutes',
                'version' => ClubMembershipApplication::documentVersion($requiredDocument),
            ]],
        ]);
        $file = File::query()->create([
            'club_id' => $club->id,
            'user_id' => $manager->id,
            'display_name' => 'ordnung.pdf',
            'path' => 'test/ordnung.pdf',
            'type' => 'application/pdf',
            'size' => 3,
        ]);
        ClubPolicyDocument::query()->create([
            'club_id' => $club->id,
            'file_id' => $file->id,
            'created_by' => $manager->id,
            'type' => 'regulation',
            'title' => 'Spielordnung',
            'version_label' => '2026',
            'valid_from' => today()->subYear(),
            'valid_until' => today()->addDays(20),
        ]);
        OperatingContract::query()->create([
            'name' => 'Vereinssoftware',
            'category' => 'software',
            'status' => 'active',
            'amount' => 49,
            'currency' => 'EUR',
            'billing_interval' => 'monthly',
            'notice_until_on' => today()->addDays(25),
        ]);
        ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $manager->id,
            'name' => 'Externe Sportlerin',
            'email' => 'external-license@example.test',
            'role' => 'member',
            'membership_status' => 'active',
            'athlete_license_number' => 'LIC-EXTERNAL',
            'athlete_license_valid_until' => today()->subDay(),
        ]);

        $this->actingAs($manager)
            ->get(route('auth.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.attention.total', 9)
                ->has('dashboard.attention.items', 3)
                ->where('dashboard.attention.items.0.key', 'applications')
                ->where('dashboard.attention.items.0.count', 2)
                ->where('dashboard.attention.items.1.key', 'documents')
                ->where('dashboard.attention.items.1.count', 1)
                ->where('dashboard.attention.items.1.request_count', 1)
                ->where('dashboard.attention.items.2.key', 'deadlines')
                ->where('dashboard.attention.items.2.count', 6)
                ->where('dashboard.attention.items.2.contract_count', 1)
                ->where('dashboard.attention.items.2.policy_document_count', 1)
                ->where('dashboard.attention.items.2.legal_record_count', 2)
                ->where('dashboard.attention.items.2.license_count', 2));

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertJsonPath('data.attention.total', 9)
            ->assertJsonPath('data.attention.items.1.key', 'documents')
            ->assertJsonPath('data.attention.items.2.key', 'deadlines');
    }

    public function test_athlete_license_validity_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_09_26_000011_add_validity_to_athlete_licenses.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'athlete_license_valid_until'));
        $this->assertFalse(Schema::hasColumn('club_external_members', 'athlete_license_valid_until'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'athlete_license_valid_until'));
        $this->assertTrue(Schema::hasColumn('club_external_members', 'athlete_license_valid_until'));
    }

    public function test_dashboard_attention_reuses_actionable_team_tasks_without_counting_stable_routines(): void
    {
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($member->id, ['role' => 'member']);
        Event::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $club->owner_id,
            'title' => 'Training mit Rückmeldung',
            'type' => 'training',
            'status' => 'scheduled',
            'visibility' => 'team',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'participant_response_required' => true,
            'participant_response_deadline_at' => now()->addHours(12),
        ]);

        $this->actingAs($member)
            ->get(route('auth.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.attention.total', 2)
                ->has('dashboard.attention.items', 1)
                ->where('dashboard.attention.items.0.key', 'tasks')
                ->where('dashboard.attention.items.0.count', 2)
                ->where('dashboard.attention.items.0.team_count', 1));

        $team->events()->delete();

        $this->actingAs($member)
            ->get(route('auth.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.attention.total', 0)
                ->has('dashboard.attention.items', 0));
    }

    public function test_mobile_api_exposes_daily_flow_contract(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(10, 0));

        $user = User::factory()->create();

        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Morgenlauf',
            'sport_type' => 'running',
            'status' => 'completed',
            'performed_at' => now()->copy()->subHour(),
            'duration_minutes' => 35,
            'distance_meters' => 7200,
        ]);

        File::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Trainingsplan.pdf',
            'path' => 'uploads/trainingsplan.pdf',
            'type' => 'application/pdf',
            'size' => 4096,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertJsonPath('data.steps.0.key', 'training')
            ->assertJsonPath('data.steps.1.key', 'route')
            ->assertJsonPath('data.steps.2.key', 'nutrition')
            ->assertJsonPath('data.steps.3.key', 'hydration')
            ->assertJsonPath('data.steps.4.key', 'reminders')
            ->assertJsonPath('data.steps.0.progress', 78)
            ->assertJsonPath('data.files.count', 1)
            ->assertJsonPath('data.files.bytes', 4096)
            ->assertJsonPath('data.mobile_context.shell.navigation', 'bottom_tabs')
            ->assertJsonPath('data.mobile_context.shell.primary_action', 'start_training')
            ->assertJsonPath('data.mobile_context.quick_actions.0.key', 'start_training')
            ->assertJsonPath('data.mobile_context.quick_actions.1.key', 'scan_meal')
            ->assertJsonPath('data.mobile_context.quick_actions.1.required_permissions.0', 'camera')
            ->assertJsonPath('data.mobile_context.quick_actions.2.key', 'start_tracking')
            ->assertJsonPath('data.mobile_context.quick_actions.2.required_permissions.1', 'location_background')
            ->assertJsonPath('data.mobile_context.offline_status.retry_header', 'Idempotency-Key')
            ->assertJsonStructure([
                'data' => [
                    'score',
                    'files' => ['count', 'bytes'],
                    'summary',
                    'coach_note',
                    'mobile_context' => [
                        'shell',
                        'quick_actions',
                        'offline_status',
                        'permission_prompts',
                    ],
                    'steps' => [
                        '*' => ['key', 'title', 'body', 'meta', 'progress', 'href', 'cta', 'icon'],
                    ],
                ],
            ]);
    }

    public function test_mobile_daily_flow_classifies_offline_read_write_and_critical_actions(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->json('data.mobile_context');

        $actions = collect($response['quick_actions'])->keyBy('key');

        $this->assertSame('queue_write', $actions['start_training']['offline_mode']);
        $this->assertSame('/api/v1/training/logs', $actions['start_training']['api_target']);
        $this->assertSame('queue_points', $actions['start_tracking']['offline_mode']);
        $this->assertSame('read_cache', $actions['open_next_event']['offline_mode']);
        $this->assertSame('requires_network', $actions['scan_meal']['offline_mode']);
        $this->assertContains('camera', $actions['scan_meal']['required_permissions']);

        $this->assertSame('network_first_with_cache', $response['offline_status']['read_strategy']);
        $this->assertSame('idempotent_retry_queue', $response['offline_status']['write_strategy']);
        $this->assertSame('Idempotency-Key', $response['offline_status']['retry_header']);
        $this->assertSame('/api/v1/mobile/sync', $response['offline_status']['sync_endpoint']);

        $criticalTargets = collect($response['permission_prompts'])->pluck('api_target')->all();
        $this->assertContains('/api/v1/nutrition/ai/meal-image', $criticalTargets);
        $this->assertContains('/api/v1/mobile/push-devices', $criticalTargets);
        $this->assertNotContains('queue_write', collect($response['permission_prompts'])->pluck('offline_mode')->all());
    }

    public function test_daily_flow_personalizes_goals_and_proves_weekly_plan_continuity(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 3)->setTime(10, 0));

        $user = User::factory()->create();
        NutritionGoal::query()->create([
            'user_id' => $user->id,
            'daily_calories_target' => 2800,
            'body_weight_kg' => 80,
            'water_target_mode' => 'auto',
        ]);
        $plan = TrainingPlan::query()->create([
            'created_by' => $user->id,
            'title' => 'Wettkampfwoche',
            'status' => 'published',
        ]);
        $mondayItem = TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Montagslauf',
            'scheduled_at' => now()->copy()->startOfWeek()->addHours(8),
            'duration_minutes' => 45,
        ]);
        $todayItem = TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Tempolauf',
            'scheduled_at' => now()->copy()->setTime(17, 0),
            'duration_minutes' => 60,
        ]);
        TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Langer Lauf',
            'scheduled_at' => now()->copy()->endOfWeek()->subDays(2)->setTime(9, 0),
            'duration_minutes' => 45,
        ]);

        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'training_plan_id' => $plan->id,
            'training_plan_item_id' => $mondayItem->id,
            'title' => 'Montagslauf erledigt',
            'status' => 'completed',
            'performed_at' => now()->copy()->startOfWeek()->addHours(8),
            'duration_minutes' => 45,
        ]);
        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Mobility',
            'status' => 'completed',
            'performed_at' => now()->copy()->subHour(),
            'duration_minutes' => 60,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertJsonPath('data.goals.training_minutes', 60)
            ->assertJsonPath('data.goals.calories', 2800)
            ->assertJsonPath('data.goals.water_ml', 3150)
            ->assertJsonPath('data.goals.water_mode', 'auto')
            ->assertJsonPath('data.week.status', 'on_track')
            ->assertJsonPath('data.week.planned_sessions', 3)
            ->assertJsonPath('data.week.due_sessions', 1)
            ->assertJsonPath('data.week.completed_planned_sessions', 1)
            ->assertJsonPath('data.week.completed_sessions', 2)
            ->assertJsonPath('data.week.adherence_percent', 100)
            ->assertJsonPath('data.week.days.0.state', 'completed')
            ->assertJsonPath('data.week.days.2.state', 'partial')
            ->assertJsonPath('data.primary_action.key', 'complete_training')
            ->assertJsonPath('data.primary_action.api_target', '/api/v1/training/logs')
            ->assertJsonPath('data.primary_action.href', route('auth.training.logs.create', ['plan_item_id' => $todayItem->id]));
    }

    public function test_daily_flow_respects_arabic_locale_and_does_not_leak_private_notes(): void
    {
        $user = User::factory()->create();
        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Privates Training',
            'status' => 'completed',
            'performed_at' => now()->subHour(),
            'duration_minutes' => 30,
            'notes' => 'PRIVATE-COACH-NOTE',
            'metrics' => ['private_marker' => 'PRIVATE-METRIC'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Locale', 'ar')
            ->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertHeader('X-Airmius-Text-Direction', 'rtl')
            ->assertJsonPath('data.steps.0.title', 'التدريب')
            ->assertJsonPath('data.primary_action.label', 'إضافة الماء');

        $response->assertDontSee('PRIVATE-COACH-NOTE');
        $response->assertDontSee('PRIVATE-METRIC');
    }

    public function test_daily_flow_query_budget_stays_bounded_with_a_visible_plan(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::query()->create([
            'created_by' => $user->id,
            'title' => 'Budgetplan',
            'status' => 'published',
        ]);
        TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Budgeteinheit',
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 45,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(AthleteDailyFlowService::class)->forUser($user);
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $queryCount, "Daily flow used {$queryCount} queries");
    }

    public function test_daily_flow_ui_and_localization_contracts_are_complete(): void
    {
        $dashboard = (string) file_get_contents(resource_path('js/Pages/Auth/Dashboard/Index.vue'));
        $widget = (string) file_get_contents(resource_path('js/Components/Dashboard/DashboardDailyFlowWidget.vue'));

        $this->assertStringContainsString("import DashboardDailyFlowWidget from '@/Components/Dashboard/DashboardDailyFlowWidget.vue'", $dashboard);
        $this->assertStringContainsString("v-if=\"isWidgetVisible('daily_flow')\"", $dashboard);
        $this->assertStringContainsString(':daily-flow="dailyFlow"', $dashboard);
        $this->assertStringContainsString('week.days', $widget);
        $this->assertStringContainsString('primaryAction', $widget);

        $reference = Arr::dot(require lang_path('de/athlete_today.php'));
        foreach (['en', 'fr', 'ar'] as $locale) {
            $catalog = Arr::dot(require lang_path("{$locale}/athlete_today.php"));
            $this->assertSame(array_keys($reference), array_keys($catalog), "athlete_today:{$locale} key parity");

            foreach ($reference as $key => $source) {
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $source, $sourceMatches);
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $catalog[$key], $translatedMatches);
                $this->assertSame($sourceMatches[0], $translatedMatches[0], "athlete_today:{$locale} placeholder parity for {$key}");
            }
        }
    }
}
