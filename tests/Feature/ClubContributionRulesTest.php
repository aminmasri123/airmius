<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\User;
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
}
