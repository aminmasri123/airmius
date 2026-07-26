<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_invoice_actions_are_written_and_visible_in_audit_log(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['name' => 'Audit Owner']);
        $member = User::factory()->create(['name' => 'Audit Member']);
        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'starter'],
            [
                'target_actor' => 'verein',
                'name' => 'Starter',
                'monthly_price_cents' => 990,
                'yearly_price_cents' => 9900,
                'currency' => 'EUR',
                'features' => [],
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
            ],
        );
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);

        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('auth.club-memberships.invoices.store', [$club, $member]), [
                'title' => 'Audit Beitrag',
                'amount' => 19.9,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('title', 'Audit Beitrag')->firstOrFail();

        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.invoice.created',
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
        ]);

        $this->actingAs($owner)
            ->put(route('auth.club-memberships.invoices.update', $invoice), [
                'status' => 'paid',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.invoice.status_updated',
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
        ]);

        $latest = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame('open', $latest->data['old_status']);
        $this->assertSame('paid', $latest->data['new_status']);

        $this->actingAs($owner)
            ->get(route('auth.club-memberships.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubMemberships/Index')
                ->where('clubs.0.audit_logs.0.type', 'club.invoice.status_updated')
                ->where('clubs.0.audit_logs.0.label', 'Rechnungsstatus geaendert')
                ->where('clubs.0.audit_logs.0.actor.name', 'Audit Owner')
                ->where('clubs.0.audit_logs.0.data.old_status', 'open')
                ->where('clubs.0.audit_logs.0.data.new_status', 'paid')
                ->where('clubs.0.audit_logs.1.type', 'club.invoice.created')
            );

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/billing")
            ->assertOk()
            ->assertJsonPath('data.audit_logs.0.type', 'club.invoice.status_updated')
            ->assertJsonPath('data.audit_logs.0.actor.name', 'Audit Owner')
            ->assertJsonPath('data.audit_logs.1.type', 'club.invoice.created');
    }

    public function test_member_role_changes_are_written_to_the_club_audit_log(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role", [
            'role' => 'trainer',
        ])->assertOk();

        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.member.role_updated',
            'subject_type' => User::class,
            'subject_id' => $member->id,
        ]);

        $this->getJson("/api/v1/clubs/{$club->id}/billing")
            ->assertOk()
            ->assertJsonPath('data.audit_logs.0.type', 'club.member.role_updated')
            ->assertJsonPath('data.audit_logs.0.data.from_role', 'member')
            ->assertJsonPath('data.audit_logs.0.data.to_role', 'trainer');
    }
}
