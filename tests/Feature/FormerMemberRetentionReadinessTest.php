<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\Invoice;
use App\Models\User;
use App\Support\FormerMemberRetentionReadinessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormerMemberRetentionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_report_is_read_only_minimized_and_keeps_deletion_blocked_for_approval(): void
    {
        $owner = User::factory()->create();
        $former = User::factory()->create([
            'name' => 'Former Member Visible Name',
            'email' => 'former-person@example.test',
        ]);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($former->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'former',
            'member_number' => 'M-2026-Former',
            'contribution_amount' => 120,
            'sepa_iban' => 'DE12500105170648489890',
            'sepa_mandate_reference' => 'MANDATE-FORMER',
            'joined_on' => now()->subYear()->toDateString(),
            'membership_ends_on' => now()->subMonth()->toDateString(),
            'membership_ended_at' => now()->subMonth(),
            'membership_notes' => 'Sensitive free-text note',
        ]);
        Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $former->id,
            'number' => 'INV-FORMER-1',
            'title' => 'Former contribution',
            'amount' => 120,
            'status' => 'paid',
            'source' => 'manual',
            'due_date' => now()->subMonths(2),
            'issued_at' => now()->subMonths(2),
        ]);
        ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'External Former Person',
            'email' => 'external-former@example.test',
            'phone' => '+491234567',
            'city' => 'Berlin',
            'membership_status' => 'former',
            'member_number' => 'EXT-1',
            'contribution_amount' => 60,
            'sepa_iban' => 'DE89370400440532013000',
            'sepa_mandate_reference' => 'EXT-MANDATE',
            'joined_on' => now()->subYear()->toDateString(),
            'membership_ended_at' => now()->subMonth(),
            'membership_notes' => 'External note',
        ]);
        $before = [
            'club_user' => DB::table('club_user')->where('membership_status', 'former')->count(),
            'external' => ClubExternalMember::query()->where('membership_status', 'former')->count(),
            'invoices' => Invoice::query()->count(),
        ];

        $report = app(FormerMemberRetentionReadinessReport::class)->make(true);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertTrue($report['read_only']);
        $this->assertFalse($report['productive_erasure_supported']);
        $this->assertTrue($report['requires_domain_approval']);
        $this->assertTrue($report['requires_legal_approval']);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertSame('no-go', $report['decision']);
        $this->assertSame(1, $report['inventory']['linked_former_members']);
        $this->assertSame(1, $report['inventory']['external_former_members']);
        $this->assertSame(['linked' => 1, 'external' => 1], $report['inventory']['category_counts']['payment_mandate']);
        $this->assertSame(['linked' => 1, 'external' => 1], $report['inventory']['category_counts']['free_text_notes']);
        $this->assertSame('pending', collect($report['checks'])->firstWhere('id', 'approval.legal')['status']);
        $this->assertStringNotContainsString('Former Member Visible Name', $encoded);
        $this->assertStringNotContainsString('former-person@example.test', $encoded);
        $this->assertStringNotContainsString('Sensitive free-text note', $encoded);
        $this->assertStringNotContainsString('DE12500105170648489890', $encoded);
        $this->assertStringNotContainsString('club_id', json_encode($report['inventory'], JSON_THROW_ON_ERROR));
        $this->assertSame($before, [
            'club_user' => DB::table('club_user')->where('membership_status', 'former')->count(),
            'external' => ClubExternalMember::query()->where('membership_status', 'former')->count(),
            'invoices' => Invoice::query()->count(),
        ]);
    }
}
