<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubPolicyDocument;
use App\Models\File;
use App\Models\User;
use App\Support\ClubPolicyDocumentReadinessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubPolicyDocumentReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_runtime_audit_is_read_only_and_requires_reviewed_evidence(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $document = $this->document($club, 'contribution_model', 'Beitragsmodell', '2026-01-01', null);
        ClubContributionRule::query()->create([
            'club_id' => $club->id,
            'club_policy_document_id' => $document->id,
            'name' => 'Standard',
            'valid_from' => '2026-01-01',
            'billing_interval' => 'yearly',
            'amount' => 120,
            'factor_key' => 'standard',
            'is_active' => true,
        ]);
        $before = [
            'documents' => ClubPolicyDocument::query()->count(),
            'rules' => ClubContributionRule::query()->count(),
        ];

        $report = app(ClubPolicyDocumentReadinessReport::class)->make(true);

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertSame('no-go', $report['decision']);
        $this->assertSame(1, $report['inventory']['linked_contribution_rules']);
        $this->assertSame($before, [
            'documents' => ClubPolicyDocument::query()->count(),
            'rules' => ClubContributionRule::query()->count(),
        ]);
        $this->artisan('airmius:audit-club-policy-documents', [
            '--with-data' => true,
            '--strict' => true,
        ])->assertFailed();
    }

    public function test_runtime_audit_detects_invalid_links_and_overlaps_without_identifiers(): void
    {
        $firstClub = Club::factory()->create(['owner_id' => User::factory()]);
        $secondClub = Club::factory()->create(['owner_id' => User::factory()]);
        $document = $this->document($firstClub, 'contribution_model', 'Intern geheim', '2026-01-01', '2026-12-31');
        $this->document($firstClub, 'contribution_model', 'Intern geheim', '2026-06-01', null);
        ClubContributionRule::query()->create([
            'club_id' => $secondClub->id,
            'club_policy_document_id' => $document->id,
            'name' => 'Nicht ausgeben',
            'valid_from' => '2025-01-01',
            'billing_interval' => 'monthly',
            'amount' => 10,
            'factor_key' => 'standard',
            'is_active' => true,
        ]);

        $report = app(ClubPolicyDocumentReadinessReport::class)->make(true);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame(1, $report['inventory']['invalid_rule_links']);
        $this->assertSame(1, $report['inventory']['overlapping_document_pairs']);
        $this->assertStringNotContainsString('Intern geheim', $encoded);
        $this->assertStringNotContainsString('Nicht ausgeben', $encoded);
        $this->assertStringNotContainsString('club_id', json_encode($report['inventory'], JSON_THROW_ON_ERROR));
    }

    public function test_strict_audit_accepts_only_complete_versioned_evidence(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'policy-document-evidence-');
        file_put_contents($path, json_encode([
            'contract' => ClubPolicyDocumentReadinessReport::CONTRACT,
            'status' => 'passed',
            'migration' => [
                'backup_verified' => true,
                'dry_run_passed' => true,
                'rollback_rehearsed' => true,
            ],
            'surfaces' => array_fill_keys(ClubPolicyDocumentReadinessReport::SURFACES, 'passed'),
            'journeys' => array_fill_keys(ClubPolicyDocumentReadinessReport::JOURNEYS, 'passed'),
            'approvals' => ['product' => true, 'engineering' => true],
            'evidence_references' => ['POLICY-QA-2026-09-24', 'DEVICE-RUN-01'],
        ], JSON_THROW_ON_ERROR));

        try {
            $report = app(ClubPolicyDocumentReadinessReport::class)->make(true, $path);
            $this->assertSame('go', $report['decision']);
            $this->artisan('airmius:audit-club-policy-documents', [
                '--with-data' => true,
                '--evidence' => $path,
                '--strict' => true,
            ])->assertSuccessful();

            file_put_contents($path, str_replace('DEVICE-RUN-01', '/private/device.png', (string) file_get_contents($path)));
            $this->assertSame('no-go', app(ClubPolicyDocumentReadinessReport::class)->make(true, $path)['decision']);
        } finally {
            @unlink($path);
        }
    }

    private function document(Club $club, string $type, string $title, string $from, ?string $until): ClubPolicyDocument
    {
        $file = File::query()->create([
            'club_id' => $club->id,
            'user_id' => $club->owner_id,
            'display_name' => 'policy.pdf',
            'path' => 'test/policy.pdf',
            'type' => 'application/pdf',
            'size' => 3,
        ]);

        return ClubPolicyDocument::query()->create([
            'club_id' => $club->id,
            'file_id' => $file->id,
            'created_by' => $club->owner_id,
            'type' => $type,
            'title' => $title,
            'version_label' => $from,
            'valid_from' => $from,
            'valid_until' => $until,
            'is_public' => false,
        ]);
    }
}
