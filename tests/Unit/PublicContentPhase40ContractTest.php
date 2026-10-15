<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PublicContentPhase40ContractTest extends TestCase
{
    public function test_phase_40_documentation_covers_every_scenario(): void
    {
        $report = file_get_contents(__DIR__.'/../../docs/PHASE_40_PUBLIC_CONTENT_2026-10-15.md');

        foreach (range(1, 9) as $number) {
            self::assertStringContainsString(sprintf('T40-%02d', $number), $report);
        }
    }

    public function test_cookie_preferences_are_global_persistent_and_withdrawable(): void
    {
        $app = file_get_contents(__DIR__.'/../../resources/js/app.js');
        $component = file_get_contents(__DIR__.'/../../resources/js/Components/Privacy/CookiePreferences.vue');
        $service = file_get_contents(__DIR__.'/../../resources/js/services/privacyConsent.js');
        $footer = file_get_contents(__DIR__.'/../../resources/js/Components/Guest/Footer.vue');

        self::assertStringContainsString('h(CookiePreferences)', $app);
        self::assertStringContainsString('acceptNecessary', $component);
        self::assertStringContainsString('acceptAll', $component);
        self::assertStringContainsString('saveSelection', $component);
        self::assertStringContainsString("window.localStorage.setItem(COOKIE_CONSENT_STORAGE_KEY", $service);
        self::assertStringContainsString('SameSite=Lax', $service);
        self::assertStringContainsString('source === \'user_settings\'', $service);
        self::assertStringContainsString('@click="openCookiePreferences"', $footer);
    }

    public function test_machine_readable_endpoints_are_stateless_and_filter_public_content(): void
    {
        $guestRoutes = file_get_contents(__DIR__.'/../../routes/guest.php');
        $webRoutes = file_get_contents(__DIR__.'/../../routes/web.php');
        $feed = file_get_contents(__DIR__.'/../../app/Http/Controllers/PublicBlogFeedController.php');

        foreach (["name('robots')", "name('sitemap')", "name('guest.blog.rss')"] as $route) {
            self::assertStringContainsString($route, $guestRoutes);
        }

        self::assertStringContainsString("name('site.webmanifest')", $webRoutes);
        self::assertStringContainsString('withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])', $guestRoutes);
        self::assertStringContainsString('->published()', $guestRoutes);
        self::assertStringContainsString("->where('status', 'published')", $guestRoutes);
        self::assertStringContainsString('->publishedPublic()', $guestRoutes);
        self::assertStringContainsString('->published()', $feed);
        self::assertStringContainsString('->take(self::MAX_ITEMS)', $feed);
    }

    public function test_all_supported_languages_include_cookie_preference_copy(): void
    {
        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $catalog = json_decode(
                file_get_contents(__DIR__."/../../resources/js/lang/{$locale}.json"),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            self::assertNotEmpty($catalog['cookie_consent']['title'] ?? null, $locale);
            self::assertNotEmpty($catalog['cookie_consent']['necessary_only'] ?? null, $locale);
            self::assertNotEmpty($catalog['cookie_consent']['save_selection'] ?? null, $locale);
            self::assertNotEmpty($catalog['cookie_consent']['open'] ?? null, $locale);
        }
    }
}
