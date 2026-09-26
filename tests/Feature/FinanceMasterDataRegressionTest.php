<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingAccount;
use App\Models\ClubBusinessPartner;
use App\Models\ClubCostCenter;
use App\Models\ClubDepartment;
use App\Models\ClubFinanceAssignment;
use App\Models\ClubProject;
use App\Models\ClubYearPeriod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceMasterDataRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_master_data_is_tenant_scoped_and_historized_on_entries(): void
    {
        [$club, $owner] = $this->clubWithFinanceAccess();
        [$foreignClub] = $this->clubWithFinanceAccess();

        $period = ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'business',
            'name' => 'Wirtschaftsjahr 2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $partner = ClubBusinessPartner::query()->create([
            'club_id' => $club->id,
            'type' => 'supplier',
            'number' => 'K-1001',
            'name' => 'Sporthalle Nord GmbH',
        ]);
        $account = ClubAccountingAccount::query()->create([
            'club_id' => $club->id,
            'code' => '4210',
            'name' => 'Mieten Sportanlagen',
            'type' => 'expense',
            'datev_code' => '4210',
        ]);
        $costCenter = ClubCostCenter::query()->create([
            'club_id' => $club->id,
            'code' => 'HANDBALL',
            'name' => 'Handball',
        ]);
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Handball',
            'sport_type' => 'handball',
            'is_public' => true,
        ]);
        $project = ClubProject::query()->create([
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'code' => 'CAMP-2026',
            'name' => 'Sommercamp 2026',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
            'status' => 'active',
        ]);
        $foreignCostCenter = ClubCostCenter::query()->create([
            'club_id' => $foreignClub->id,
            'code' => 'FOREIGN',
            'name' => 'Fremdverein',
        ]);

        $entryId = DB::table('club_finance_entries')->insertGetId([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'business_year_period_id' => $period->id,
            'club_business_partner_id' => $partner->id,
            'club_accounting_account_id' => $account->id,
            'club_cost_center_id' => $costCenter->id,
            'club_project_id' => $project->id,
            'club_department_id' => $department->id,
            'type' => 'expense',
            'account' => 'bank',
            'category' => 'facility',
            'title' => 'Hallenmiete Sommercamp',
            'amount' => '250.00',
            'booked_on' => '2026-07-10',
            'reference' => 'AP-2026-0042',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ClubFinanceAssignment::query()->create([
            'club_id' => $club->id,
            'assignable_type' => 'club_finance_entry',
            'assignable_id' => $entryId,
            'club_business_partner_id' => $partner->id,
            'club_accounting_account_id' => $account->id,
            'club_cost_center_id' => $costCenter->id,
            'club_project_id' => $project->id,
            'club_department_id' => $department->id,
            'club_year_period_id' => $period->id,
            'valid_from' => '2026-07-01',
            'valid_until' => '2026-07-31',
            'snapshot' => ['reason' => 'Sommercamp allocation'],
        ]);

        $this->assertDatabaseHas('club_finance_entries', [
            'id' => $entryId,
            'club_id' => $club->id,
            'business_year_period_id' => $period->id,
            'club_business_partner_id' => $partner->id,
            'club_accounting_account_id' => $account->id,
            'club_cost_center_id' => $costCenter->id,
            'club_project_id' => $project->id,
            'club_department_id' => $department->id,
        ]);
        $this->assertSame(1, ClubFinanceAssignment::query()->where('assignable_id', $entryId)->effectiveOn('2026-07-10')->count());
        $this->assertSame(0, ClubFinanceAssignment::query()->where('assignable_id', $entryId)->effectiveOn('2026-08-01')->count());
        $this->assertNotSame($club->id, $foreignCostCenter->club_id);
    }

    private function clubWithFinanceAccess(): array
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $plan = SubscriptionPlan::query()->firstOrCreate(['slug' => 'pro'], [
            'target_actor' => 'verein',
            'name' => 'Pro',
            'monthly_price_cents' => 2990,
            'yearly_price_cents' => 29900,
            'currency' => 'EUR',
            'features' => [],
            'sort_order' => 1,
            'is_public' => true,
            'is_active' => true,
        ]);

        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);

        return [$club, $owner];
    }
}
