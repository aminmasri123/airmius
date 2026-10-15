<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\File;
use App\Models\User;
use App\Services\ClubFinancialClosingExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ClubFinancialClosingExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_contains_only_this_clubs_finance_records_and_receipts(): void
    {
        Storage::fake('public');
        config(['filesystems.uploads_disk' => 'public']);
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $other = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $receipt = File::create([
            'club_id' => $club->id, 'user_id' => $owner->id,
            'path' => 'receipts/own.pdf', 'type' => 'application/pdf', 'size' => 3,
        ]);
        Storage::disk('public')->put('receipts/own.pdf', 'own');
        $foreignReceipt = File::create([
            'club_id' => $other->id, 'user_id' => $owner->id,
            'path' => 'receipts/foreign.pdf', 'type' => 'application/pdf', 'size' => 7,
        ]);
        Storage::disk('public')->put('receipts/foreign.pdf', 'foreign');
        ClubFinanceEntry::create([
            'club_id' => $club->id, 'receipt_file_id' => $receipt->id,
            'booked_on' => today(), 'type' => 'income', 'category' => 'other',
            'title' => 'Own entry', 'amount' => 25, 'account' => 'cash',
        ]);
        ClubFinanceEntry::create([
            'club_id' => $other->id, 'booked_on' => today(), 'type' => 'income',
            'category' => 'other', 'title' => 'Foreign entry', 'amount' => 50, 'account' => 'cash',
        ]);
        ClubFinanceEntry::create([
            'club_id' => $club->id, 'receipt_file_id' => $foreignReceipt->id,
            'booked_on' => today(), 'type' => 'expense', 'category' => 'other',
            'title' => 'Wrongly linked receipt', 'amount' => 5, 'account' => 'cash',
        ]);

        $path = app(ClubFinancialClosingExportService::class)->create($club);
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $entries = $zip->getFromName('data/club_finance_entries.jsonl');
            $this->assertStringContainsString('Own entry', $entries);
            $this->assertStringNotContainsString('Foreign entry', $entries);
            $this->assertSame('own', $zip->getFromName('receipts/'.$receipt->id.'-own.pdf'));
            $this->assertFalse($zip->getFromName('receipts/'.$foreignReceipt->id.'-foreign.pdf'));
            $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($club->id, $manifest['club_id']);
            $this->assertSame(2, $manifest['record_counts']['club_finance_entries']);
            $this->assertContains($foreignReceipt->id, $manifest['missing_receipt_file_ids']);
            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_only_club_owner_can_download_financial_closing_package(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs(User::factory()->create())
            ->get(route('auth.clubs.deletion.financial-export', $club))->assertForbidden();
        $this->actingAs($owner)
            ->get(route('auth.clubs.deletion.financial-export', $club))->assertOk();
    }

    public function test_incomplete_receipt_export_cannot_start_financial_review(): void
    {
        Storage::fake('public');
        config(['filesystems.uploads_disk' => 'public']);
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $file = File::create([
            'club_id' => $club->id, 'user_id' => $owner->id,
            'path' => 'receipts/missing.pdf', 'type' => 'application/pdf',
        ]);
        ClubFinanceEntry::create([
            'club_id' => $club->id, 'receipt_file_id' => $file->id,
            'booked_on' => today(), 'type' => 'expense', 'category' => 'other',
            'title' => 'Missing receipt', 'amount' => 5, 'account' => 'cash',
        ]);
        $this->actingAs($owner)->get(route('auth.clubs.deletion.financial-export', $club))
            ->assertOk()->assertHeader('X-Airmius-Export-Complete', 'false');
        $this->assertDatabaseCount('club_deletion_financial_reviews', 0);
        $this->postJson(route('auth.clubs.deletion.archive-confirmation', $club), ['archive_confirmed' => true])
            ->assertUnprocessable();
    }

    public function test_export_includes_sepa_history_and_uploaded_receipts_without_cross_club_leakage(): void
    {
        Storage::fake('public');
        config(['filesystems.uploads_disk' => 'public']);
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreign = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $ids = [];

        foreach ([$club, $foreign] as $itemClub) {
            $tag = $itemClub->id === $club->id ? 'own' : 'foreign';
            $invoiceId = DB::table('invoices')->insertGetId([
                'club_id' => $itemClub->id, 'number' => 'INV-'.$tag,
                'amount' => 10, 'due_date' => now(),
            ]);
            $batchId = DB::table('club_sepa_batches')->insertGetId([
                'club_id' => $itemClub->id, 'reference' => 'BATCH-'.$tag,
                'collection_date' => today(), 'notice_days' => 14,
                'creditor_snapshot' => '{}',
            ]);
            $itemId = DB::table('club_sepa_batch_items')->insertGetId([
                'club_sepa_batch_id' => $batchId, 'invoice_id' => $invoiceId,
                'amount_cents' => 1000, 'debtor_snapshot' => '{"name":"'.$tag.'"}',
            ]);
            $noticeId = DB::table('club_sepa_notices')->insertGetId([
                'club_sepa_batch_id' => $batchId, 'club_sepa_batch_item_id' => $itemId,
                'content' => 'notice-'.$tag,
            ]);
            DB::table('club_sepa_notice_provider_events')->insert([
                'club_sepa_notice_id' => $noticeId, 'provider' => 'test',
                'event_key' => 'event-'.$tag, 'event_type' => 'delivered',
                'occurred_at' => now(),
            ]);
            $file = File::create([
                'club_id' => $itemClub->id, 'user_id' => $owner->id,
                'path' => 'receipts/'.$tag.'.pdf', 'type' => 'application/pdf',
            ]);
            Storage::disk('public')->put('receipts/'.$tag.'.pdf', $tag);
            DB::table('club_receipt_uploads')->insert([
                'club_id' => $itemClub->id, 'file_id' => $file->id,
                'sha256' => hash('sha256', $tag), 'mime_type' => 'application/pdf',
                'size_bytes' => strlen($tag),
            ]);
            $ids[$tag] = [
                'club_sepa_batches' => $batchId,
                'club_sepa_batch_items' => $itemId,
                'club_sepa_notices' => $noticeId,
                'club_sepa_notice_provider_events' => DB::table('club_sepa_notice_provider_events')
                    ->where('event_key', 'event-'.$tag)->value('id'),
                'club_receipt_uploads' => DB::table('club_receipt_uploads')
                    ->where('file_id', $file->id)->value('id'),
                'file' => $file->id,
            ];
        }

        $path = app(ClubFinancialClosingExportService::class)->create($club);
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            foreach (['club_sepa_batches', 'club_sepa_batch_items', 'club_sepa_notices',
                'club_sepa_notice_provider_events', 'club_receipt_uploads'] as $table) {
                $content = $zip->getFromName('data/'.$table.'.jsonl');
                $this->assertIsString($content);
                $row = json_decode(trim($content), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame($ids['own'][$table], $row['id']);
                $this->assertNotSame($ids['foreign'][$table], $row['id']);
            }
            $this->assertSame('own', $zip->getFromName('receipts/'.$ids['own']['file'].'-own.pdf'));
            $this->assertFalse($zip->getFromName('receipts/'.$ids['foreign']['file'].'-foreign.pdf'));
            $zip->close();
        } finally {
            @unlink($path);
        }
    }
}
