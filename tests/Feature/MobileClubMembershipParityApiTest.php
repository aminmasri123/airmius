<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubFinanceEntry;
use App\Models\ClubReceiptUpload;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\ExternalClubMembershipInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.subscription.storage_bytes', 0)
            ->assertJsonPath('data.management.subscription.storage_bytes', 0);

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

    public function test_owner_can_complete_uc23_member_data_and_audit_every_changed_field(): void
    {
        [$owner, $club, $member] = $this->managedClub();
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'member_number' => 'UC23-M-001',
            'athlete_license_number' => 'UC23-L-001',
            'athlete_license_valid_until' => '2026-12-31',
            'contribution_amount' => 31.50,
            'contribution_interval' => 'monthly',
            'contribution_next_invoice_on' => '2026-09-15',
            'joined_on' => '2026-08-22',
            'membership_notes' => 'Interne UC23 Testnotiz',
            'sepa_mandate_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.members', fn (array $members) => collect($members)->contains(
                fn (array $item) => $item['id'] === $member->id
                    && $item['athlete_license_number'] === 'UC23-L-001'
                    && $item['athlete_license_valid_until'] === '2026-12-31'
                    && data_get($item, 'membership.status') === 'active'
                    && data_get($item, 'membership.member_number') === 'UC23-M-001'
                    && data_get($item, 'membership.contribution_interval') === 'monthly'
                    && data_get($item, 'membership.contribution_next_invoice_on') === '2026-09-15'
                    && data_get($item, 'membership.joined_on') === '2026-08-22'
                    && data_get($item, 'membership.membership_notes') === 'Interne UC23 Testnotiz'
            ));

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.management.members', fn (array $members) => collect($members)->contains(
                fn (array $item) => $item['id'] === $member->id
                    && $item['email'] === $member->email
                    && $item['athlete_license_number'] === 'UC23-L-001'
                    && $item['athlete_license_valid_until'] === '2026-12-31'
                    && data_get($item, 'membership.membership_notes') === 'Interne UC23 Testnotiz'
            ));

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'member_number' => 'UC23-M-001',
            'contribution_amount' => 31.50,
            'contribution_interval' => 'monthly',
            'contribution_next_invoice_on' => '2026-09-15',
            'joined_on' => '2026-08-22',
            'membership_notes' => 'Interne UC23 Testnotiz',
        ]);
        $this->assertSame('UC23-L-001', $member->fresh()->athlete_license_number);
        $this->assertSame('2026-12-31', $member->fresh()->athlete_license_valid_until?->toDateString());

        $audit = Activity::query()->where('type', 'club.member.updated')->latest('id')->firstOrFail();
        $this->assertSame($owner->id, $audit->user_id);
        $this->assertSame($member->id, $audit->subject_id);
        $this->assertEqualsCanonicalizing([
            'member_number',
            'athlete_license_number',
            'athlete_license_valid_until',
            'contribution_amount',
            'contribution_interval',
            'contribution_next_invoice_on',
            'joined_on',
            'membership_notes',
        ], $audit->data['changed_fields']);
        $this->assertSame(['from' => false, 'to' => true], $audit->data['changes']['membership_notes']);
        $this->assertStringNotContainsString('Interne UC23 Testnotiz', json_encode($audit->data, JSON_THROW_ON_ERROR));

        Sanctum::actingAs($member);
        $memberView = $this->getJson("/api/v1/clubs/{$club->id}")->assertOk();
        $this->assertStringNotContainsString('Interne UC23 Testnotiz', $memberView->getContent());
        $memberView->assertJsonMissing(['membership_notes' => 'Interne UC23 Testnotiz']);
    }

    public function test_owner_can_import_members_and_download_accounting_exports(): void
    {
        [$owner, $club, $member] = $this->managedClub();
        Sanctum::actingAs($owner);

        $csv = implode("\n", [
            'name,email,mitgliedschaft,mitgliedsnummer,beitrag,intervall',
            'Neue Person,neu@example.test,active,UC22-001,"19,00",monthly',
            'Zweite Person,zwei@example.test,paused,UC22-002,"24,50",quarterly',
            'Fehlerhafte Person,ungueltig,active,UC22-003,"12,00",monthly',
        ]);

        $this->post(
            "/api/v1/clubs/{$club->id}/members/import",
            [
                'file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
                'send_invitation' => true,
            ],
            ['Accept' => 'application/json'],
        )
            ->assertCreated()
            ->assertJsonFragment([
                'email' => 'neu@example.test',
                'member_number' => 'UC22-001',
                'membership_status' => 'active',
                'contribution_interval' => 'monthly',
                'invitation_status' => 'pending',
            ])
            ->assertJsonFragment([
                'email' => 'zwei@example.test',
                'member_number' => 'UC22-002',
                'membership_status' => 'paused',
                'contribution_interval' => 'quarterly',
                'invitation_status' => 'pending',
            ]);

        $this->assertSame(2, ClubExternalMember::query()->where('club_id', $club->id)->count());
        Notification::assertSentOnDemandTimes(ExternalClubMembershipInvitation::class, 2);

        $this->get('/api/v1/club-members/import-template', ['Accept' => 'application/json'])
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

    public function test_uc26_bank_preview_is_non_mutating_and_import_requires_manual_confirmation(): void
    {
        [$owner, $club, $member] = $this->managedClub();
        Sanctum::actingAs($owner);
        $invoice = Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => 'UC26-BANK-001',
            'title' => 'UC26 Bankabgleich',
            'amount' => 27.40,
            'status' => 'open',
            'source' => 'manual',
            'due_date' => now()->addWeek(),
            'issued_at' => now(),
        ]);
        $csv = implode("\n", [
            'Datum;Betrag;Währung;Auftraggeber;IBAN;Verwendungszweck',
            '22.08.2026;27,40;EUR;UC26 Testperson;DE12500105170648489890;UC26-BANK-001',
            'kein-datum;-5,00;EUR;Fehlerzeile;DE00000000000000000000;Ungültig',
            'kein-datum;5,00;EUR;Fehlerdatum;DE00000000000000000000;Datum fehlt',
        ]);

        $preview = $this->post(
            "/api/v1/clubs/{$club->id}/bank-transactions/preview",
            ['file' => UploadedFile::fake()->createWithContent('uc26-bank.csv', $csv)],
            ['Accept' => 'application/json'],
        )->assertOk()
            ->assertJsonPath('data.can_import', true)
            ->assertJsonPath('data.stats.total', 3)
            ->assertJsonPath('data.stats.importable', 1)
            ->assertJsonPath('data.stats.matched', 1)
            ->assertJsonPath('data.stats.invalid', 2)
            ->assertJsonPath('data.rows.0.invoice.id', $invoice->id)
            ->assertJsonPath('data.rows.0.currency', 'EUR')
            ->assertJsonPath('data.rows.0.debtor_iban_masked', '•••• 9890')
            ->assertJsonMissingPath('data.rows.0.debtor_iban');

        $this->assertSame('matched', $preview->json('data.rows.0.status'));
        $this->assertDatabaseCount('bank_transactions', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('open', $invoice->fresh()->status);

        $this->post(
            "/api/v1/clubs/{$club->id}/bank-transactions/import",
            ['file' => UploadedFile::fake()->createWithContent('uc26-bank.csv', $csv)],
            ['Accept' => 'application/json'],
        )->assertCreated()
            ->assertJsonPath('data.bank_transactions.0.status', 'suggested')
            ->assertJsonPath('data.bank_transactions.0.match_confidence', 100)
            ->assertJsonPath('data.bank_transactions.0.invoice.id', $invoice->id)
            ->assertJsonPath('data.invoices.0.status', 'open');

        $this->assertDatabaseCount('bank_transactions', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('open', $invoice->fresh()->status);
        $transaction = BankTransaction::query()->firstOrFail();
        $this->assertSame('suggested', $transaction->status);
        $this->assertNull($transaction->payment_id);
        $this->assertSame(100, $transaction->match_confidence);
        $this->assertSame($invoice->id, $transaction->invoice_id);

        $this->postJson("/api/v1/clubs/{$club->id}/bank-transactions/{$transaction->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.bank_transactions.0.status', 'matched')
            ->assertJsonPath('data.invoices.0.status', 'paid');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.payment.recorded',
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
        ]);
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

    public function test_mobile_receipt_upload_requires_clean_file_and_manual_booking_confirmation(): void
    {
        Storage::fake(\App\Support\UploadStorage::disk());
        [$owner, $club] = $this->managedClub();
        Sanctum::actingAs($owner);

        $uploadId = $this->postJson("/api/v1/clubs/{$club->id}/receipt-uploads", [
            'file' => UploadedFile::fake()->create('2026-09-26_hallenmiete_42,50.pdf', 12, 'application/pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.receipt_upload.status', 'pending_confirmation')
            ->assertJsonPath('data.receipt_upload.scan_status', 'clean')
            ->assertJsonPath('data.receipt_upload.ocr_suggestion.requires_manual_confirmation', true)
            ->json('data.receipt_upload.id');

        $this->assertSame(0, ClubFinanceEntry::query()->where('club_id', $club->id)->count());

        $this->postJson("/api/v1/clubs/{$club->id}/receipt-uploads/{$uploadId}/confirm", [
            'type' => 'expense',
            'account' => 'bank',
            'category' => 'Halle',
            'title' => 'Hallenmiete',
            'amount' => 42.50,
            'booked_on' => '2026-09-26',
            'reference' => 'R-2026-09',
            'description' => 'Manuell geprüft',
        ])
            ->assertOk()
            ->assertJsonPath('data.receipt_upload.status', 'confirmed')
            ->assertJsonPath('data.management.receipt_uploads.0.status', 'confirmed')
            ->assertJsonPath('data.management.finance_entries.0.receipt_file.display_name', '2026-09-26_hallenmiete_42,50.pdf');

        $entry = ClubFinanceEntry::query()->where('club_id', $club->id)->firstOrFail();
        $this->assertSame('Hallenmiete', $entry->title);
        $this->assertNotNull($entry->receipt_file_id);

        $receipt = ClubReceiptUpload::query()->firstOrFail();
        Storage::disk(\App\Support\UploadStorage::disk())->assertExists($receipt->file->path);

        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.receipt.uploaded',
        ]);
        $this->assertDatabaseMissing('activities', [
            'club_id' => $club->id,
            'description' => 'Hallenmiete',
        ]);
    }

    public function test_receipt_upload_blocks_malware_signature_and_cross_club_assignment(): void
    {
        Storage::fake(\App\Support\UploadStorage::disk());
        [$owner, $club] = $this->managedClub();
        $otherClub = Club::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/receipt-uploads", [
            'file' => UploadedFile::fake()->createWithContent(
                'rechnung.pdf',
                'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'
            ),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $uploadId = $this->postJson("/api/v1/clubs/{$club->id}/receipt-uploads", [
            'file' => UploadedFile::fake()->create('rechnung.pdf', 8, 'application/pdf'),
        ])
            ->assertCreated()
            ->json('data.receipt_upload.id');

        $foreignEntry = ClubFinanceEntry::query()->create([
            'club_id' => $otherClub->id,
            'user_id' => $owner->id,
            'type' => 'expense',
            'account' => 'bank',
            'title' => 'Fremder Verein',
            'amount' => 10,
            'booked_on' => '2026-09-26',
        ]);

        $this->postJson("/api/v1/clubs/{$club->id}/receipt-uploads/{$uploadId}/confirm", [
            'finance_entry_id' => $foreignEntry->id,
        ])->assertNotFound();

        $this->assertNull($foreignEntry->fresh()->receipt_file_id);
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
