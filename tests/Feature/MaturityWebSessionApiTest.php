<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MaturityWebSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_maturity_api_accepts_authenticated_browser_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard/maturity')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Maturity/Index'));

        $this->actingAs($user)
            ->withHeader('Referer', 'http://localhost/dashboard/maturity')
            ->withHeader('X-App-Locale', 'ar')
            ->getJson('/api/v1/maturity/overview')
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('data.overview.user_id', $user->id)
            ->assertJsonPath('data.next_actions.0.key', 'name')
            ->assertJsonPath('data.next_actions.0.label', __('maturity.onboarding.name', locale: 'ar'));
    }
}
