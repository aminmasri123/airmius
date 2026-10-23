<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_session_logout_also_revokes_mobile_devices(): void
    {
        $user = User::factory()->create();
        $user->createToken('Other phone');
        $foreign = User::factory()->create()->createToken('Foreign phone');
        $this->actingAs($user)->delete('/user/other-browser-sessions', ['password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertCount(0, $user->tokens()->get());
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->accessToken->id]);
    }

    public function test_session_heartbeat_requires_authentication(): void
    {
        $this->getJson('/session-status')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/session-status')->assertNoContent()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_other_browser_sessions_can_be_logged_out(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->delete('/user/other-browser-sessions', [
            'password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
    }
}
