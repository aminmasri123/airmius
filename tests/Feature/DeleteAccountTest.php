<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_accounts_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());
        Notification::fake();

        $this
            ->withSession([
                'account_deletion_confirmation' => [
                    'user_id' => $user->id,
                    'code_hash' => Hash::make('123456'),
                    'expires_at' => now()->addMinutes(15)->timestamp,
                ],
            ])
            ->delete('/user', [
                'code' => '123456',
            ]);

        $this->assertNull($user->fresh());
    }

    public function test_correct_code_must_be_provided_before_account_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());
        Notification::fake();

        $this
            ->withSession([
                'account_deletion_confirmation' => [
                    'user_id' => $user->id,
                    'code_hash' => Hash::make('123456'),
                    'expires_at' => now()->addMinutes(15)->timestamp,
                ],
            ])
            ->delete('/user', [
                'code' => 'wrong-code',
            ]);

        $this->assertNotNull($user->fresh());
    }
}
