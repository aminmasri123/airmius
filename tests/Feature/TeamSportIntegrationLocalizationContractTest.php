<?php

namespace Tests\Feature;

use App\Http\Controllers\SportIntegrationController;
use Illuminate\Support\Arr;
use Tests\TestCase;

class TeamSportIntegrationLocalizationContractTest extends TestCase
{
    public function test_team_and_sport_integration_catalogs_have_key_and_placeholder_parity(): void
    {
        foreach (['team_competitiveness', 'sport_integrations', 'maturity'] as $catalogName) {
            $reference = Arr::dot(require lang_path("de/{$catalogName}.php"));

            foreach (['de', 'en', 'fr', 'ar'] as $locale) {
                $catalog = Arr::dot(require lang_path("{$locale}/{$catalogName}.php"));
                $this->assertSame(array_keys($reference), array_keys($catalog), "{$catalogName}:{$locale} key parity");

                foreach ($reference as $key => $source) {
                    $this->assertSame(
                        $this->placeholders((string) $source),
                        $this->placeholders((string) $catalog[$key]),
                        "{$catalogName}:{$locale} placeholder parity for {$key}",
                    );
                }
            }
        }
    }

    public function test_web_sport_provider_definitions_follow_the_active_locale(): void
    {
        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            app()->setLocale($locale);
            $providers = SportIntegrationController::localizedProviders();

            $this->assertSame(
                __('sport_integrations.providers.google_fit.label', locale: $locale),
                $providers['google_fit']['label'],
            );
            $this->assertSame(
                __('sport_integrations.providers.garmin.description', locale: $locale),
                $providers['garmin']['description'],
            );
            $this->assertSame('partner_required', $providers['garmin']['status']);
            $this->assertSame(['activities', 'wellness'], $providers['garmin']['scopes']);
        }
    }

    /** @return array<int, string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);

        $placeholders = array_values(array_unique($matches[0] ?? []));
        sort($placeholders);

        return $placeholders;
    }
}
