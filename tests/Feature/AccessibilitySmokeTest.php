<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccessibilitySmokeTest extends TestCase
{
    public function test_auth_layout_has_keyboard_focus_and_landmark_basics(): void
    {
        $layout = file_get_contents(resource_path('js/Components/Auth/Layouts/AppLayout.vue'));
        $searchOverlay = file_get_contents(resource_path('js/Components/Auth/Layouts/AppMobileSearchOverlay.vue'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('href="#main-content"', $layout);
        $this->assertStringContainsString('id="main-content"', $layout);
        $this->assertStringContainsString('tabindex="-1"', $layout);
        $this->assertStringContainsString(':aria-label="translatedPageTitle"', $layout);
        $this->assertStringContainsString('role="status"', $layout);
        $this->assertStringContainsString('aria-live="polite"', $layout);
        $this->assertStringContainsString('aria-haspopup="dialog"', $layout);
        $this->assertStringContainsString(':aria-label="t(\'search.open_command\')"', $layout);
        $this->assertStringContainsString(':aria-label="t(\'Benachrichtigungen öffnen\')"', $layout);

        $this->assertStringContainsString('role="dialog"', $searchOverlay);
        $this->assertStringContainsString('aria-modal="true"', $searchOverlay);
        $this->assertStringContainsString('aria-labelledby="airmius-command-title"', $searchOverlay);
        $this->assertStringContainsString(':aria-activedescendant=', $searchOverlay);
        $this->assertStringContainsString('@keydown="handleKeydown"', $searchOverlay);

        $this->assertStringContainsString('.skip-link', $css);
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('--focus-ring', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    public function test_guest_navigation_has_mobile_keyboard_and_label_basics(): void
    {
        $nav = file_get_contents(resource_path('js/Components/Guest/Nav.vue'));
        $subnav = file_get_contents(resource_path('js/Components/Guest/Subnav.vue'));
        $footer = file_get_contents(resource_path('js/Components/Guest/Footer.vue'));

        $this->assertStringContainsString(':aria-label="$t(\'guest.nav.main_aria\')"', $nav);
        $this->assertStringContainsString('aria-controls="guest-mobile-menu"', $nav);
        $this->assertStringContainsString('role="dialog"', $nav);
        $this->assertStringContainsString('aria-modal="true"', $nav);
        $this->assertStringContainsString(':aria-label="$t(\'guest.nav.close_menu_aria\')"', $nav);
        $this->assertStringContainsString('type="button"', $nav);

        $this->assertStringContainsString('role="navigation"', $subnav);
        $this->assertStringContainsString(':aria-label="$t(\'guest.subnav.quick_navigation\')"', $subnav);

        $this->assertStringContainsString('type="button" aria-label="Airmius Start"', $footer);
        $this->assertGreaterThanOrEqual(5, substr_count($footer, 'type="button"'));
    }

    public function test_focus_ring_examples_meet_non_text_contrast(): void
    {
        $this->assertGreaterThanOrEqual(3.0, $this->contrastRatio('#38bdf8', '#090d13'));
        $this->assertGreaterThanOrEqual(3.0, $this->contrastRatio('#0369a1', '#e8f4fb'));
    }

    public function test_manual_accessibility_acceptance_stays_external_release_gate(): void
    {
        $gates = json_decode(file_get_contents(resource_path('release/platform_release_gates.json')), true, flags: JSON_THROW_ON_ERROR);
        $gate = collect($gates['gates'])->firstWhere('id', 'wcag_human_acceptance');

        $this->assertSame('pending', $gate['status']);
        $this->assertSame('Accessibility Specialist / Product / QA', $gate['owner']);
        $this->assertStringContainsString('VoiceOver', $gate['required_evidence']);
        $this->assertStringContainsString('keyboard-only', $gate['required_evidence']);
    }

    private function contrastRatio(string $foreground, string $background): float
    {
        $foregroundLuminosity = $this->relativeLuminosity($foreground);
        $backgroundLuminosity = $this->relativeLuminosity($background);

        return (max($foregroundLuminosity, $backgroundLuminosity) + 0.05)
            / (min($foregroundLuminosity, $backgroundLuminosity) + 0.05);
    }

    private function relativeLuminosity(string $hex): float
    {
        $rgb = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $channels = array_map(function (int $value): float {
            $channel = $value / 255;

            return $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }
}
