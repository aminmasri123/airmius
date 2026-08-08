<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AppShellAccessibilityContractTest extends TestCase
{
    public function test_shell_keeps_landmarks_skip_link_and_current_page_semantics(): void
    {
        $layout = $this->source('resources/js/Components/Auth/Layouts/AppLayout.vue');
        $sidebar = $this->source('resources/js/Components/Auth/Sidebar.vue');
        $mobileNavigation = $this->source('resources/js/Components/Auth/Layouts/AppMobileBottomNav.vue');
        $workspaceSwitcher = $this->source('resources/js/Components/Auth/WorkspaceSwitcher.vue');

        self::assertStringContainsString('href="#main-content"', $layout);
        self::assertStringContainsString('id="main-content"', $layout);
        self::assertStringContainsString(':aria-label="$t(\'shell.navigation_label\')"', $sidebar);
        self::assertStringContainsString(':aria-label="$t(\'shell.mobile_navigation_label\')"', $mobileNavigation);
        self::assertStringContainsString(':aria-current="space.active ? \'page\' : undefined"', $mobileNavigation);
        self::assertStringContainsString(':aria-label="t(\'shell.workspace.choose\')"', $workspaceSwitcher);
        self::assertStringContainsString('focus-visible:ring-2', $workspaceSwitcher);
    }

    public function test_global_mobile_navigation_does_not_overlap_training_action_bars(): void
    {
        $layout = $this->source('resources/js/Components/Auth/Layouts/AppLayout.vue');

        self::assertStringContainsString("'Auth/Dashboard/Training/Index'", $layout);
        self::assertStringContainsString("'Auth/Dashboard/Training/LogCreate'", $layout);
        self::assertStringContainsString('<AppMobileBottomNav v-if="showMobileBottomNav"', $layout);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
