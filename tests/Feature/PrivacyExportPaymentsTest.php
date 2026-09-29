<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacyExportPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_and_mobile_export_the_actual_payment_method_and_only_own_payments(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $other->id]);
        $payment = Payment::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'amount' => '24.50',
            'status' => 'paid',
            'method' => 'bank_transfer',
            'reference' => 'EXPORT-OWN',
            'paid_at' => now(),
        ]);
        Payment::create([
            'club_id' => $club->id,
            'user_id' => $other->id,
            'amount' => '99.00',
            'status' => 'paid',
            'method' => 'cash',
            'reference' => 'EXPORT-OTHER',
        ]);

        $this->assertTrue(Schema::hasColumn('payments', 'method'));
        $this->assertFalse(Schema::hasColumn('payments', 'provider'));
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/privacy/export')
            ->assertOk()
            ->assertJsonCount(1, 'data.billing.payments')
            ->assertJsonPath('data.billing.payments.0.id', $payment->id)
            ->assertJsonPath('data.billing.payments.0.method', 'bank_transfer')
            ->assertJsonPath('data.billing.payments.0.amount', '24.50')
            ->assertDontSee('EXPORT-OTHER');

        $response = $this->actingAs($user)->get(route('auth.settings.privacy.export'))->assertOk();
        $payload = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $payload['billing']['payments']);
        $this->assertSame('bank_transfer', $payload['billing']['payments'][0]['method']);
        $this->assertSame('EXPORT-OWN', $payload['billing']['payments'][0]['reference']);
        $this->assertArrayNotHasKey('provider', $payload['billing']['payments'][0]);
    }
}
