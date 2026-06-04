<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminInvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_admin_can_open_invoice_center(): void
    {
        $admin = User::factory()->create();
        $recipient = User::factory()->create(['name' => 'Invoice Recipient']);
        $this->grantPermissions($admin, ['billing.manage']);

        Invoice::query()->create([
            'user_id' => $recipient->id,
            'number' => 'MAN-2026-0001',
            'title' => 'Website Konzept',
            'amount' => 250,
            'status' => 'open',
            'source' => 'agency_website',
            'due_date' => now()->addDays(14),
            'issued_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Admin/Invoices/Index')
                ->where('summary.count', 1)
                ->where('summary.open', 1)
                ->where('invoices.data.0.number', 'MAN-2026-0001')
                ->where('invoices.data.0.user.name', 'Invoice Recipient')
                ->has('invoiceTypes', 10)
            );
    }

    public function test_billing_admin_can_create_update_and_delete_manual_invoice(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $recipient = User::factory()->create();
        $this->grantPermissions($admin, ['billing.manage']);

        $this->actingAs($admin)
            ->post(route('invoices.store'), [
                'source' => 'custom',
                'club_id' => '',
                'user_id' => $recipient->id,
                'number' => '',
                'title' => 'Individuelle Betreuung',
                'description' => 'Einzelcoaching und Analyse.',
                'amount' => 149.9,
                'status' => 'open',
                'due_date' => now()->addDays(10)->toDateString(),
                'issued_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();

        $this->assertSame('custom', $invoice->source);
        $this->assertSame('Individuelle Betreuung', $invoice->title);
        $this->assertSame('149.90', $invoice->amount);
        $this->assertNotEmpty($invoice->number);

        $this->actingAs($admin)
            ->put(route('invoices.status.update', $invoice), [
                'status' => 'paid',
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $this->actingAs($admin)
            ->delete(route('invoices.destroy', $invoice))
            ->assertRedirect();

        $this->assertDatabaseMissing('invoices', [
            'id' => $invoice->id,
        ]);
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
