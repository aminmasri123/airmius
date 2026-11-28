<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubAccountingExportTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 28)->startOfDay());
        $owner = User::factory()->create();
        $this->club = Club::factory()->create(['owner_id' => $owner->id]);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'target_actor' => 'verein', 'is_active' => true]);
        $this->club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => $plan->id, 'status' => 'active']);
        Sanctum::actingAs($owner);
    }

    private function path(string $action = 'datev-export'): string
    {
        return "/api/v1/clubs/{$this->club->id}/membership/{$action}";
    }

    public function test_invalid_accounts_and_effective_period_are_rejected(): void
    {
        foreach (['=1+1', '000', '01200'] as $account) {
            $this->putJson($this->path('datev-settings'), ['datev_revenue_account' => $account, 'datev_bank_account' => '1200'])
                ->assertUnprocessable()->assertJsonValidationErrors('datev_revenue_account');
        }
        foreach (['?from=2026-12-01', '?to=2026-10-01', '?from=2026-02-30', '?from=2026-11-02&to=2026-11-01'] as $query) {
            $this->getJson($this->path().$query)->assertUnprocessable();
        }
    }

    public function test_csv_preserves_utf8_cents_boundaries_and_escapes_references(): void
    {
        foreach (['2026-11-01 00:00:00', '2026-11-28 23:59:59', '2026-10-31 23:59:59'] as $date) {
            Payment::create(['club_id' => $this->club->id, 'amount' => '12.34', 'status' => 'paid', 'paid_at' => $date, 'reference' => '=Ä;"Test"']);
        }
        Payment::create(['club_id' => Club::factory()->create()->id, 'amount' => 999, 'status' => 'paid', 'paid_at' => now()]);
        Payment::create(['club_id' => $this->club->id, 'amount' => 888, 'status' => 'pending', 'paid_at' => now()]);
        $response = $this->get($this->path().'?from=2026-11-01&to=2026-11-28')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = array_map(fn ($line) => str_getcsv($line, ';', '"', ''), explode("\r\n", trim(substr($csv, 3))));
        $this->assertCount(3, $rows);
        $this->assertCount(14, $rows[1]);
        $this->assertSame(['12,34', 'S', 'EUR', '1200', '2110'], array_slice($rows[1], 0, 5));
        $this->assertSame("'=Ä;\"Test\"", $rows[1][7]);
    }

    public function test_inconsistent_invoice_club_is_rejected(): void
    {
        $invoice = Invoice::create(['club_id' => Club::factory()->create()->id, 'number' => 'SECRET', 'title' => 'Other club', 'amount' => 10, 'status' => 'paid', 'due_date' => '2026-11-28']);
        Payment::create(['club_id' => $this->club->id, 'invoice_id' => $invoice->id, 'amount' => 10, 'status' => 'paid', 'paid_at' => now()]);
        $this->getJson($this->path())->assertUnprocessable()->assertDontSee('SECRET');
    }

    public function test_empty_and_nonpositive_exports_are_rejected(): void
    {
        $this->getJson($this->path())->assertUnprocessable();
        Payment::create(['club_id' => $this->club->id, 'amount' => -10, 'status' => 'paid', 'paid_at' => now()]);
        $this->getJson($this->path())->assertUnprocessable();
    }

    public function test_member_cannot_export_or_change_settings(): void
    {
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member']);
        Sanctum::actingAs($member);
        foreach (['datev-export', 'sepa-export'] as $action) {
            $this->getJson($this->path($action))->assertForbidden();
        }
        $this->putJson($this->path('datev-settings'), ['datev_bank_account' => '1300'])->assertForbidden();
    }
}
