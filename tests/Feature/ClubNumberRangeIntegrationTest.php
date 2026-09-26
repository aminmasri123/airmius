<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubInventoryItem;
use App\Models\ClubNumberAllocation;
use App\Models\ClubNumberRange;
use App\Models\ClubNumberRangeDefault;
use App\Models\CommerceOrder;
use App\Models\Invoice;
use App\Models\MarketplaceProduct;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubPaymentNumberService;
use App\Services\ClubShopOrderNumberService;
use App\Services\ClubShopProductNumberService;
use App\Support\ClubNumberRangeReadinessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClubNumberRangeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_number_uses_default_range_and_without_default_keeps_legacy_format(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $range = $this->defaultRange($club, 'member', 'VM-', 12);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/member-number")->assertOk();
        $this->assertSame('VM-0012', DB::table('club_user')
            ->where('club_id', $club->id)->where('user_id', $member->id)->value('member_number'));
        $allocation = ClubNumberAllocation::query()->firstOrFail();
        $this->assertSame('member', $allocation->assigned_subject_type);
        $this->assertSame($member->id, $allocation->assigned_subject_id);
        $this->assertSame(13, $range->fresh()->next_number);

        $legacyMember = User::factory()->create();
        $legacyClub = Club::factory()->create(['owner_id' => User::factory()]);
        $legacyClub->users()->attach($legacyMember->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        Sanctum::actingAs($legacyClub->owner);
        $this->postJson("/api/v1/clubs/{$legacyClub->id}/members/{$legacyMember->id}/member-number")->assertOk();
        $this->assertSame('M-'.$legacyClub->id.'-00001', DB::table('club_user')
            ->where('club_id', $legacyClub->id)->where('user_id', $legacyMember->id)->value('member_number'));
        $this->assertSame(1, ClubNumberAllocation::query()->count());
    }

    public function test_member_collision_rolls_back_allocation_counter_and_membership_change(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $range = $this->defaultRange($club, 'member', 'COL-', 1);
        ClubExternalMember::query()->create([
            'club_id' => $club->id, 'created_by' => $owner->id,
            'name' => 'Bestand', 'email' => 'legacy-collision@example.test',
            'role' => 'member', 'membership_status' => 'active', 'member_number' => 'COL-0001',
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/member-number")
            ->assertUnprocessable()->assertJsonValidationErrors('number_range');
        $this->assertNull(DB::table('club_user')
            ->where('club_id', $club->id)->where('user_id', $member->id)->value('member_number'));
        $this->assertSame(1, $range->fresh()->next_number);
        $this->assertDatabaseCount('club_number_allocations', 0);
    }

    public function test_manual_and_recurring_club_invoices_use_default_range_and_link_allocations(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $manualMember = User::factory()->create();
        $recurringMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club, 'pro');
        $club->users()->attach($manualMember->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $club->users()->attach($recurringMember->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            'contribution_amount' => 25,
            'contribution_interval' => 'monthly',
            'contribution_next_invoice_on' => '2026-09-01',
        ]);
        $range = $this->defaultRange($club, 'invoice', 'VR-', 50);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$manualMember->id}/invoices", [
            'title' => 'Manuelle Vereinsrechnung', 'amount' => 10, 'due_date' => '2026-10-01',
        ])->assertCreated();
        $manualInvoice = Invoice::query()->where('user_id', $manualMember->id)->firstOrFail();
        $this->assertSame('VR-0050', $manualInvoice->number);

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-09-01'])
            ->assertSuccessful();
        $recurringInvoice = Invoice::query()->where('user_id', $recurringMember->id)->firstOrFail();
        $this->assertSame('VR-0051', $recurringInvoice->number);
        $this->assertSame(52, $range->fresh()->next_number);
        $this->assertSame(2, ClubNumberAllocation::query()
            ->where('assigned_subject_type', 'invoice')->count());
        $this->assertEqualsCanonicalizing(
            [$manualInvoice->id, $recurringInvoice->id],
            ClubNumberAllocation::query()->pluck('assigned_subject_id')->all()
        );
        $this->assertSame(0, app(ClubNumberRangeReadinessReport::class)
            ->make(true)['inventory']['allocation_collisions']['invoice']);

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-09-01'])
            ->assertSuccessful();
        $this->assertSame(2, Invoice::query()->where('club_id', $club->id)->count());
        $this->assertSame(2, ClubNumberAllocation::query()->count());
    }

    public function test_contribution_payer_receives_manual_and_separate_recurring_member_invoices(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $payer = User::factory()->create();
        $firstMember = User::factory()->create();
        $secondMember = User::factory()->create();
        $foreignMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $this->activatePlan($club, 'pro');
        $club->users()->attach($payer->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        foreach ([$firstMember, $secondMember] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'family_group_key' => 'household-one',
                'contribution_amount' => 20,
                'contribution_interval' => 'monthly',
                'contribution_next_invoice_on' => '2026-09-01',
            ]);
        }
        $foreignClub->users()->attach($foreignMember->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        Sanctum::actingAs($owner);

        $payload = [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'family_group_key' => 'household-one',
            'contribution_payer_user_id' => $payer->id,
            'contribution_amount' => 20,
            'contribution_interval' => 'monthly',
            'contribution_next_invoice_on' => '2026-09-01',
            'sepa_mandate_active' => false,
        ];
        foreach ([$firstMember, $secondMember] as $member) {
            $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}", $payload)
                ->assertOk()
                ->assertJsonPath('data.members', fn (array $members) => collect($members)->contains(
                    fn (array $entry) => $entry['id'] === $member->id
                        && data_get($entry, 'membership.contribution_payer_user_id') === $payer->id
                ));
        }

        $this->postJson("/api/v1/clubs/{$club->id}/members/invite", [
            'name' => 'Externes Familienmitglied',
            'email' => 'family-external@example.test',
            'role' => 'member',
            'membership_status' => 'active',
            'family_group_key' => 'household-one',
            'contribution_payer_user_id' => $payer->id,
            'contribution_interval' => 'none',
            'send_invitation' => false,
        ])->assertCreated();
        $this->assertDatabaseHas('club_external_members', [
            'club_id' => $club->id,
            'email' => 'family-external@example.test',
            'family_group_key' => 'household-one',
            'contribution_payer_user_id' => $payer->id,
        ]);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$firstMember->id}", [
            ...$payload,
            'contribution_payer_user_id' => $foreignMember->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('contribution_payer_user_id');

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$firstMember->id}/invoices", [
            'title' => 'Familienbeitrag', 'amount' => 20, 'due_date' => '2026-09-10',
        ])->assertCreated();
        $manual = Invoice::query()->where('source', 'manual')->sole();
        $this->assertSame($payer->id, $manual->user_id);
        $this->assertSame($firstMember->id, $manual->membership_user_id);

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-09-01'])
            ->assertSuccessful();
        $recurring = Invoice::query()->where('source', 'recurring_contribution')->get();
        $this->assertCount(2, $recurring);
        $this->assertEqualsCanonicalizing(
            [$firstMember->id, $secondMember->id],
            $recurring->pluck('membership_user_id')->all()
        );
        $this->assertTrue($recurring->every(fn (Invoice $invoice) => $invoice->user_id === $payer->id));

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-09-01'])
            ->assertSuccessful();
        $this->assertSame(2, Invoice::query()->where('source', 'recurring_contribution')->count());
    }

    public function test_club_invoice_collision_rolls_back_but_explicit_admin_number_is_preserved(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club, 'starter');
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $range = $this->defaultRange($club, 'invoice', 'DUP-', 1);
        Invoice::query()->create([
            'club_id' => $club->id, 'user_id' => $member->id, 'number' => 'DUP-0001',
            'title' => 'Bestand', 'amount' => 5, 'status' => 'open', 'source' => 'manual',
            'due_date' => now()->addDay(), 'issued_at' => now(),
        ]);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/invoices", [
            'title' => 'Kollision', 'amount' => 10, 'due_date' => '2026-10-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('number_range');
        $this->assertSame(1, $range->fresh()->next_number);
        $this->assertDatabaseCount('club_number_allocations', 0);
        $this->assertSame(1, Invoice::query()->where('club_id', $club->id)->count());

        $admin = User::factory()->create();
        Permission::findOrCreate('billing.manage', 'web');
        $admin->givePermissionTo('billing.manage');
        $this->actingAs($admin)->post(route('invoices.store'), [
            'source' => 'custom', 'club_id' => $club->id, 'user_id' => '',
            'number' => 'MANUELL-UNVERAENDERT', 'title' => 'Explizit', 'amount' => 7,
            'status' => 'open', 'due_date' => '2026-10-01', 'issued_at' => '2026-09-24',
        ])->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'club_id' => $club->id, 'number' => 'MANUELL-UNVERAENDERT',
        ]);
        $this->assertSame(1, $range->fresh()->next_number);
    }

    public function test_receipt_and_donation_numbers_are_canonical_and_preserve_external_reference(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $receiptRange = $this->defaultRange($club, 'receipt', 'BEL-', 7);
        $donationRange = $this->defaultRange($club, 'donation', 'SP-', 20);

        $payment = app(ClubPaymentNumberService::class)->create($club, [
            'user_id' => $member->id,
            'purpose' => 'donation',
            'amount' => 15,
            'status' => 'paid',
            'method' => 'bank_transfer',
            'reference' => 'BANKREFERENZ-UNVERAENDERT',
            'paid_at' => now(),
        ], $owner);

        $this->assertSame('BANKREFERENZ-UNVERAENDERT', $payment->reference);
        $this->assertSame('BEL-0007', $payment->receipt_number);
        $this->assertSame('SP-0020', $payment->donation_number);
        $this->assertSame(8, $receiptRange->fresh()->next_number);
        $this->assertSame(21, $donationRange->fresh()->next_number);
        $this->assertDatabaseHas('club_number_allocations', [
            'formatted_number' => 'BEL-0007', 'assigned_subject_type' => 'receipt',
            'assigned_subject_id' => $payment->id,
        ]);
        $this->assertDatabaseHas('club_number_allocations', [
            'formatted_number' => 'SP-0020', 'assigned_subject_type' => 'donation',
            'assigned_subject_id' => $payment->id,
        ]);
        $this->assertSame(0, app(ClubNumberRangeReadinessReport::class)
            ->make(true)['inventory']['allocation_collisions']['receipt']);
        $this->assertSame(0, app(ClubNumberRangeReadinessReport::class)
            ->make(true)['inventory']['allocation_collisions']['donation']);

        $legacyClub = Club::factory()->create(['owner_id' => User::factory()]);
        $legacy = app(ClubPaymentNumberService::class)->create($legacyClub, [
            'user_id' => $member->id, 'purpose' => 'prepayment', 'amount' => 5,
            'status' => 'paid', 'method' => 'cash', 'reference' => 'BAR-ALT', 'paid_at' => now(),
        ]);
        $this->assertNull($legacy->receipt_number);
        $this->assertNull($legacy->donation_number);
        $this->assertSame('BAR-ALT', $legacy->reference);
    }

    public function test_receipt_collision_rolls_back_payment_allocation_and_counter(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $range = $this->defaultRange($club, 'receipt', 'DUP-B-', 1);
        Payment::query()->create([
            'club_id' => $club->id, 'user_id' => $owner->id, 'purpose' => 'prepayment',
            'amount' => 1, 'status' => 'paid', 'method' => 'cash',
            'receipt_number' => 'DUP-B-0001', 'paid_at' => now(),
        ]);

        try {
            app(ClubPaymentNumberService::class)->create($club, [
                'user_id' => $owner->id, 'purpose' => 'prepayment', 'amount' => 2,
                'status' => 'paid', 'method' => 'cash', 'paid_at' => now(),
            ], $owner);
            $this->fail('Expected receipt collision validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('number_range', $exception->errors());
        }

        $this->assertSame(1, $range->fresh()->next_number);
        $this->assertDatabaseCount('club_number_allocations', 0);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_inventory_default_generates_only_blank_sku_and_collision_rolls_back(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $range = $this->defaultRange($club, 'inventory_item', 'INV-', 3);
        Sanctum::actingAs($owner);
        $payload = [
            'name' => 'Vereinsmaterial', 'quantity_total' => 1, 'condition' => 'good',
            'status' => 'active', 'requires_approval' => false,
        ];

        $generated = $this->postJson("/api/v1/clubs/{$club->id}/inventory", $payload)
            ->assertCreated();
        $item = ClubInventoryItem::query()->findOrFail($generated->json('data.id'));
        $this->assertSame('INV-0003', $item->sku);
        $this->assertDatabaseHas('club_number_allocations', [
            'formatted_number' => 'INV-0003', 'assigned_subject_type' => 'inventory_item',
            'assigned_subject_id' => $item->id,
        ]);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            ...$payload, 'name' => 'Manuell', 'sku' => 'ALT-SKU',
        ])->assertCreated()->assertJsonPath('data.sku', 'ALT-SKU');
        $this->assertSame(4, $range->fresh()->next_number);

        ClubInventoryItem::query()->create([
            'club_id' => $club->id, 'name' => 'Kollision', 'sku' => 'INV-0004',
            'quantity_total' => 1, 'quantity_available' => 1, 'condition' => 'good',
            'status' => 'active', 'requires_approval' => false,
        ]);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            ...$payload, 'name' => 'Darf nicht entstehen',
        ])->assertUnprocessable()->assertJsonValidationErrors('number_range');
        $this->assertSame(4, $range->fresh()->next_number);
        $this->assertSame(3, ClubInventoryItem::query()->where('club_id', $club->id)->count());
    }

    public function test_club_shop_product_default_generates_only_blank_sku(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $range = $this->defaultRange($club, 'shop_sku', 'SHOP-', 9);
        $service = app(ClubShopProductNumberService::class);

        $generated = $service->create([
            'club_id' => $club->id, 'user_id' => $owner->id,
            'title' => 'Vereinsartikel', 'price_cents' => 1200,
        ], $owner);
        $this->assertSame('SHOP-0009', $generated->sku);
        $this->assertDatabaseHas('club_number_allocations', [
            'formatted_number' => 'SHOP-0009', 'assigned_subject_type' => 'shop_sku',
            'assigned_subject_id' => $generated->id,
        ]);

        $manual = $service->create([
            'club_id' => $club->id, 'user_id' => $owner->id,
            'title' => 'Manueller Artikel', 'sku' => 'SKU-BESTAND', 'price_cents' => 800,
        ], $owner);
        $this->assertSame('SKU-BESTAND', $manual->sku);
        $this->assertSame(10, $range->fresh()->next_number);
        $this->assertSame(2, MarketplaceProduct::query()->where('club_id', $club->id)->count());
    }

    public function test_club_shop_documents_use_defaults_and_existing_numbers_are_preserved(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $invoiceRange = $this->defaultRange($club, 'shop_invoice', 'SHOP-R-', 30);
        $creditRange = $this->defaultRange($club, 'shop_credit_note', 'SHOP-G-', 5);
        $order = CommerceOrder::query()->create([
            'user_id' => $owner->id, 'club_id' => $club->id,
            'type' => 'marketplace_product', 'provider' => 'bank_transfer',
            'amount_cents' => 1200, 'currency' => 'EUR', 'status' => 'completed',
        ]);
        $service = app(ClubShopOrderNumberService::class);

        $this->assertSame('SHOP-R-0030', $service->assign($order, 'shop_invoice', fn () => 'LEGACY-R', $owner));
        $this->assertSame('SHOP-G-0005', $service->assign($order, 'shop_credit_note', fn () => 'LEGACY-G', $owner));
        $this->assertSame('SHOP-R-0030', $order->fresh()->invoice_number);
        $this->assertSame('SHOP-G-0005', $order->fresh()->credit_note_number);
        $this->assertSame(31, $invoiceRange->fresh()->next_number);
        $this->assertSame(6, $creditRange->fresh()->next_number);
        $this->assertSame(2, ClubNumberAllocation::query()->where('assigned_subject_id', $order->id)->count());

        $existing = CommerceOrder::query()->create([
            'user_id' => $owner->id, 'club_id' => $club->id,
            'type' => 'marketplace_product', 'provider' => 'bank_transfer',
            'amount_cents' => 900, 'currency' => 'EUR', 'status' => 'completed',
            'invoice_number' => 'MANUELL-R', 'credit_note_number' => 'MANUELL-G',
        ]);
        $this->assertSame('MANUELL-R', $service->assign($existing, 'shop_invoice', fn () => 'NEU-R', $owner));
        $this->assertSame('MANUELL-G', $service->assign($existing, 'shop_credit_note', fn () => 'NEU-G', $owner));
        $this->assertSame(31, $invoiceRange->fresh()->next_number);
        $this->assertSame(6, $creditRange->fresh()->next_number);
    }

    private function defaultRange(Club $club, string $scope, string $prefix, int $start): ClubNumberRange
    {
        $range = ClubNumberRange::query()->create([
            'club_id' => $club->id, 'scope' => $scope, 'name' => $scope,
            'prefix' => $prefix, 'suffix' => '', 'padding' => 4,
            'start_number' => $start, 'next_number' => $start,
            'reset_policy' => 'never', 'is_active' => true,
        ]);
        ClubNumberRangeDefault::query()->create([
            'club_id' => $club->id, 'scope' => $scope,
            'club_number_range_id' => $range->id, 'assigned_by' => $club->owner_id,
        ]);

        return $range;
    }

    private function activatePlan(Club $club, string $slug): void
    {
        $plan = SubscriptionPlan::query()->firstOrCreate(['slug' => $slug], [
            'target_actor' => 'verein', 'name' => ucfirst($slug),
            'monthly_price_cents' => 2990, 'yearly_price_cents' => 29900,
            'currency' => 'EUR', 'features' => [], 'sort_order' => 1,
            'is_public' => true, 'is_active' => true,
        ]);
        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => 'monthly',
        ]);
        $club->unsetRelation('currentSubscription');
    }
}
