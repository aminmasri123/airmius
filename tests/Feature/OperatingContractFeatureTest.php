<?php

namespace Tests\Feature;

use App\Models\OperatingContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OperatingContractFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_viewer_can_open_operating_contracts_but_not_manage_them(): void
    {
        $viewer = User::factory()->create();
        $this->grantPermissions($viewer, ['finance.view']);

        OperatingContract::query()->create([
            'name' => 'Hosting Vertrag',
            'vendor' => 'Hoster AG',
            'category' => 'hosting',
            'status' => 'active',
            'amount' => 120,
            'currency' => 'EUR',
            'billing_interval' => 'monthly',
            'payment_method' => 'direct_debit',
            'next_due_on' => now()->addDays(10)->toDateString(),
            'notice_until_on' => now()->addDays(30)->toDateString(),
        ]);

        $this->actingAs($viewer)
            ->get(route('admin.operating-contracts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Admin/OperatingContracts/Index')
                ->where('canManage', false)
                ->where('summary.active_count', 1)
                ->where('summary.due_soon_count', 1)
                ->has('contracts.data', 1)
                ->where('contracts.data.0.name', 'Hosting Vertrag')
                ->where('contracts.data.0.update_url', null)
                ->where('contracts.data.0.delete_url', null)
            );

        $this->actingAs($viewer)
            ->post(route('admin.operating-contracts.store'), $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseCount('operating_contracts', 1);
    }

    public function test_finance_manager_can_create_update_and_delete_operating_contracts(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $this->grantPermissions($admin, ['finance.edit']);

        $this->actingAs($admin)
            ->post(route('admin.operating-contracts.store'), $this->validPayload([
                'owner_user_id' => $owner->id,
                'name' => 'Mobilfunk Team',
                'amount' => 49.9,
                'ends_on' => now()->addYear()->toDateString(),
                'cancellation_period_days' => 30,
                'notice_until_on' => '',
            ]))
            ->assertRedirect();

        $contract = OperatingContract::query()->firstOrFail();

        $this->assertSame($admin->id, $contract->created_by);
        $this->assertSame($admin->id, $contract->updated_by);
        $this->assertSame($owner->id, $contract->owner_user_id);
        $this->assertSame(now()->addYear()->subDays(30)->toDateString(), $contract->notice_until_on->toDateString());

        $this->actingAs($admin)
            ->put(route('admin.operating-contracts.update', $contract), $this->validPayload([
                'name' => 'Mobilfunk Team Plus',
                'amount' => 59.9,
                'status' => 'paused',
                'auto_renews' => false,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('operating_contracts', [
            'id' => $contract->id,
            'name' => 'Mobilfunk Team Plus',
            'status' => 'paused',
            'amount' => 59.9,
            'auto_renews' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.operating-contracts.destroy', $contract))
            ->assertRedirect();

        $this->assertDatabaseMissing('operating_contracts', [
            'id' => $contract->id,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'owner_user_id' => '',
            'name' => 'WLAN Buero',
            'vendor' => 'Telekom',
            'category' => 'telecom',
            'status' => 'active',
            'amount' => 89.9,
            'currency' => 'EUR',
            'billing_interval' => 'monthly',
            'payment_method' => 'direct_debit',
            'next_due_on' => now()->addMonth()->toDateString(),
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => '',
            'notice_until_on' => '',
            'cancellation_period_days' => '',
            'auto_renews' => true,
            'contract_number' => 'AIR-1000',
            'account_reference' => 'K-123',
            'contact_email' => 'billing@example.test',
            'website' => 'https://example.test',
            'document_url' => 'contracts/wlan.pdf',
            'notes' => 'Testvertrag',
        ], $overrides);
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
