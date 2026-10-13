<?php

namespace Tests\Unit;

use App\Support\ClubGovernanceTemplateCatalog;
use Tests\TestCase;

class ClubGovernanceTemplateCatalogTest extends TestCase
{
    public function test_catalog_defines_unique_editable_templates_and_positions(): void
    {
        $templates = ClubGovernanceTemplateCatalog::all();

        $this->assertCount(8, $templates);
        $this->assertSame(array_unique(array_column($templates, 'key')), array_column($templates, 'key'));
        $this->assertSame(ClubGovernanceTemplateCatalog::keys(), array_column($templates, 'key'));

        foreach ($templates as $template) {
            $this->assertContains($template['type'], ['board', 'committee', 'working_group']);
            $this->assertNotSame('', trim($template['name']));
            $this->assertNotEmpty($template['positions']);
            $this->assertSame(
                array_unique(array_column($template['positions'], 'title')),
                array_column($template['positions'], 'title'),
            );
        }

        $cashAudit = collect($templates)->firstWhere('key', 'cash_audit');
        $this->assertNotContains('Kassenwart', array_column($cashAudit['positions'], 'title'));
    }
}
