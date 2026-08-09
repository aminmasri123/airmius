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
            ->assertSee(route('legal.data-erasure'), false)
            ->assertSee(route('terms.show'), false)
            ->assertSee(route('legal.community'), false)
            ->assertSee(route('legal.minors'), false)
            ->assertSee(route('legal.cookies'), false)
            ->assertSee(route('legal.withdrawal'), false)
            ->assertSee(route('legal.reporting'), false);
    }
}
