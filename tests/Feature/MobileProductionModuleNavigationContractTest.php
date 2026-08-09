<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileProductionModuleNavigationContractTest extends TestCase
{
    public function test_every_visible_mobile_module_has_exactly_one_production_destination(): void
    {
        $surface = $this->source('mobile/airmius_mobile/lib/core/airmius_mvp_surface.dart');
        $destination = $this->source('mobile/airmius_mobile/lib/navigation/airmius_module_destination.dart');

        preg_match('/mvpModuleTitles\s*=\s*<String>\{(?<body>.*?)\};/s', $surface, $surfaceMatch);
        preg_match('/return switch \(title\) \{(?<body>.*?)\n\s*\};/s', $destination, $destinationMatch);

        $this->assertArrayHasKey('body', $surfaceMatch);
        $this->assertArrayHasKey('body', $destinationMatch);

        preg_match_all("/'([^']+)'/", $surfaceMatch['body'], $visibleMatches);
        preg_match_all("/^\s*'([^']+)'\s*=>/m", $destinationMatch['body'], $destinationMatches);

        $visibleTitles = $visibleMatches[1];
        $destinationTitles = $destinationMatches[1];
        sort($visibleTitles);
        sort($destinationTitles);

        $this->assertGreaterThanOrEqual(34, count($visibleTitles));
        $this->assertCount(count(array_unique($destinationTitles)), $destinationTitles);
        $this->assertSame($visibleTitles, $destinationTitles);
        $this->assertStringContainsString(
            'AirmiusMvpSurface.mvpModuleTitles',
            $destination,
        );
    }

    public function test_shell_and_persona_home_use_the_shared_live_destination_contract(): void
    {
        $shell = $this->source('mobile/airmius_mobile/lib/screens/shell_screen.dart');
        $moduleScreen = $this->source('mobile/airmius_mobile/lib/screens/module_screen.dart');
        $sections = $this->source('mobile/airmius_mobile/lib/screens/module_sections.dart');

        $this->assertStringContainsString('AirmiusModuleDestination.resolve(', $shell);
        $this->assertStringContainsString('AirmiusModuleDestination.resolve(', $sections);
        $this->assertStringContainsString(
            'autoOpen: _openedModule!.title == _roleHomeModuleTitle',
            $shell,
        );
        $this->assertStringContainsString('this.autoOpen = false', $moduleScreen);
        $this->assertStringContainsString('autoOpen: autoOpen', $moduleScreen);
        $this->assertStringContainsString('WidgetsBinding.instance.addPostFrameCallback', $sections);
        $this->assertStringContainsString('if (widget.autoOpen) _scheduleAutomaticOpen();', $sections);
        $this->assertStringContainsString('bool _openedAutomatically = false;', $sections);
        $this->assertStringContainsString('if (!AirmiusMvpSurface.showDeveloperSuites)', $sections);
        $this->assertStringNotContainsString('_ => ModuleItemDetailScreen(', $sections);
    }

    public function test_role_specific_and_previously_missing_destinations_are_live(): void
    {
        $destination = $this->source('mobile/airmius_mobile/lib/navigation/airmius_module_destination.dart');

        $this->assertStringContainsString("'Trainingsplanung' => const TrainingPlansLogsScreen()", $destination);
        $this->assertStringContainsString("'Recruiting' => const RecruitingPipelineScreen()", $destination);
        $this->assertMatchesRegularExpression(
            "/'Sponsoren'\\s*=>\\s*user\\?\\.hasAnyRole/",
            $destination,
        );
        $this->assertStringContainsString('? const SponsorCockpitScreen()', $destination);
        $this->assertStringContainsString(': const SponsorsCenterScreen()', $destination);
    }

    public function test_global_search_uses_stable_module_keys_for_native_destinations(): void
    {
        $destination = $this->source('mobile/airmius_mobile/lib/navigation/airmius_module_destination.dart');
        $search = $this->source('mobile/airmius_mobile/lib/screens/global_search_screen.dart');

        $this->assertStringContainsString('searchableModuleTitles', $destination);
        $this->assertStringContainsString('static Widget? resolveKey(', $destination);
        $this->assertStringContainsString("if (key == 'support') return const SupportHelpdeskScreen();", $destination);
        $this->assertStringContainsString('AirmiusModuleDestination.resolveKey(', $search);
        $this->assertStringContainsString("item.payload['module_key']", $search);
        $this->assertStringContainsString("return 'module';", $search);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));

        $this->assertIsString($source, $path.' must be readable.');

        return $source;
    }
}
