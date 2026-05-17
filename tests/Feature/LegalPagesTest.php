<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_legal_pages_do_not_expose_placeholder_todos(): void
    {
        foreach ([
            '/impressum',
            '/datenschutz',
            '/agb',
            '/community-richtlinien',
            '/jugendschutz',
            '/cookies',
            '/widerruf',
            '/kontakt-und-melden',
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('TODO', false);
        }
    }
}
