<?php

namespace Tests\Feature;

use App\Support\MultiClubPlatformIsolationReport;
use Tests\TestCase;

class MultiClubPlatformIsolationContractTest extends TestCase
{
    public function test_meta_exposes_complete_multi_club_isolation_domains_without_external_security_claim(): void
    {
        $report = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.multi_club_isolation');

        $this->assertSame(MultiClubPlatformIsolationReport::VERSION, $report['version']);
        $this->assertSame('local_contract_ready_external_security_gates_open', $report['decision']);
        $this->assertTrue($report['public_internal_split']['public_projection_only']);
        $this->assertTrue($report['public_internal_split']['private_contact_billing_and_notes_excluded']);
        $this->assertSame('pending', $report['external_gates']['independent_security_review']);

        $domains = collect($report['domains'])->keyBy('key');
        foreach (['models', 'files', 'search', 'queue_jobs', 'caches', 'exports', 'notifications', 'broadcast_channels'] as $key) {
            $this->assertSame('covered_by_local_contract', $domains[$key]['status']);
            $this->assertGreaterThanOrEqual(3, count($domains[$key]['checks']));
        }

        $this->assertContains('jobs_store_tenant_scope', $domains['queue_jobs']['checks']);
        $this->assertContains('private_channels_authorize_current_membership_or_object_access', $domains['broadcast_channels']['checks']);
        $this->assertContains('public_search_uses_published_projection_only', $domains['search']['checks']);
    }
}
