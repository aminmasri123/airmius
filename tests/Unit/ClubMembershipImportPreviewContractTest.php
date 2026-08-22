<?php

namespace Tests\Unit;

use Tests\TestCase;

class ClubMembershipImportPreviewContractTest extends TestCase
{
    public function test_web_import_requires_a_preview_before_writing_members(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Auth/Dashboard/ClubMemberships/Index.vue'));

        $this->assertStringContainsString("route('api.v1.clubs.members.import-preview'", $source);
        $this->assertStringContainsString('if (!importPreview.value?.can_import || !selectedClub.value || importSubmitting.value) return', $source);
        $this->assertStringContainsString("route('api.v1.clubs.members.import'", $source);
        $this->assertStringContainsString('Object.assign(selectedClub.value, management)', $source);
        $this->assertStringContainsString('Import-Vorschau', $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringContainsString('for="club-member-import-file"', $source);
        $this->assertStringContainsString('@submit.prevent="importPreview ? importEmailMembers() : previewEmailMembers()"', $source);
    }
}
