<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubPermissionDelegation;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubRoleAccessReviewReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_membership_end_creates_localized_access_review_for_every_role_manager(): void
    {
        $owner = User::factory()->create(['language' => 'de']);
        $roleManager = User::factory()->create(['language' => 'fr']);
        $departing = User::factory()->create(['name' => 'Alex Beispiel']);
        $unprivileged = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->updateExistingPivot($owner->id, [
            'role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active',
        ]);
        foreach ([$roleManager, $departing, $unprivileged] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'membership_ends_on' => $member->is($departing) ? now()->addDays(7)->toDateString() : null,
            ]);
        }
        $club->users()->updateExistingPivot($roleManager->id, [
            'permission_overrides' => [ClubPermissions::MEMBERS_ROLES => true],
        ]);
        $definition = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'temporary_office',
            'name' => 'Temporary office',
            'permissions' => [ClubPermissions::MEMBERS_VIEW],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $definition->id,
            'user_id' => $departing->id,
            'assigned_by' => $owner->id,
        ]);
        ClubPermissionDelegation::query()->create([
            'club_id' => $club->id,
            'grantor_user_id' => $departing->id,
            'grantee_user_id' => $roleManager->id,
            'permissions' => [ClubPermissions::MEMBERS_VIEW],
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(14),
        ]);

        $this->artisan('airmius:send-membership-billing-reminders', [
            '--membership-days' => 30,
        ])->assertExitCode(0);

        $reviews = Notification::query()
            ->where('type', 'club.role_access_review_due')
            ->orderBy('user_id')
            ->get();

        $this->assertCount(2, $reviews);
        $this->assertSame([$owner->id, $roleManager->id], $reviews->pluck('user_id')->sort()->values()->all());
        $this->assertFalse($reviews->contains('user_id', $unprivileged->id));
        $this->assertSame(1, data_get($reviews->firstWhere('user_id', $owner->id)?->data, 'role_assignment_count'));
        $this->assertSame(1, data_get($reviews->firstWhere('user_id', $owner->id)?->data, 'delegation_count'));
        $this->assertTrue(data_get($reviews->firstWhere('user_id', $owner->id)?->data, 'decision_required'));
        $this->assertSame(['remove', 'assign_successor'], data_get($reviews->firstWhere('user_id', $owner->id)?->data, 'decision_options'));
        $this->assertSame('fr', data_get($reviews->firstWhere('user_id', $roleManager->id)?->data, 'locale'));
        $this->assertSame(
            trans('organization.notifications.role_access_review_title', locale: 'fr'),
            data_get($reviews->firstWhere('user_id', $roleManager->id)?->data, 'title'),
        );
    }

    public function test_no_access_review_is_created_without_roles_or_live_delegations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->updateExistingPivot($owner->id, [
            'role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active',
        ]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            'membership_ends_on' => now()->addDays(7)->toDateString(),
        ]);

        $this->artisan('airmius:send-membership-billing-reminders', [
            '--membership-days' => 30,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', ['type' => 'club.role_access_review_due']);
    }

    public function test_membership_finance_and_subscription_reminders_use_their_own_club_rights(): void
    {
        $owner = User::factory()->create();
        $departing = User::factory()->create();
        $memberManager = User::factory()->create();
        $financeViewer = User::factory()->create();
        $subscriptionViewer = User::factory()->create();
        $deniedManager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$departing, $memberManager, $financeViewer, $subscriptionViewer] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'membership_ends_on' => $member->is($departing) ? now()->addDays(5)->toDateString() : null,
            ]);
        }
        $club->users()->attach($deniedManager->id, [
            'role' => 'manager',
            'membership_status' => 'active',
            'permission_overrides' => [
                ClubPermissions::MEMBERS_MANAGE => false,
                ClubPermissions::FINANCE_VIEW => false,
                ClubPermissions::SUBSCRIPTIONS_VIEW => false,
            ],
        ]);
        foreach ([
            [$memberManager, 'membership_reminder_manager', ClubPermissions::MEMBERS_MANAGE],
            [$financeViewer, 'invoice_reminder_viewer', ClubPermissions::FINANCE_VIEW],
            [$subscriptionViewer, 'subscription_reminder_viewer', ClubPermissions::SUBSCRIPTIONS_VIEW],
        ] as [$user, $key, $permission]) {
            $definition = ClubRoleDefinition::query()->create([
                'club_id' => $club->id,
                'key' => $key,
                'name' => str_replace('_', ' ', $key),
                'permissions' => [$permission],
                'is_active' => true,
            ]);
            ClubRoleAssignment::query()->create([
                'club_id' => $club->id,
                'club_role_definition_id' => $definition->id,
                'user_id' => $user->id,
                'scope_type' => 'club',
                'scope_key' => 'club',
                'assigned_by' => $owner->id,
            ]);
        }
        $club->users()->updateExistingPivot($financeViewer->id, [
            'permission_overrides' => [ClubPermissions::SUBSCRIPTIONS_VIEW => false],
        ]);
        Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $departing->id,
            'number' => 'RIGHTS-REMINDER-1',
            'title' => 'Membership fee',
            'amount' => 25,
            'status' => 'open',
            'source' => 'manual',
            'due_date' => now()->addDays(3),
            'issued_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'reminder-rights-plan',
            'target_actor' => 'verein',
            'name' => 'Reminder Rights',
            'monthly_price_cents' => 1000,
            'yearly_price_cents' => 10000,
            'currency' => 'EUR',
            'storage_gb' => 5,
            'sort_order' => 1,
            'is_public' => true,
            'is_active' => true,
        ]);
        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'cancels_at_period_end',
            'current_period_ends_at' => now()->addDays(10),
            'renewal_notified_at' => null,
        ]);

        $this->artisan('airmius:send-membership-billing-reminders')->assertSuccessful();

        foreach ([
            [$memberManager, 'club.member_membership_ending_soon'],
            [$financeViewer, 'club.invoice_due_soon'],
            [$subscriptionViewer, 'club.subscription_ending_soon'],
        ] as [$recipient, $type]) {
            $this->assertDatabaseHas('notifications', ['user_id' => $recipient->id, 'type' => $type]);
        }
        foreach ([
            'club.member_membership_ending_soon',
            'club.invoice_due_soon',
            'club.subscription_ending_soon',
        ] as $type) {
            $this->assertDatabaseMissing('notifications', ['user_id' => $deniedManager->id, 'type' => $type]);
        }
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $memberManager->id,
            'type' => 'club.invoice_due_soon',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $financeViewer->id,
            'type' => 'club.subscription_ending_soon',
        ]);
    }
}
