<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\CommercePriceList;
use App\Models\Event;
use App\Models\EventCommerceAssortment;
use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Services\MarketplacePricingService;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommerceEventPriceListTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_event_quote_uses_active_versioned_price_list_and_falls_back_after_validity(): void
    {
        Carbon::setTestNow('2026-09-26 10:00:00');

        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'title' => 'Sommerfest',
            'type' => 'public',
            'visibility' => 'public',
            'start_time' => now()->addDay(),
        ]);
        $product = MarketplaceProduct::query()->create([
            'user_id' => $owner->id,
            'club_id' => $club->id,
            'title' => 'Event-Shirt',
            'category' => 'teamwear',
            'price_cents' => 2500,
            'currency' => 'EUR',
            'status' => 'published',
        ]);
        $expired = CommercePriceList::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Sommerfest 2026',
            'version' => '2026-a',
            'status' => 'active',
            'valid_from' => now()->subDays(10),
            'valid_until' => now()->subDay(),
        ]);
        $active = CommercePriceList::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Sommerfest 2026',
            'version' => '2026-b',
            'status' => 'active',
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addWeek(),
        ]);
        $expired->items()->create([
            'marketplace_product_id' => $product->id,
            'price_cents' => 1900,
            'currency' => 'EUR',
        ]);
        $active->items()->create([
            'marketplace_product_id' => $product->id,
            'price_cents' => 2100,
            'currency' => 'EUR',
        ]);
        EventCommerceAssortment::query()->create([
            'event_id' => $event->id,
            'commerce_price_list_id' => $active->id,
            'marketplace_product_id' => $product->id,
        ]);

        $quote = app(MarketplacePricingService::class)->quoteForEvent($event, $product, 'DE');

        $this->assertSame(2100, $quote['base_gross_cents']);
        $this->assertSame('2026-b', $quote['price_list_version']);

        Carbon::setTestNow('2026-10-10 10:00:00');
        $quote = app(MarketplacePricingService::class)->quoteForEvent($event, $product, 'DE');

        $this->assertSame(2500, $quote['base_gross_cents']);
        $this->assertArrayNotHasKey('price_list_version', $quote);
    }

    public function test_event_assortment_rejects_products_and_price_lists_from_other_clubs(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $owner->id]);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'title' => 'Heimspiel',
            'type' => 'match',
            'visibility' => 'organization',
            'start_time' => now()->addDay(),
        ]);
        $priceList = CommercePriceList::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Heimspiel',
            'version' => 'home-1',
            'status' => 'active',
            'valid_from' => now()->subDay(),
        ]);
        $foreignProduct = MarketplaceProduct::query()->create([
            'user_id' => $owner->id,
            'club_id' => $otherClub->id,
            'title' => 'Fremdes Trikot',
            'category' => 'teamwear',
            'price_cents' => 3999,
            'status' => 'published',
        ]);

        $this->expectException(ValidationException::class);

        EventCommerceAssortment::query()->create([
            'event_id' => $event->id,
            'commerce_price_list_id' => $priceList->id,
            'marketplace_product_id' => $foreignProduct->id,
        ]);
    }

    public function test_shop_editor_role_can_manage_event_assortments_only_in_own_club(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $club->users()->attach($editor->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $this->assign($club, $owner, $editor, 'event_shop_editor', [ClubPermissions::COMMERCE_PRODUCTS_EDIT]);

        $ownPermissions = ClubPermissions::effectiveFor($club, $editor);
        $foreignPermissions = ClubPermissions::effectiveFor($otherClub, $editor);

        $this->assertTrue($ownPermissions[ClubPermissions::COMMERCE_PRODUCTS_EDIT]);
        $this->assertFalse($foreignPermissions[ClubPermissions::COMMERCE_PRODUCTS_EDIT]);
    }

    private function assign(Club $club, User $owner, User $user, string $key, array $permissions): void
    {
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $user->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
