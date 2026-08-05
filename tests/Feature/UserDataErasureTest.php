<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\CommerceShippingAddress;
use App\Models\Notification as AppNotification;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\UserDataErasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserDataErasureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_data_erasure_page_explains_the_process(): void
    {
        $this->get(route('legal.data-erasure'))->assertOk();
    }

    public function test_user_can_remove_selected_data_without_deleting_the_account(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'Ada Beispiel',
            'first_name' => 'Ada',
            'last_name' => 'Beispiel',
            'email' => 'ada@example.test',
            'phone' => '+49 123 456',
            'city' => 'Berlin',
            'password' => Hash::make('secure-current-password'),
        ]);
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Dieser Beitrag soll verschwinden.',
        ]);
        $address = CommerceShippingAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'Zuhause',
            'country' => 'DE',
            'city' => 'Berlin',
            'street' => 'Musterstraße',
            'house_number' => '1',
        ]);
        $order = CommerceOrder::query()->create([
            'user_id' => $user->id,
            'guest_name' => 'Ada Beispiel',
            'guest_email' => 'ada@example.test',
            'access_token' => 'temporary-order-access',
            'type' => 'marketplace',
            'provider' => 'manual',
            'amount_cents' => 1200,
            'status' => 'cancelled',
            'payload' => ['shipping_contact' => 'Ada Beispiel'],
        ]);
        $socialAccount = SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
            'email' => 'ada@example.test',
            'name' => 'Ada Beispiel',
            'access_token' => 'provider-token',
        ]);
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'test.notification',
            'data' => ['body' => 'personenbezogen'],
            'read' => false,
        ]);

        $categories = ['profile', 'content', 'social_and_integrations', 'commerce'];

        $this->actingAs($user)
            ->post(route('auth.settings.privacy.erasure.code'), [
                'identity' => 'ada@example.test',
                'categories' => $categories,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->primeConfirmation($user, $categories);

        $this->actingAs($user)
            ->post(route('auth.settings.privacy.erasure.destroy'), [
                'code' => '123456',
                'categories' => $categories,
            ])
            ->assertRedirect(route('auth.settings.privacy.erasure'))
            ->assertSessionHas('success');

        $freshUser = $user->fresh();
        $this->assertNotNull($freshUser);
        $this->assertSame('ada@example.test', $freshUser->email);
        $this->assertSame('Airmius Nutzer #'.$user->id, $freshUser->name);
        $this->assertNull($freshUser->first_name);
        $this->assertNull($freshUser->phone);
        $this->assertTrue(Hash::check('secure-current-password', $freshUser->password));

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('commerce_shipping_addresses', ['id' => $address->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $user->id]);

        $socialAccount->refresh();
        $this->assertSame('google-user-123', $socialAccount->provider_user_id);
        $this->assertNull($socialAccount->email);
        $this->assertNull($socialAccount->name);

        $order->refresh();
        $this->assertNull($order->user_id);
        $this->assertNull($order->guest_name);
        $this->assertNull($order->guest_email);
        $this->assertNull($order->access_token);
    }

    public function test_mobile_request_rejects_a_code_used_with_different_categories(): void
    {
        $user = User::factory()->create([
            'city' => 'Köln',
            'password' => Hash::make('mobile-current-password'),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/privacy/data-erasure-code', [
            'identity' => 'wrong-password',
            'categories' => ['profile'],
        ])->assertUnprocessable()->assertJsonValidationErrors('identity');

        $this->primeConfirmation($user, ['profile']);

        $this->postJson('/api/v1/privacy/data-erasure', [
            'code' => '123456',
            'categories' => ['content'],
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertSame('Köln', $user->fresh()->city);
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function primeConfirmation(User $user, array $categories): void
    {
        $normalized = app(UserDataErasureService::class)->normalizeCategories($categories);

        Cache::put("user_data_erasure_confirmation:{$user->id}", [
            'code_hash' => Hash::make('123456'),
            'categories_hash' => hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR)),
            'expires_at' => now()->addMinutes(15)->timestamp,
        ], now()->addMinutes(15));
    }
}
