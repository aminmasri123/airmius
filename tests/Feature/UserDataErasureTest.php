<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\CommerceShippingAddress;
use App\Models\Notification as AppNotification;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\UserDataErasureCodeRequested;
use App\Notifications\UserDataErasureCompleted;
use App\Services\UserDataErasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserDataErasureTest extends TestCase
{
    use RefreshDatabase;

    public function test_code_is_sent_immediately_without_a_queue_worker(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);
        \Illuminate\Support\Facades\Queue::fake();
        $user = User::factory()->create(['password' => Hash::make('current-password')]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/privacy/data-erasure-code', [
            'identity' => 'current-password',
            'categories' => ['profile'],
        ])->assertOk();

        \Illuminate\Support\Facades\Queue::assertNothingPushed();
        $this->assertCount(1, app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
        $this->assertNotNull(Cache::get("user_data_erasure_confirmation:{$user->id}"));
    }

    public function test_failed_code_delivery_is_reported_and_invalidates_the_code(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);
        Notification::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/privacy/data-erasure-code', [
            'identity' => 'current-password',
            'categories' => ['profile'],
        ])->assertUnprocessable()->assertJsonValidationErrors('identity');

        $this->assertNull(Cache::get("user_data_erasure_confirmation:{$user->id}"));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_public_account_deletion_page_explains_the_process(): void
    {
        $this->get(route('legal.account-deletion'))->assertOk();
    }

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

    public function test_settings_exposes_the_server_owned_data_erasure_contract(): void
    {
        $user = User::factory()->create([
            'email' => 'social@example.test',
            'language' => 'fr',
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-settings-contract',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-App-Locale', 'fr')
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertHeader('Content-Language', 'fr')
            ->assertJsonPath('data.data_erasure.uses_social_login', true)
            ->assertJsonPath('data.data_erasure.account_email', 'social@example.test')
            ->assertJsonPath('data.data_erasure.category_keys', [
                'profile',
                'content',
                'messages',
                'files',
                'sport_and_health',
                'social_and_integrations',
                'commerce',
            ]);
    }

    public function test_api_erasure_result_uses_the_request_locale(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'language' => 'fr',
            'password' => Hash::make('mot-de-passe-actuel'),
        ]);
        $this->primeConfirmation($user, ['profile']);
        Sanctum::actingAs($user);

        $this->withHeader('X-App-Locale', 'fr')
            ->postJson('/api/v1/privacy/data-erasure', [
                'code' => '123456',
                'categories' => ['profile'],
            ])
            ->assertOk()
            ->assertHeader('Content-Language', 'fr')
            ->assertJsonPath('data.message', __('data_erasure.flash.completed', locale: 'fr'))
            ->assertJsonPath('data.result.retained.0', __('data_erasure.summary.retained_account', locale: 'fr'));
    }

    public function test_data_erasure_catalogs_and_transactional_emails_match_all_locales(): void
    {
        $reference = Arr::dot(require lang_path('de/data_erasure.php'));

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $catalog = Arr::dot(require lang_path($locale.'/data_erasure.php'));
            $this->assertSame(array_keys($reference), array_keys($catalog), $locale.' key parity');

            foreach ($reference as $key => $source) {
                $this->assertSame(
                    $this->translationPlaceholders((string) $source),
                    $this->translationPlaceholders((string) $catalog[$key]),
                    $locale.' placeholder parity for '.$key,
                );
            }
        }

        $notifiable = new AnonymousNotifiable;
        $this->assertSame(
            __('data_erasure.email_templates.data_erasure_code.subject', locale: 'fr'),
            (new UserDataErasureCodeRequested('123456', 'fr'))->toMail($notifiable)->subject,
        );
        $this->assertSame(
            __('data_erasure.email_templates.data_erasure_completed.subject', locale: 'ar'),
            (new UserDataErasureCompleted('Amina', 'ar'))->toMail($notifiable)->subject,
        );
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

    /**
     * @return array<int, string>
     */
    private function translationPlaceholders(string $value): array
    {
        preg_match_all('/(?::[a-zA-Z_][a-zA-Z0-9_]*|\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\})/', $value, $matches);
        $placeholders = array_map(
            fn (string $placeholder) => preg_replace('/\s+/', '', $placeholder) ?? $placeholder,
            $matches[0],
        );
        sort($placeholders);

        return $placeholders;
    }
}
