<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubExternalMember;
use App\Models\User;
use App\Services\ClubContributionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubContributionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_contribution_rules_support_family_discount_and_special_types(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('auth.club-memberships.contribution-rules.store', $club), [
                'name' => 'Familienbeitrag Jahr',
                'valid_from' => now()->toDateString(),
                'billing_interval' => 'yearly',
                'amount' => 240,
                'factor_key' => 'family',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Beitragsregel gespeichert.');

        $this->assertDatabaseHas('club_contribution_rules', [
            'club_id' => $club->id,
            'name' => 'Familienbeitrag Jahr',
            'billing_interval' => 'yearly',
            'factor_key' => 'family',
            'factor_operator' => null,
            'factor_value' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('auth.club-memberships.contribution-rules.store', $club), [
                'name' => 'Rabatt ohne Typ',
                'valid_from' => now()->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => 10,
                'factor_key' => 'discount',
                'factor_value' => 15,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['factor_operator']);

        $this->actingAs($owner)
            ->post(route('auth.club-memberships.contribution-rules.store', $club), [
                'name' => 'Sonderbeitrag falsch',
                'valid_from' => now()->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => 20,
                'factor_key' => 'special',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['billing_interval']);

        $this->actingAs($owner)
            ->post(route('auth.club-memberships.contribution-rules.store', $club), [
                'name' => 'Startgebuehr',
                'valid_from' => now()->toDateString(),
                'billing_interval' => 'once',
                'amount' => 20,
                'factor_key' => 'special',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Beitragsregel gespeichert.');

        $this->assertDatabaseHas('club_contribution_rules', [
            'club_id' => $club->id,
            'name' => 'Startgebuehr',
            'billing_interval' => 'once',
            'factor_key' => 'special',
        ]);
    }

    public function test_api_contribution_rules_validate_discount_fields_and_return_rule_type_options(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/membership/contribution-rules", [
            'name' => 'Rabatt unvollstaendig',
            'valid_from' => now()->toDateString(),
            'billing_interval' => 'monthly',
            'amount' => 15,
            'factor_key' => 'discount',
            'factor_operator' => 'percent',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['factor_value']);

        $this->postJson("/api/v1/clubs/{$club->id}/membership/contribution-rules", [
            'name' => 'Ehrenamt Rabatt',
            'valid_from' => now()->toDateString(),
            'billing_interval' => 'monthly',
            'amount' => 15,
            'factor_key' => 'discount',
            'factor_operator' => 'percent',
            'factor_value' => 25,
        ])
            ->assertCreated()
            ->assertJsonPath('data.contribution_rule_types.0.value', 'standard')
            ->assertJsonPath('data.contribution_rule_types.1.value', 'family')
            ->assertJsonPath('data.contribution_rule_types.2.value', 'discount')
            ->assertJsonPath('data.contribution_rule_types.3.value', 'special')
            ->assertJsonPath('data.contribution_discount_operators.0.value', 'percent');

        $rule = ClubContributionRule::query()->where('name', 'Ehrenamt Rabatt')->firstOrFail();

        $this->assertSame('discount', $rule->factor_key);
        $this->assertSame('percent', $rule->factor_operator);
        $this->assertSame('25', $rule->factor_value);
        $this->assertSame('Rabatt', ClubContributionRule::RULE_TYPE_LABELS[$rule->factor_key]);
    }

    public function test_contribution_calculator_applies_one_matching_discount_to_the_base_rule(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->contributionRules()->create([
            'name' => 'Standard Jahr',
            'valid_from' => now()->subDay()->toDateString(),
            'billing_interval' => 'yearly',
            'amount' => 100,
            'factor_key' => 'standard',
            'is_active' => true,
        ]);
        $club->contributionRules()->create([
            'name' => 'Ehrenamt Rabatt',
            'valid_from' => now()->subDay()->toDateString(),
            'billing_interval' => 'yearly',
            'amount' => 100,
            'factor_key' => 'discount',
            'factor_operator' => 'percent',
            'factor_value' => 10,
            'is_active' => true,
        ]);

        $preview = app(ClubContributionCalculator::class)->resolve($club, $applicant, null);

        $this->assertSame('90.00', $preview['amount']);
        $this->assertSame('100.00', $preview['base_amount']);
        $this->assertSame('10.00', $preview['discount_amount']);
        $this->assertSame('standard', $preview['rule_type']);
        $this->assertNotNull($preview['discount_rule_id']);
    }

    public function test_percentage_discount_above_one_hundred_is_rejected(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('auth.club-memberships.contribution-rules.store', $club), [
                'name' => 'Ungültiger Rabatt',
                'valid_from' => now()->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => 20,
                'factor_key' => 'discount',
                'factor_operator' => 'percent',
                'factor_value' => 101,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['factor_value']);
    }

    public function test_family_group_assignment_recalculates_all_active_members(): void
    {
        $owner = User::factory()->create();
        $firstMember = User::factory()->create();
        $secondMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->contributionRules()->createMany([
            [
                'name' => 'Standardbeitrag',
                'valid_from' => now()->subDay()->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => 120,
                'factor_key' => 'standard',
                'is_active' => true,
            ],
            [
                'name' => 'Familienbeitrag',
                'valid_from' => now()->subDay()->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => 80,
                'factor_key' => 'family',
                'is_active' => true,
            ],
        ]);

        $club->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active'],
            $firstMember->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'contribution_amount' => 120,
                'contribution_interval' => 'monthly',
                'family_group_key' => 'family-7',
            ],
            $secondMember->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'contribution_amount' => 120,
                'contribution_interval' => 'monthly',
            ],
        ]);

        $this->assertSame(
            '120.00',
            app(ClubContributionCalculator::class)->resolve($club, $firstMember, null, null, 'family-7')['amount']
        );

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$secondMember->id}", [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'family_group_key' => 'FAMILY-7',
            'contribution_amount' => 120,
            'contribution_interval' => 'monthly',
        ])->assertOk();

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $firstMember->id,
            'family_group_key' => 'family-7',
            'contribution_amount' => '80.00',
        ]);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $secondMember->id,
            'family_group_key' => 'family-7',
            'contribution_amount' => '80.00',
        ]);
    }

    public function test_family_group_key_rejects_unsafe_values(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active'],
            $member->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'family_group_key' => 'family/7',
        ])->assertStatus(422)->assertJsonValidationErrors(['family_group_key']);
    }

    public function test_external_member_can_join_family_group_through_protected_api(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->contributionRules()->createMany([
            [
                'name' => 'Standard',
                'valid_from' => now()->subDay()->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => 100,
                'factor_key' => 'standard',
                'is_active' => true,
            ],
            [
                'name' => 'Family',
                'valid_from' => now()->subDay()->toDateString(),
                'billing_interval' => 'monthly',
                'amount' => 70,
                'factor_key' => 'family',
                'is_active' => true,
            ],
        ]);
        $club->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active'],
            $member->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'family_group_key' => 'home-1',
                'contribution_amount' => 100,
            ],
        ]);
        $external = ClubExternalMember::create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'email' => 'external@example.org',
            'membership_status' => 'active',
            'contribution_amount' => 100,
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/external-members/{$external->id}", [
            'name' => 'Externe Person',
            'email' => 'external@example.org',
            'membership_status' => 'active',
            'family_group_key' => 'HOME-1',
            'contribution_amount' => 100,
            'contribution_interval' => 'monthly',
        ])->assertOk();

        $this->assertDatabaseHas('club_external_members', [
            'id' => $external->id,
            'family_group_key' => 'home-1',
            'contribution_amount' => '70.00',
        ]);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'contribution_amount' => '70.00',
        ]);

        $this->deleteJson("/api/v1/clubs/{$club->id}/external-members/{$external->id}", [
            'reason' => 'Mitgliedschaft wurde beendet.',
        ])
            ->assertOk();
        $this->assertDatabaseMissing('club_external_members', ['id' => $external->id]);
    }
}
