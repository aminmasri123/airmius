<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentedWorkspaceRoutesTest extends TestCase
{
    public function test_self_service_documentation_uses_registered_workspace_paths(): void
    {
        $documentation = file_get_contents(base_path('docs/SELF_SERVICE_WEB_API_APP_GAP_MATRIX.md'));

        foreach ([
            'auth.settings' => 'settings',
            'auth.club-inventory.index' => 'club-inventory',
            'auth.notifications.index' => 'notifications',
            'auth.files.index' => 'files',
        ] as $name => $uri) {
            $this->assertSame($uri, app('router')->getRoutes()->getByName($name)->uri());
            $this->assertStringContainsString('/'.$uri, $documentation);
            $this->assertStringNotContainsString('/auth/'.$uri, $documentation);
        }
    }
}
