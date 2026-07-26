<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileAccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_records_the_app_device_name_for_session_tracking(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@example.com',
            'password' => Hash::make('Password-123!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password-123!',
            'device_name' => 'Airmius Android App',
        ])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Airmius Android App',
        ]);
    }

    public function test_user_can_change_password_and_other_sessions_are_revoked(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Old-password-123!')]);
        $current = $user->createToken('Pixel 9');
        $other = $user->createToken('Tablet');

        $this->withToken($current->plainTextToken)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'Old-password-123!',
                'password' => 'New-password-456!',
                'password_confirmation' => 'New-password-456!',
            ])
            ->assertOk()
            ->assertJsonPath('data.message', 'Dein Passwort wurde aktualisiert.');

        $this->assertTrue(Hash::check('New-password-456!', $user->refresh()->password));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Old-password-123!')]);
        $token = $user->createToken('Phone')->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/me/password', [
            'current_password' => 'wrong',
            'password' => 'New-password-456!',
            'password_confirmation' => 'New-password-456!',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_user_can_upload_and_remove_profile_photo(): void
    {
        Storage::fake(config('jetstream.profile_photo_disk', 'public'));
        $user = User::factory()->create();
        $token = $user->createToken('Phone')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/me/profile-photo', [
                'photo' => UploadedFile::fake()->image('avatar.jpg', 600, 600),
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['profile_photo_url']]);

        $path = $user->refresh()->profile_photo_path;
        $this->assertNotNull($path);
        Storage::disk(config('jetstream.profile_photo_disk', 'public'))->assertExists($path);

        $this->withToken($token)->deleteJson('/api/v1/me/profile-photo')->assertOk();
        $this->assertNull($user->refresh()->profile_photo_path);
        Storage::disk(config('jetstream.profile_photo_disk', 'public'))->assertMissing($path);
    }

    public function test_user_can_list_and_end_own_sessions_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $current = $user->createToken('Pixel 9');
        $tablet = $user->createToken('Tablet');
        $foreign = $otherUser->createToken('Foreign');

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/me/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['device_name' => 'Pixel 9', 'current' => true])
            ->assertJsonFragment(['device_name' => 'Tablet', 'current' => false]);

        $this->withToken($current->plainTextToken)
            ->deleteJson('/api/v1/me/sessions/'.$foreign->accessToken->id)
            ->assertNotFound();

        $this->withToken($current->plainTextToken)
            ->deleteJson('/api/v1/me/sessions/'.$tablet->accessToken->id)
            ->assertOk()
            ->assertJsonPath('data.current_session_ended', false);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tablet->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->accessToken->id]);
    }

    public function test_user_can_end_all_other_sessions(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Phone');
        $user->createToken('Tablet');
        $user->createToken('Browser');

        $this->withToken($current->plainTextToken)
            ->deleteJson('/api/v1/me/sessions/others')
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertCount(1, $user->tokens()->get());
        $this->assertSame($current->accessToken->id, $user->tokens()->first()->id);
    }
}
