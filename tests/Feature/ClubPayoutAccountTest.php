<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubPayoutAccount;
use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Services\MarketplacePayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ClubPayoutAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_verified_account_receives_its_club_share_once(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        Gate::before(fn ($user, $ability) => $user->is($admin) && $ability === 'subscriptions.manage' ? true : null);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $other = Club::factory()->create();
        $form = ['holder' => $club->name, 'iban' => 'DE89 3704 0044 0532 0130 00'];
        $endpoint = route('auth.club-shop.account', $club);
        $this->actingAs($owner)->putJson(route('auth.club-shop.account', $other), $form)->assertForbidden();
        $this->putJson($endpoint, [...$form, 'iban' => 'DE00370400440532013000'])->assertUnprocessable();
        $this->putJson($endpoint, $form)->assertOk();
        $account = ClubPayoutAccount::sole();
        $this->assertNotSame($account->iban, $account->getRawOriginal('iban'));
        $order = CommerceOrder::create(['type' => 'marketplace_cart', 'status' => 'completed', 'provider' => 'bank_transfer',
            'amount_cents' => 9000, 'currency' => 'EUR', 'payout_status' => 'pending', 'completed_at' => now()->subDays(20)]);
        $product = MarketplaceProduct::create(['title' => 'Privates Produkt', 'user_id' => $owner->id]);
        $attrs = ['orderable_type' => MarketplaceProduct::class, 'orderable_id' => $product->id, 'quantity' => 1, 'currency' => 'EUR', 'is_shippable' => false];
        $order->items()->create([...$attrs, 'title' => 'Privat', 'total_cents' => 4000]);
        $line = $order->items()->create([...$attrs, 'title' => 'Verein', 'shop_club_id' => $club->id, 'total_cents' => 5000, 'club_commission_cents' => 200]);
        $this->assertFalse(app(MarketplacePayoutService::class)->eligibleOrdersQuery($owner)->exists());
        $url = route('admin.club-payouts.transfer', $line);
        $this->actingAs($admin)->postJson($url, ['action' => 'prepare', 'account_id' => $account->id])->assertUnprocessable();
        $this->put(route('admin.club-payouts.review', $account), ['status' => 'verified'])->assertRedirect();
        $this->postJson($url, ['action' => 'prepare', 'account_id' => $account->id + 1])->assertConflict();
        $this->post($url, ['action' => 'prepare', 'account_id' => $account->id, 'iban' => 'ignored', 'club_id' => $other->id])->assertRedirect();
        $this->assertEquals($account->id, $line->fresh()->club_payout_account_id);
        $this->assertEquals(4800, $line->fresh()->club_payout_cents);
        $this->actingAs($owner)->putJson($endpoint, $form)->assertConflict();
        $this->actingAs($admin)->post($url, ['action' => 'paid', 'account_id' => $account->id, 'reference' => 'TEST-001'])->assertRedirect();
        $this->postJson($url, ['action' => 'paid', 'account_id' => $account->id, 'reference' => 'TEST-002'])->assertConflict();
        $this->actingAs($owner)->putJson($endpoint, $form)->assertOk();
        $this->assertEquals('superseded', $account->fresh()->status);
        $this->assertEquals('pending', ClubPayoutAccount::latest('id')->first()->status);
        $this->assertEquals('TEST-001', $line->fresh()->club_bank_reference);
    }
}
