<?php

namespace Tests\Unit;

use Tests\TestCase;

class ClubSurveyWebWorkspaceContractTest extends TestCase
{
    public function test_club_workspace_exposes_ajax_survey_lifecycle_with_accessible_controls(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Auth/Dashboard/ClubMemberships/Index.vue'));

        $this->assertStringContainsString("{ key: 'surveys'", $source);
        $this->assertStringContainsString("route('api.v1.clubs.surveys.index'", $source);
        $this->assertStringContainsString("route('api.v1.clubs.surveys.store'", $source);
        $this->assertStringContainsString("route('api.v1.clubs.surveys.vote'", $source);
        $this->assertStringContainsString("route('api.v1.clubs.surveys.close'", $source);
        $this->assertStringContainsString('for="club-survey-question"', $source);
        $this->assertStringContainsString('role="alert"', $source);
        $this->assertStringNotContainsString('window.location.reload', $source);
    }
}
