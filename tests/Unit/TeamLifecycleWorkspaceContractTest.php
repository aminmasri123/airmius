<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use Tests\TestCase;

class TeamLifecycleWorkspaceContractTest extends TestCase
{
    public function test_team_workspace_exposes_responsive_ajax_lifecycle_tabs_and_real_insights(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Auth/Dashboard/Teams/Index.vue'));
        $workspace = file_get_contents(resource_path('js/composables/useTeamsWorkspace.js'));

        foreach (['roster', 'invitations', 'competition'] as $tab) {
            $this->assertStringContainsString("key: '{$tab}'", $source);
        }

        foreach (['events.next', 'participation?.missing_responses', 'team_actions', 'team_organizer?.season_plan'] as $insight) {
            $this->assertStringContainsString($insight, $source);
        }

        $this->assertStringContainsString('role="tablist"', $source);
        $this->assertStringContainsString('role="tabpanel"', $source);
        $this->assertStringContainsString('overflow-x-auto', $source);
        $this->assertStringContainsString('@/i18n/teamLifecycleLocalization.json', $source);
        $this->assertStringContainsString('mergeLocaleMessage(language, { team_lifecycle: copy })', $source);
        $this->assertStringContainsString('api.v1.teams.competitiveness.insights', $workspace);
        $this->assertStringNotContainsString('window.location.reload', $source.$workspace);
    }

    public function test_team_lifecycle_catalog_has_locale_key_and_placeholder_parity(): void
    {
        $catalog = json_decode(
            (string) file_get_contents(resource_path('js/i18n/teamLifecycleLocalization.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $referenceMessages = Arr::dot($catalog['de']);
        $reference = collect(array_keys($referenceMessages))->sort()->values()->all();

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $messages = Arr::dot($catalog[$locale]);
            $this->assertSame($reference, collect(array_keys($messages))->sort()->values()->all(), "{$locale} team lifecycle keys");

            foreach ($messages as $key => $value) {
                preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', (string) $referenceMessages[$key], $sourcePlaceholders);
                preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', (string) $value, $targetPlaceholders);
                sort($sourcePlaceholders[0]);
                sort($targetPlaceholders[0]);
                $this->assertSame($sourcePlaceholders[0], $targetPlaceholders[0], "{$locale}.{$key} placeholders");
            }
        }
    }
}
