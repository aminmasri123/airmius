<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;
use App\Services\ClubOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
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

    public function test_club_onboarding_is_computed_from_real_data_and_localized_for_web_and_mobile(): void
    {
        $owner = User::factory()->create(['language' => 'ar']);
        $admin = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Ready Club',
            'sport_type' => 'football',
            'country' => 'DE',
            'city' => 'Berlin',
            'verification_status' => 'pending',
            'membership_requests_enabled' => true,
        ]);
        $club->users()->syncWithoutDetaching([
            $admin->id => [
                'role' => 'admin',
                'roles' => ['admin'],
                'membership_status' => 'active',
            ],
        ]);
        Team::query()->create(['club_id' => $club->id, 'name' => 'First Team', 'sport_type' => 'football']);
        ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => 'Active',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('auth.club-cockpit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('clubs.0.onboarding.version', '2026-08-09.club-onboarding.v1')
                ->where('clubs.0.onboarding.completion_percent', 56)
                ->where('clubs.0.onboarding.completed_steps', 5)
                ->where('clubs.0.onboarding.steps.0.key', 'profile')
                ->where('clubs.0.onboarding.steps.0.done', true)
                ->where('clubs.0.onboarding.steps.1.key', 'verification')
                ->where('clubs.0.onboarding.steps.1.done', false));

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/clubs/'.$club->id, ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.management.onboarding.completion_percent', 56)
            ->assertJsonPath('data.management.onboarding.title', trans('club_onboarding.title', locale: 'ar'))
            ->assertJsonPath('data.management.onboarding.steps.0.action', 'profile')
            ->assertJsonCount(9, 'data.management.onboarding.steps');
    }

    public function test_club_onboarding_catalogs_have_key_and_placeholder_parity(): void
    {
        $catalogs = collect(['de', 'en', 'fr', 'ar'])->mapWithKeys(fn (string $locale) => [
            $locale => Arr::dot(require base_path('lang/'.$locale.'/club_onboarding.php')),
        ]);
        $baseline = $catalogs->get('de');

        foreach ($catalogs as $locale => $catalog) {
            $this->assertSame(array_keys($baseline), array_keys($catalog), $locale.' onboarding keys differ.');
            foreach ($baseline as $key => $value) {
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $value, $expected);
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $catalog[$key], $actual);
                $this->assertSame($expected[0], $actual[0], $locale.'.'.$key.' placeholders differ.');
            }
        }
    }

    public function test_club_onboarding_uses_a_fixed_privacy_safe_query_budget(): void
    {
        $clubs = collect(range(1, 4))->map(fn () => Club::factory()->create([
            'owner_id' => User::factory()->create()->id,
        ]));
        DB::flushQueryLog();
        DB::enableQueryLog();

        $summaries = app(ClubOnboardingService::class)->forClubs($clubs);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(4, $summaries);
        $this->assertLessThanOrEqual(9, $queryCount);
        $encoded = json_encode($summaries->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('email', $encoded);
        $this->assertStringNotContainsString('member_number', $encoded);
        $this->assertStringNotContainsString('iban', strtolower($encoded));
    }
}
