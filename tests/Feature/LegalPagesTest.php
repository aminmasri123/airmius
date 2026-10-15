<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_legal_pages_render_with_content(): void
    {
        $pages = [
            'legal.imprint' => 'Impressum',
            'policy.show' => 'Datenschutzerklärung',
            'legal.account-deletion' => 'Airmius-Konto löschen',
            'legal.club-deletion' => 'Airmius-Verein löschen',
            'legal.data-erasure' => 'Daten löschen',
            'terms.show' => 'Allgemeine Nutzungsbedingungen',
            'legal.community' => 'Community-Richtlinien',
            'legal.minors' => 'Jugendschutz und Elternzustimmung',
            'legal.cookies' => 'Cookie-Hinweise',
            'legal.withdrawal' => 'Widerrufsbelehrung',
            'legal.reporting' => 'Kontakt, Support und Inhalte melden',
        ];

        foreach ($pages as $routeName => $title) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Legal/Show')
                    ->where('title', $title)
                    ->has('sections.0.title')
                    ->has('sections.0.body.0')
                );
        }
    }

    public function test_sitemap_contains_required_legal_pages(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertSee(route('legal.imprint'), false)
            ->assertSee(route('policy.show'), false)
            ->assertSee(route('legal.account-deletion'), false)
            ->assertSee(route('legal.club-deletion'), false)
            ->assertSee(route('legal.data-erasure'), false)
            ->assertSee(route('terms.show'), false)
            ->assertSee(route('legal.community'), false)
            ->assertSee(route('legal.minors'), false)
            ->assertSee(route('legal.cookies'), false)
            ->assertSee(route('legal.withdrawal'), false)
            ->assertSee(route('legal.reporting'), false);
    }

    public function test_deletion_guides_are_public_as_localized_json_for_the_app(): void
    {
        foreach (['legal.account-deletion', 'legal.club-deletion'] as $routeName) {
            $this->get(route($routeName, ['format' => 'json', 'locale' => 'de']))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/json')
                ->assertJsonPath('contentLocale', 'de')
                ->assertJsonStructure(['title', 'sections' => [['title', 'body']], 'note']);

            $this->get(route($routeName, ['format' => 'json', 'locale' => 'ar']))
                ->assertOk()
                ->assertJsonPath('contentLocale', 'ar')
                ->assertJsonPath('textDirection', 'rtl')
                ->assertJsonPath('translationComplete', true);
        }
    }

    public function test_public_footer_links_to_both_deletion_guides(): void
    {
        $footer = file_get_contents(resource_path('js/Components/Guest/Footer.vue'));

        $this->assertIsString($footer);
        $this->assertStringContainsString("safeRoute('legal.account-deletion', '/konto-loeschen')", $footer);
        $this->assertStringContainsString("safeRoute('legal.club-deletion', '/verein-loeschen')", $footer);
    }

    public function test_account_deletion_guide_uses_the_actual_app_settings_label(): void
    {
        foreach ([
            'de' => 'Profil & Kontosicherheit',
            'en' => 'Profile & account security',
            'fr' => 'Profil et sécurité du compte',
            'ar' => 'الملف وأمان الحساب',
        ] as $locale => $label) {
            $step = $this->get(route('legal.account-deletion', ['format' => 'json', 'locale' => $locale]))
                ->assertOk()
                ->assertJsonPath('translationComplete', true)
                ->json('sections.2.body.0');
            $this->assertStringContainsString($label, $step);
        }
    }

    public function test_public_club_deletion_guide_names_affected_records_and_preserves_other_scopes(): void
    {
        $german = $this->get(route('legal.club-deletion', ['format' => 'json', 'locale' => 'de']))
            ->assertOk()
            ->json('sections.3.body.1');
        $this->assertStringContainsString('Gremien mit Sitzungen und Beschlüssen', $german);
        $this->assertStringContainsString('Unterlagen anderer Vereine und rein persönliche Inhalte', $german);

        $english = $this->get(route('legal.club-deletion', ['format' => 'json', 'locale' => 'en']))
            ->assertOk()
            ->json('sections.3.body.1');
        $this->assertStringContainsString('committees with meetings and resolutions', $english);
        $this->assertStringContainsString('records belonging to other clubs and purely personal content', $english);
    }

    public function test_both_deletion_guides_explain_deferred_file_cleanup_and_backup_limits(): void
    {
        foreach (['legal.account-deletion', 'legal.club-deletion'] as $routeName) {
            foreach (['de' => ['nachgelagerten Bereinigungslauf', 'Ältere Datenbank- oder Hosting-Sicherungen'],
                'en' => ['subsequent cleanup run', 'Older database or hosting backups']] as $locale => $terms) {
                $sections = $this->get(route($routeName, ['format' => 'json', 'locale' => $locale]))
                    ->assertOk()
                    ->assertJsonPath('translationComplete', true)
                    ->json('sections');
                $text = implode(' ', array_merge(...array_column($sections, 'body')));
                foreach ($terms as $term) {
                    $this->assertStringContainsString($term, $text);
                }
            }
        }
    }

    public function test_account_guide_explains_unresolved_club_loans_and_name_review(): void
    {
        foreach (['de' => 'offene Vereinsausleihen', 'en' => 'Open club loans'] as $locale => $term) {
            $sections = $this->get(route('legal.account-deletion', ['format' => 'json', 'locale' => $locale]))
                ->assertOk()
                ->assertJsonPath('translationComplete', true)
                ->json('sections');
            $text = implode(' ', array_merge(...array_column($sections, 'body')));
            $this->assertStringContainsString($term, $text);
        }
    }

    public function test_both_deletion_guides_distinguish_legal_retention_periods(): void
    {
        foreach (['legal.account-deletion', 'legal.club-deletion'] as $routeName) {
            foreach (['de' => ['zehn Jahre', 'acht Jahre', 'sechs Jahre', '§ 14b Abs. 1 UStG'],
                'en' => ['ten years', 'eight years', 'six years', '§ 14b(1) UStG']] as $locale => $terms) {
                $sections = $this->get(route($routeName, ['format' => 'json', 'locale' => $locale]))
                    ->assertOk()
                    ->assertJsonPath('translationComplete', true)
                    ->json('sections');
                $text = implode(' ', array_merge(...array_column($sections, 'body')));
                foreach ($terms as $term) {
                    $this->assertStringContainsString($term, $text);
                }
            }
        }
    }

    public function test_account_guide_does_not_promise_automatic_expiry_cleanup_for_airmius_records(): void
    {
        foreach (['de' => 'kein automatischer Fristablauf-Löschlauf',
            'en' => 'does not currently have an automatic expiry-based deletion job'] as $locale => $term) {
            $sections = $this->get(route('legal.account-deletion', ['format' => 'json', 'locale' => $locale]))
                ->assertOk()
                ->assertJsonPath('translationComplete', true)
                ->json('sections');
            $text = implode(' ', array_merge(...array_column($sections, 'body')));
            $this->assertStringContainsString($term, $text);
        }
    }
}
