<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminCreatedAccountCredentialsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminCreatedUserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_with_generated_password_and_credentials_email(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('users.create', 'web'));

        $response = $this->actingAs($admin)->post(route('members.store'), [
            'name' => 'Neuer Nutzer',
            'email' => 'new-user@example.com',
            'generate_password' => true,
            'send_credentials' => true,
            'profile_visibility' => 'public',
        ]);

        $response->assertRedirect(route('members.index'));
        $response->assertSessionHas('success', 'Nutzer wurde erstellt. Die Zugangsdaten wurden per E-Mail versendet.');

        $user = User::where('email', 'new-user@example.com')->first();

        $this->assertNotNull($user);

        Notification::assertSentTo($user, AdminCreatedAccountCredentialsNotification::class);
    }
}
