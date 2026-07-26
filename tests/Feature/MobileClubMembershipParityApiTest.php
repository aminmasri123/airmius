<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileClubMembershipParityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
    }

    public function test_owner_can_use_member_invoice_and_finance_parity_actions(): void
    {
        [$owner, $club, $member] = $this->managedClub();
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'member_number' => 'M-2042',
            'contribution_amount' => 24.50,
            'contribution_interval' => 'monthly',
            'payment_method' => 'bank_transfer',
            'sepa_mandate_active' => false,
        ])
            ->assertOk()
            ->assertJsonFragment(['member_number' => 'M-2042']);

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/invoices", [
            'title' => 'Monatsbeitrag',
            'amount' => 24.50,
            'due_date' => now()->addWeek()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.invoices.0.title', 'Monatsbeitrag');

        $invoice = Invoice::query()->where('club_id', $club->id)->firstOrFail();

        $this->putJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/status", [
            'status' => 'overdue',
        ])
            ->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'overdue');

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/reminder")
            ->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'overdue');

        $this->putJson("/api/v1/clubs/{$club->id}/membership/sepa-settings", [
            'sepa_creditor_id' => 'DE98ZZZ09999999999',
            'sepa_account_holder' => 'Airmius Club',
            'sepa_iban' => 'DE02120300000000202051',
            'sepa_bic' => 'BYLADEM1001',
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.sepa_creditor_id', 'DE98ZZZ09999999999');

        $this->putJson("/api/v1/clubs/{$club->id}/membership/datev-settings", [
            'datev_consultant_number' => '12345',
            'datev_client_number' => '67890',
            'datev_revenue_account' => '2110',
            'datev_bank_account' => '1200',
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.datev_client_number', '67890');

        $transaction = BankTransaction::query()->create([
            'club_id' => $club->id,
            'invoice_id' => $invoice->id,
            'imported_by' => $owner->id,
            'transaction_hash' => hash('sha256', 'mobile-parity-bank-entry'),
            'booking_date' => now()->toDateString(),
            'amount' => 24.50,
            'currency' => 'EUR',
            'debtor_name' => $member->name,
            'purpose' => $invoice->number,
            'status' => 'suggested',
            'match_confidence' => 75,
        ]);

        $this->postJson("/api/v1/clubs/{$club->id}/bank-transactions/{$transaction->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.bank_transactions.0.status', 'matched');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($transaction->fresh()->payment_id);
    }

    public function test_owner_can_import_members_and_download_accounting_exports(): void
    {
        [$owner, $club, $member] = $this->managedClub();
        Sanctum::actingAs($owner);

        $csv = "name,email,mitgliedschaft\nNeue Person,neu@example.test,active\n";

        $this->post(
            "/api/v1/clubs/{$club->id}/members/import",
            ['file' => UploadedFile::fake()->createWithContent('members.csv', $csv)],
            ['Accept' => 'application/json'],
        )
            ->assertCreated()
            ->assertJsonPath('data.external_members.0.email', 'neu@example.test');

        $this->get("/api/v1/club-members/import-template", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('content-disposition');

        $club->update([
            'sepa_creditor_id' => 'DE98ZZZ09999999999',
            'sepa_account_holder' => 'Airmius Club',
            'sepa_iban' => 'DE02120300000000202051',
            'sepa_bic' => 'BYLADEM1001',
        ]);
        $club->users()->updateExistingPivot($member->id, [
            'sepa_iban' => 'DE12500105170648489890',
            'sepa_bic' => 'INGDDEFFXXX',
            'sepa_mandate_reference' => 'MANDAT-42',
            'sepa_mandate_signed_on' => now()->subMonth()->toDateString(),
            'sepa_mandate_active' => true,
        ]);
        Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => 'INV-SEPA-42',
            'title' => 'SEPA Beitrag',
            'amount' => 19.90,
            'status' => 'open',
            'source' => 'manual',
            'due_date' => now()->addWeek(),
            'issued_at' => now(),
        ]);

        $this->get("/api/v1/clubs/{$club->id}/membership/sepa-export", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('INV-SEPA-42');
    }

    public function test_member_self_service_and_cross_club_boundaries_are_enforced(): void
    {
        [$owner, $club, $member] = $this->managedClub();
        $club->update(['member_pause_requests_enabled' => true]);

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.is_member', true)
            ->assertJsonPath('data.membership.status', 'active')
            ->assertJsonPath('data.membership.pause_requested', false)
            ->assertJsonPath('data.member_pause_requests_enabled', true);

        $this->postJson("/api/v1/clubs/{$club->id}/pause-requests", [
            'requested_pause_from' => now()->addWeek()->toDateString(),
            'requested_pause_until' => now()->addMonth()->toDateString(),
            'message' => 'Verletzungspause',
        ])->assertCreated();

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.membership.pause_requested', true);

        $this->postJson("/api/v1/clubs/{$club->id}/leave")
            ->assertOk()
            ->assertJsonPath('message', 'Du hast den Verein verlassen.');
        $this->assertDatabaseMissing('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
        ]);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$owner->id}", [
            'membership_status' => 'active',
        ])->assertForbidden();

        $otherOwner = User::factory()->create();
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $otherMember = User::factory()->create();
        $otherClub->users()->syncWithoutDetaching([
            $otherMember->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ],
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}")
            ->assertNotFound();
    }

    public function test_only_authorized_users_can_delete_a_club_through_mobile_api(): void
    {
        [$owner, $club, $member] = $this->managedClub();

        Sanctum::actingAs($member);
        $this->deleteJson("/api/v1/clubs/{$club->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Verein gelöscht.');
        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
    }

    private function managedClub(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Mobile Parity Club',
        ]);

        $club->users()->syncWithoutDetaching([
            $member->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'joined_on' => now()->subYear()->toDateString(),
            ],
        ]);

        $elitePlan = SubscriptionPlan::query()->where('slug', 'elite')->firstOrFail();
        $club->currentSubscription()->update([
            'subscription_plan_id' => $elitePlan->id,
            'status' => 'active',
        ]);
        $club->unsetRelation('currentSubscription');

        return [$owner, $club, $member];
    }
}
