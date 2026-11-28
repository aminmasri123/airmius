<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubSepaBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClubSepaMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $owner;

    private User $payer;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 28)->startOfDay());
        $this->owner = User::factory()->create();
        $this->payer = User::factory()->create(['name' => 'Zahlungspflichtige Mutter']);
        $this->club = Club::factory()->create([
            'owner_id' => $this->owner->id, 'sepa_creditor_id' => 'DE98ZZZ09999999999',
            'sepa_iban' => 'DE02120300000000202051',
        ]);
        $this->club->users()->attach($this->payer->id, ['role' => 'member', 'roles' => ['member'], ...$this->mandate()]);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'target_actor' => 'verein', 'is_active' => true]);
        $this->club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => $plan->id, 'status' => 'active']);
        $this->invoice = Invoice::create([
            'club_id' => $this->club->id, 'user_id' => $this->payer->id, 'number' => 'MASTER-1',
            'title' => 'Beitrag', 'amount' => 25, 'status' => 'open', 'due_date' => '2026-12-20',
        ]);
        Sanctum::actingAs($this->owner);
    }

    private function mandate(): array
    {
        return ['sepa_iban' => 'DE12500105170648489890', 'sepa_bic' => null,
            'sepa_mandate_reference' => 'MANDATE-1', 'sepa_mandate_signed_on' => '2026-11-01',
            'sepa_mandate_active' => true, 'payment_method' => 'sepa_debit'];
    }

    private function batchData(): array
    {
        return ['collection_date' => '2026-12-20', 'notice_days' => 14, 'invoice_ids' => [$this->invoice->id]];
    }

    public function test_settings_normalize_valid_data_and_accept_optional_bic(): void
    {
        $this->putJson("/api/v1/clubs/{$this->club->id}/membership/sepa-settings", [
            'sepa_creditor_id' => 'de98 zzz09999999999', 'sepa_account_holder' => 'Testverein',
            'sepa_iban' => 'de02 1203 0000 0000 2020 51', 'sepa_bic' => '',
        ])->assertOk();
        $this->assertSame('DE98ZZZ09999999999', $this->club->fresh()->sepa_creditor_id);
        $this->assertSame('DE02120300000000202051', $this->club->fresh()->sepa_iban);
        $this->assertNull($this->club->fresh()->sepa_bic);
    }

    public function test_invalid_settings_are_rejected_without_overwriting_saved_data(): void
    {
        $this->putJson("/api/v1/clubs/{$this->club->id}/membership/sepa-settings", [
            'sepa_creditor_id' => 'DE97ZZZ09999999999', 'sepa_iban' => 'DE03120300000000202051', 'sepa_bic' => 'INVALID',
        ])->assertUnprocessable()->assertJsonValidationErrors(['sepa_creditor_id', 'sepa_iban', 'sepa_bic']);
        $this->assertSame('DE98ZZZ09999999999', $this->club->fresh()->sepa_creditor_id);
    }

    public function test_active_mandate_requires_complete_data_but_inactive_draft_is_allowed(): void
    {
        $url = "/api/v1/clubs/{$this->club->id}/members/{$this->payer->id}";
        $this->putJson($url, ['membership_status' => 'active', 'sepa_mandate_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['sepa_iban', 'sepa_mandate_reference', 'sepa_mandate_signed_on']);
        $this->putJson($url, ['membership_status' => 'active', 'sepa_mandate_active' => false])->assertOk();
        $this->postJson("/api/v1/clubs/{$this->club->id}/sepa-batches", $this->batchData())->assertUnprocessable();
    }

    public static function invalidMandates(): array
    {
        return [
            'checksum' => ['sepa_iban', 'DE13500105170648489890'],
            'missing iban' => ['sepa_iban', null],
            'bic' => ['sepa_bic', 'INVALID'],
            'missing reference' => ['sepa_mandate_reference', null],
            'long reference' => ['sepa_mandate_reference', str_repeat('A', 36)],
            'invalid reference characters' => ['sepa_mandate_reference', '<Mandat>'],
            'missing date' => ['sepa_mandate_signed_on', null],
            'future date' => ['sepa_mandate_signed_on', '2026-11-29'],
        ];
    }

    #[DataProvider('invalidMandates')]
    public function test_invalid_member_data_is_rejected_on_save_and_in_legacy_records(string $field, mixed $value): void
    {
        $this->putJson("/api/v1/clubs/{$this->club->id}/members/{$this->payer->id}", [
            'membership_status' => 'active', ...$this->mandate(), $field => $value,
        ])->assertUnprocessable()->assertJsonValidationErrors([$field]);
        $this->club->users()->updateExistingPivot($this->payer->id, [$field => $value]);
        $this->postJson("/api/v1/clubs/{$this->club->id}/sepa-batches", $this->batchData())->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_batches', 0);
        $this->getJson("/api/v1/clubs/{$this->club->id}/membership/sepa-export")->assertUnprocessable();
    }

    public function test_missing_or_invalid_creditor_blocks_creation_and_legacy_export(): void
    {
        foreach ([null, 'DE97ZZZ09999999999'] as $creditor) {
            $this->club->update(['sepa_creditor_id' => $creditor]);
            $this->postJson("/api/v1/clubs/{$this->club->id}/sepa-batches", $this->batchData())->assertUnprocessable();
            $this->getJson("/api/v1/clubs/{$this->club->id}/membership/sepa-export")->assertUnprocessable();
        }
        $this->assertDatabaseCount('club_sepa_batches', 0);
    }

    public function test_invoice_payer_mandate_is_used_instead_of_beneficiary_mandate(): void
    {
        $child = User::factory()->create(['name' => 'Kind']);
        $this->club->users()->attach($child->id, ['role' => 'member', 'contribution_payer_user_id' => $this->payer->id]);
        $this->invoice->update(['membership_user_id' => $child->id]);
        $batch = app(ClubSepaBatchService::class)->create($this->club, $this->owner, $this->batchData());
        $this->assertSame('Zahlungspflichtige Mutter', $batch->items->first()->debtor_snapshot['name']);
        $this->assertSame('DE12500105170648489890', $batch->items->first()->debtor_snapshot['sepa_iban']);
    }

    public function test_missing_payer_or_different_payment_method_never_creates_a_batch(): void
    {
        $this->club->users()->updateExistingPivot($this->payer->id, ['payment_method' => 'bank_transfer']);
        $this->postJson("/api/v1/clubs/{$this->club->id}/sepa-batches", $this->batchData())->assertUnprocessable();
        $this->invoice->update(['user_id' => null]);
        $this->postJson("/api/v1/clubs/{$this->club->id}/sepa-batches", $this->batchData())->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_batches', 0);
    }

    public function test_duplicate_active_reference_blocks_collection(): void
    {
        $other = User::factory()->create();
        $this->club->users()->attach($other->id, ['role' => 'member', ...$this->mandate()]);
        $this->postJson("/api/v1/clubs/{$this->club->id}/sepa-batches", $this->batchData())->assertUnprocessable();
        $this->getJson("/api/v1/clubs/{$this->club->id}/membership/sepa-export")->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_batch_items', 0);
    }

    public function test_linked_external_record_is_the_same_payer_not_an_ambiguous_mandate(): void
    {
        ClubExternalMember::create([
            'club_id' => $this->club->id, 'name' => $this->payer->name, 'email' => $this->payer->email,
            'linked_user_id' => $this->payer->id, ...$this->mandate(),
        ]);
        $this->postJson("/api/v1/clubs/{$this->club->id}/sepa-batches", $this->batchData())->assertCreated();
    }

    public function test_plain_member_cannot_change_creditor_settings(): void
    {
        Sanctum::actingAs($this->payer);
        $this->putJson("/api/v1/clubs/{$this->club->id}/membership/sepa-settings", [])->assertForbidden();
    }

    public function test_external_member_mandate_uses_the_same_validation(): void
    {
        $external = ClubExternalMember::create([
            'club_id' => $this->club->id, 'name' => 'Extern', 'email' => 'external@example.test',
            'membership_status' => 'active',
        ]);
        $url = "/api/v1/clubs/{$this->club->id}/external-members/{$external->id}";
        $this->putJson($url, [
            'name' => 'Extern', 'email' => $external->email, 'membership_status' => 'active',
            ...$this->mandate(), 'sepa_bic' => 'BAD',
        ])->assertUnprocessable()->assertJsonValidationErrors(['sepa_bic']);
        $this->putJson($url, [
            'name' => 'Extern', 'email' => $external->email, 'membership_status' => 'active',
            ...$this->mandate(), 'sepa_mandate_reference' => 'EXT-1',
        ])->assertOk();
    }

    public function test_revocation_after_notice_prevents_export_and_preserves_invoice(): void
    {
        $batch = app(ClubSepaBatchService::class)->create($this->club, $this->owner, $this->batchData());
        $batch->update(['status' => 'notified', 'notice_sent_on' => today()]);
        $this->club->users()->updateExistingPivot($this->payer->id, ['sepa_mandate_active' => false]);
        $this->postJson("/api/v1/clubs/{$this->club->id}/sepa-batches/{$batch->id}/export")->assertUnprocessable();
        $this->assertNull($batch->fresh()->export_xml);
        $this->assertNull($this->invoice->fresh()->sepa_exported_at);
        $this->assertSame('open', $this->invoice->fresh()->status);
    }

    public function test_import_cannot_bypass_mandate_validation_or_partially_write_members(): void
    {
        $file = UploadedFile::fake()->createWithContent('members.csv',
            "name,email,iban,sepa_aktiv,mandatsreferenz,mandatsdatum\n"
            ."Valid,valid@example.test,DE89370400440532013000,1,IMPORT-1,2026-11-01\n"
            ."Invalid,invalid@example.test,DE89370400440532013001,1,IMPORT-2,2026-11-01\n");
        $this->postJson("/api/v1/clubs/{$this->club->id}/members/import", ['file' => $file])
            ->assertUnprocessable()->assertJsonValidationErrors(['members.1.sepa_iban']);
        $this->assertDatabaseMissing('club_external_members', ['email' => 'valid@example.test']);
        $this->assertDatabaseMissing('club_external_members', ['email' => 'invalid@example.test']);
    }
}
