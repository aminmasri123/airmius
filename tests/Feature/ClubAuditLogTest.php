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

        $invoice->update(['due_date' => now()->subDay()]);
        $this->actingAs($owner)
            ->put(route('auth.club-memberships.invoices.update', $invoice), [
                'status' => 'overdue',
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
        $this->assertSame('overdue', $latest->data['new_status']);

        $this->actingAs($owner)
            ->get(route('auth.club-memberships.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubMemberships/Index')
                ->where('clubs.0.audit_logs.0.type', 'club.invoice.status_updated')
                ->where('clubs.0.audit_logs.0.label', 'Rechnungsstatus geändert')
                ->where('clubs.0.audit_logs.0.actor.name', 'Audit Owner')
                ->where('clubs.0.audit_logs.0.data.old_status', 'open')
                ->where('clubs.0.audit_logs.0.data.new_status', 'overdue')
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

    public function test_member_removal_requires_a_reason_in_web_and_api_and_is_audited(): void
    {
        app()->setLocale('de');

        $owner = User::factory()->create();
        $webMember = User::factory()->create();
        $apiMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->attach([$webMember->id, $apiMember->id], [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        $this->actingAs($owner)
            ->delete(route('auth.club-memberships.members.destroy', [$club, $webMember]))
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $webMember->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('auth.club-memberships.members.destroy', [$club, $webMember]), [
                'reason' => 'ab',
            ])
            ->assertSessionHasErrors([
                'reason' => 'Der Grund für die Entfernung muss mindestens 3 Zeichen lang sein.',
            ]);

        $this->actingAs($owner)
            ->delete(route('auth.club-memberships.members.destroy', [$club, $webMember]), [
                'reason' => 'Mitgliedschaft wurde wegen wiederholter Regelverstöße beendet.',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('club_user', [
            'club_id' => $club->id,
            'user_id' => $webMember->id,
        ]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.member.removed',
            'subject_type' => User::class,
            'subject_id' => $webMember->id,
        ]);
        $removalNotification = \App\Models\Notification::query()
            ->where('user_id', $webMember->id)
            ->where('type', 'club.member_removed')
            ->firstOrFail();
        $this->assertSame('airmius://notifications', $removalNotification->data['mobile_url']);

        $activity = Activity::query()
            ->where('type', 'club.member.removed')
            ->where('subject_id', $webMember->id)
            ->firstOrFail();
        $this->assertSame(
            'Mitgliedschaft wurde wegen wiederholter Regelverstöße beendet.',
            $activity->data['reason'],
        );

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/clubs/{$club->id}/members/{$apiMember->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->deleteJson("/api/v1/clubs/{$club->id}/members/{$apiMember->id}", [
            'reason' => 'ab',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.reason.0',
                'Der Grund für die Entfernung muss mindestens 3 Zeichen lang sein.',
            );

        $this->deleteJson("/api/v1/clubs/{$club->id}/members/{$apiMember->id}", [
            'reason' => 'Mitgliedschaft auf Wunsch des Vereins beendet.',
        ])->assertOk();

        $this->assertDatabaseMissing('club_user', [
            'club_id' => $club->id,
            'user_id' => $apiMember->id,
        ]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.member.removed',
            'subject_type' => User::class,
            'subject_id' => $apiMember->id,
        ]);
    }
}
