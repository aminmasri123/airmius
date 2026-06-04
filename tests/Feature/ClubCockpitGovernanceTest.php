<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClubCockpitGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_cockpit_exposes_governance_privacy_and_billing_readiness(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'verification_status' => 'pending',
            'is_listed' => true,
            'teams_are_listed' => true,
            'members_can_post_to_club' => true,
            'members_can_post_to_teams' => false,
        ]);

        $club->users()->syncWithoutDetaching([
            $owner->id => [
                'role' => 'owner',
                'roles' => ['owner'],
                'membership_status' => 'active',
                'contribution_amount' => 120,
                'sepa_mandate_active' => false,
            ],
            $admin->id => [
                'role' => 'admin',
                'roles' => ['admin'],
                'membership_status' => 'active',
            ],
        ]);

        Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'number' => 'AIR-CLUB-1',
            'title' => 'Mitgliedsbeitrag',
            'amount' => 120,
            'status' => 'open',
            'source' => 'membership',
            'due_date' => now()->subDay(),
            'issued_at' => now()->subDays(14),
        ]);

        ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => User::factory()->create()->id,
            'type' => 'join',
            'status' => 'pending',
            'message' => 'Ich moechte eintreten.',
        ]);

        $this->actingAs($owner)
            ->get(route('auth.club-cockpit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubCockpit/Index')
                ->where('clubs.0.governance.level', 'watch')
                ->where('clubs.0.governance.score', 57)
                ->where('clubs.0.governance.signals.0.key', 'verification')
                ->where('clubs.0.governance.signals.0.state', 'watch')
                ->where('clubs.0.governance.signals.1.key', 'roles')
                ->where('clubs.0.governance.signals.1.state', 'strong')
                ->where('clubs.0.governance.signals.2.key', 'billing')
                ->where('clubs.0.governance.signals.2.state', 'risk')
                ->where('clubs.0.governance.signals.3.key', 'sepa')
                ->where('clubs.0.governance.signals.3.state', 'risk')
                ->where('clubs.0.governance.signals.4.key', 'privacy')
                ->where('clubs.0.governance.signals.4.state', 'watch')
                ->where('clubs.0.governance.signals.5.key', 'requests')
                ->where('clubs.0.governance.signals.5.state', 'watch')
                ->where('clubs.0.role_coverage.elevated_count', 2)
                ->where('clubs.0.stats.overdue_invoice_count', 1)
                ->where('clubs.0.stats.sepa_missing', 1)
                ->where('clubs.0.governance.tasks.0.key', 'verification'));
    }
}
