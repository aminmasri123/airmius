<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Club;
use App\Models\CommerceAuditLog;
use App\Models\CommerceOrder;
use App\Models\CommerceReturnRequest;
use App\Models\CommerceShippingRate;
use App\Models\CommerceTaxRate;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceSellerApplication;
use App\Models\PayoutProfile;
use App\Models\PublicContactRequest;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionCoupon;
use App\Models\User;
use App\Models\WebsiteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminCommerceListsExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::findOrCreate('marketplace.manage');
        $admin = User::factory()->create();
        $admin->givePermissionTo('marketplace.manage');
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_every_list_search_column_relation_status_and_page_with_nonempty_data(): void
    {
        $this->admin();
        $cases = [
            'products' => [MarketplaceProduct::class, 'draft', 'published', ['title', 'user.email']],
            'orders' => [CommerceOrder::class, 'pending', 'completed', ['invoice_number', 'guest_email', 'user.email', 'tracking_number']],
            'payouts' => [MarketplacePayout::class, 'prepared', 'paid', ['reference', 'user.email']],
            'payout_profiles' => [PayoutProfile::class, 'review', 'blocked', ['account_holder', 'user.email']],
            'return_requests' => [CommerceReturnRequest::class, 'requested', 'approved', ['reason']],
            'coupons' => [SubscriptionCoupon::class, '1', '0', ['name', 'code']],
            'addons' => [SubscriptionAddon::class, '1', '0', ['name']],
            'tax_rates' => [CommerceTaxRate::class, '1', '0', ['name', 'country_code']],
            'shipping_rates' => [CommerceShippingRate::class, '1', '0', ['name', 'country_code']],
            'seller_applications' => [MarketplaceSellerApplication::class, 'pending', 'rejected', ['business_name', 'user.email']],
            'website_requests' => [WebsiteRequest::class, 'new', 'done', ['club_name', 'domain', 'guest_email', 'user.email', 'club.name']],
            'campaigns' => [AdCampaign::class, 'draft', 'active', ['name', 'headline']],
            'public_contact_requests' => [PublicContactRequest::class, 'new', 'completed', ['name', 'email', 'subject']],
            'audit_logs' => [CommerceAuditLog::class, 'ignored', 'ignored', ['action', 'note', 'user.email']],
        ];
        $dashboard = ['products', 'orders', 'payouts', 'payout_profiles', 'return_requests'];
        foreach ($cases as $key => [$model, $status, $otherStatus, $columns]) {
            $ids = [];
            for ($i = 0; $i < 4; $i++) {
                $prefix = $i === 3 ? 'haystack' : 'needle';
                $user = User::factory()->create(['email' => "$prefix-user-email-$key-$i@example.test"]);
                $club = Club::factory()->create(['name' => "$prefix-club-name", 'owner_id' => $user->id]);
                $attributes = [];
                foreach ($columns as $column) {
                    if (! str_contains($column, '.')) {
                        $attributes[$column] = $column === 'country_code'
                            ? ($prefix === 'needle' ? 'NZ' : 'DE')
                            : "$prefix-$column";
                    }
                }
                if (in_array($key, ['coupons', 'addons', 'tax_rates', 'shipping_rates'])) {
                    $attributes['is_active'] = $i === 2 ? false : true;
                } elseif ($key !== 'audit_logs') {
                    $attributes['status'] = $i === 2 ? $otherStatus : $status;
                }
                if (in_array('user.email', $columns)) {
                    $attributes['user_id'] = $user->id;
                }
                if ($key === 'website_requests') {
                    $attributes['club_id'] = $club->id;
                }
                if ($key === 'orders') {
                    $attributes['type'] = 'marketplace_product';
                    $attributes['provider'] = 'bank_transfer';
                    $attributes['amount_cents'] = 100;
                    $attributes['currency'] = 'EUR';
                }
                if ($key === 'coupons') {
                    $attributes['code'] .= "-$i";
                }
                if ($key === 'addons') {
                    $attributes['slug'] = "$key-$i";
                }
                if ($key === 'return_requests') {
                    $attributes['commerce_order_id'] = CommerceOrder::query()->create(['type' => 'marketplace_product', 'provider' => 'bank_transfer', 'amount_cents' => 100, 'currency' => 'EUR'])->id;
                }
                if ($key === 'public_contact_requests') {
                    $attributes['message'] = 'Private contact message';
                }
                $ids[] = $model::query()->create($attributes)->id;
            }
            $url = '/api/v1/admin/commerce'.(in_array($key, $dashboard) ? '' : '/catalog');
            $rowsPath = in_array($key, ['products', 'orders']) ? "data.$key.data" : "data.$key";
            foreach ($columns as $column) {
                $search = match ($column) {
                    'country_code' => 'NZ',
                    'user.email' => 'needle-user-email',
                    'club.name' => 'needle-club-name',
                    default => 'needle-'.$column,
                };
                $query = [$key.'_search' => $search, $key.'_status' => $status, 'per_page' => 1];
                $total = $key === 'audit_logs' ? 3 : 2;
                $response = $this->getJson($url.'?'.http_build_query($query))->assertOk();
                $response->assertJsonPath("data.pagination.$key.total", $total)
                    ->assertJsonPath("data.pagination.$key.last_page", $total)
                    ->assertJsonCount(1, $rowsPath)
                    ->assertJsonPath("$rowsPath.0.id", $ids[$total - 1]);
                $this->getJson($url.'?'.http_build_query([...$query, $key.'_page' => 2]))
                    ->assertOk()->assertJsonPath("data.pagination.$key.current_page", 2)
                    ->assertJsonPath("$rowsPath.0.id", $ids[$total - 2]);
                $this->getJson($url.'?'.http_build_query([...$query, $key.'_search' => 'absent-marker']))
                    ->assertOk()->assertJsonPath("data.pagination.$key.total", 0);
            }
        }
    }

    public function test_csv_download_exceeds_old_cap_matches_filters_and_neutralizes_formulas(): void
    {
        $this->admin();
        $rows = [];
        for ($i = 0; $i < 1005; $i++) {
            $rows[] = [
                'type' => 'marketplace_product', 'status' => 'completed', 'provider' => 'bank_transfer',
                'invoice_number' => "MATCH-$i", 'guest_email' => 'buyer@example.test',
                'amount_cents' => 1234, 'currency' => 'EUR',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('commerce_orders')->insert($chunk);
        }
        $dangerous = ['=1+1', '+SUM(1;2)', '-1+2', '@SUM(A1)', " \t=HYPERLINK(1)", "\r=1", 'quote";semi', "line\nnext"];
        foreach ($dangerous as $index => $tracking) {
            CommerceOrder::query()->create([
                'type' => 'marketplace_product', 'status' => 'completed', 'provider' => 'bank_transfer', 'amount_cents' => 100, 'currency' => 'EUR',
                'invoice_number' => "MATCH-special-$index", 'tracking_number' => $tracking,
            ]);
        }
        CommerceOrder::query()->create(['type' => 'marketplace_product', 'provider' => 'bank_transfer', 'amount_cents' => 100, 'currency' => 'EUR', 'status' => 'pending', 'invoice_number' => 'MATCH-wrong-status']);
        CommerceOrder::query()->create(['type' => 'marketplace_product', 'provider' => 'bank_transfer', 'amount_cents' => 100, 'currency' => 'EUR', 'status' => 'completed', 'invoice_number' => 'EXCLUDED']);
        $response = $this->get('/api/v1/admin/commerce/export?format=csv&orders_search=MATCH&orders_status=completed&orders_page=99')
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('airmius-commerce-export.csv');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $header = fgetcsv($stream, escape: '', separator: ';');
        $parsed = [];
        while (($row = fgetcsv($stream, escape: '', separator: ';')) !== false) {
            $parsed[] = array_combine($header, $row);
        }
        fclose($stream);
        $this->assertCount(1013, $parsed);
        $this->assertCount(1013, array_unique(array_column($parsed, 'order_id')));
        $this->assertNotContains('EXCLUDED', array_column($parsed, 'invoice_number'));
        $this->assertNotContains('MATCH-wrong-status', array_column($parsed, 'invoice_number'));
        foreach ($dangerous as $index => $value) {
            $row = collect($parsed)->firstWhere('invoice_number', "MATCH-special-$index");
            $this->assertSame($index < 6 ? "'".$value : $value, $row['tracking_number']);
        }
        $this->getJson('/api/v1/admin/commerce/export?orders_search=MATCH&orders_status=completed&per_page=2&orders_page=2')
            ->assertOk()->assertJsonCount(2, 'data.rows')->assertJsonPath('data.meta.total', 1013);
    }

    public function test_list_and_download_authorization_and_query_validation(): void
    {
        $urls = ['/api/v1/admin/commerce', '/api/v1/admin/commerce/catalog', '/api/v1/admin/commerce/export?format=csv'];
        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        Sanctum::actingAs(User::factory()->create());
        foreach ($urls as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->admin();
        foreach ([
            '/api/v1/admin/commerce?orders_search[]=invalid',
            '/api/v1/admin/commerce?orders_page=0',
            '/api/v1/admin/commerce/catalog?campaigns_status[]=invalid',
            '/api/v1/admin/commerce/catalog?coupons_status=active',
            '/api/v1/admin/commerce/catalog?per_page=100000',
            '/api/v1/admin/commerce/export?format=html',
        ] as $url) {
            $this->getJson($url)->assertUnprocessable();
        }
    }
}
