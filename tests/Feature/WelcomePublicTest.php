<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomePublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_welcome_page_can_be_rendered(): void
    {
        $this->get(route('welcome'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('canLogin')
                ->has('canRegister')
            );
    }

    public function test_contact_form_requires_valid_public_input(): void
    {
        $this->from(route('welcome'))
            ->post(route('contact.store'), [
                'name' => '',
                'email' => 'keine-mail',
                'message' => '',
            ])
            ->assertRedirect(route('welcome'))
            ->assertSessionHasErrors(['name', 'email', 'message']);
    }
}
