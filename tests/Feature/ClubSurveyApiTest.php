<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubRoleDefinition;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubSurveyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_view_vote_and_reach_quorum_on_a_club_survey(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Survey Club',
        ]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $surveyId = $this->postJson('/api/v1/clubs/'.$club->id.'/surveys', [
            'question' => 'Soll der Verein ein Sommerfest veranstalten?',
            'description' => 'Bitte bis Freitag abstimmen.',
            'audience_type' => 'all_members',
            'quorum' => 50,
            'options' => ['Ja', 'Nein'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.question', 'Soll der Verein ein Sommerfest veranstalten?')
            ->assertJsonPath('data.quorum', 50)
            ->assertJsonPath('data.eligible_voters', 2)
            ->assertJsonPath('data.quorum_reached', false)
            ->assertJsonCount(2, 'data.options')
            ->json('data.id');

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/clubs/'.$club->id.'/surveys')
            ->assertOk()
            ->assertJsonPath('data.0.id', $surveyId)
            ->assertJsonPath('data.0.my_option_id', null)
            ->assertJsonPath('data.0.can_manage', false);

        $optionId = $this->getJson('/api/v1/clubs/'.$club->id.'/surveys')
            ->json('data.0.options.0.id');

        $this->postJson('/api/v1/clubs/'.$club->id.'/surveys/'.$surveyId.'/vote', [
            'option_id' => $optionId,
        ])
            ->assertOk()
            ->assertJsonPath('data.survey_id', $surveyId)
            ->assertJsonPath('data.option_id', $optionId);

        $this->getJson('/api/v1/clubs/'.$club->id.'/surveys')
            ->assertJsonPath('data.0.my_option_id', $optionId)
            ->assertJsonPath('data.0.votes', 1)
            ->assertJsonPath('data.0.quorum_reached', true);

        Sanctum::actingAs($outsider);

        $this->getJson('/api/v1/clubs/'.$club->id.'/surveys')->assertForbidden();
        $this->postJson('/api/v1/clubs/'.$club->id.'/surveys/'.$surveyId.'/vote', [
            'option_id' => $optionId,
        ])->assertForbidden();
    }

    public function test_team_targeted_surveys_are_scoped_and_can_be_closed_by_a_manager(): void
    {
        $owner = User::factory()->create();
        $teamMember = User::factory()->create();
        $clubMember = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Team Survey Club',
        ]);
        $club->users()->attach([
            $teamMember->id => ['role' => 'member', 'membership_status' => 'active'],
            $clubMember->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'U18 Team',
        ]);
        $team->users()->attach($teamMember->id, ['role' => TeamRoles::PLAYER]);

        Sanctum::actingAs($owner);

        $surveyId = $this->postJson('/api/v1/clubs/'.$club->id.'/surveys', [
            'question' => 'Welcher Trainingstag passt?',
            'audience_type' => 'team',
            'team_id' => $team->id,
            'options' => ['Dienstag', 'Donnerstag'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.team.name', 'U18 Team')
            ->json('data.id');

        Sanctum::actingAs($clubMember);

        $this->getJson('/api/v1/clubs/'.$club->id.'/surveys')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($teamMember);

        $this->getJson('/api/v1/clubs/'.$club->id.'/surveys')
            ->assertOk()
            ->assertJsonPath('data.0.id', $surveyId)
            ->assertJsonPath('data.0.eligible_voters', 1);

        $optionId = $this->getJson('/api/v1/clubs/'.$club->id.'/surveys')
            ->json('data.0.options.0.id');
        $this->postJson('/api/v1/clubs/'.$club->id.'/surveys/'.$surveyId.'/vote', [
            'option_id' => $optionId,
        ])->assertOk();

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/clubs/'.$club->id.'/surveys/'.$surveyId.'/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        Sanctum::actingAs($teamMember);

        $this->postJson('/api/v1/clubs/'.$club->id.'/surveys/'.$surveyId.'/vote', [
            'option_id' => $optionId,
        ])->assertUnprocessable();
    }

    public function test_survey_edit_close_and_delete_are_separated_and_team_scoped(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $voter = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Scoped Survey Club']);
        $club->users()->attach([
            $actor->id => ['role' => 'financial_controller', 'roles' => ['financial_controller'], 'membership_status' => 'active'],
            $voter->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false]);
        $otherDepartment = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Senioren', 'is_public' => false]);
        $team = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $department->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $otherDepartment->id]);
        $team->users()->attach($voter->id, ['role' => TeamRoles::PLAYER]);
        $editor = $this->surveyRole($club, 'survey_editor', [ClubPermissions::SURVEYS_EDIT]);
        $closer = $this->surveyRole($club, 'survey_closer', [ClubPermissions::SURVEYS_CLOSE]);
        $deleter = $this->surveyRole($club, 'survey_deleter', [ClubPermissions::SURVEYS_DELETE]);

        Sanctum::actingAs($owner);
        $this->assignSurveyRole($club, $actor, $editor, $team);

        Sanctum::actingAs($actor);
        $this->getJson('/api/v1/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('data.can_edit_surveys', true)
            ->assertJsonPath('data.can_close_surveys', false)
            ->assertJsonPath('data.can_delete_surveys', false);
        $votedSurvey = $this->postJson('/api/v1/clubs/'.$club->id.'/surveys', $this->surveyPayload($team, 'Trainingstag'))
            ->assertCreated()
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_close', false)
            ->assertJsonPath('data.can_delete', false);
        $votedSurveyId = $votedSurvey->json('data.id');
        $optionId = $votedSurvey->json('data.options.0.id');
        $cleanSurveyId = $this->postJson('/api/v1/clubs/'.$club->id.'/surveys', $this->surveyPayload($team, 'Trikotfarbe'))
            ->assertCreated()->json('data.id');
        $this->postJson('/api/v1/clubs/'.$club->id.'/surveys', $this->surveyPayload($otherTeam, 'Fremde Umfrage'))
            ->assertForbidden();
        $this->postJson('/api/v1/clubs/'.$club->id.'/surveys/'.$votedSurveyId.'/close')->assertForbidden();

        Sanctum::actingAs($voter);
        $this->postJson('/api/v1/clubs/'.$club->id.'/surveys/'.$votedSurveyId.'/vote', ['option_id' => $optionId])
            ->assertOk();

        Sanctum::actingAs($actor);
        $this->putJson('/api/v1/clubs/'.$club->id.'/surveys/'.$votedSurveyId, $this->surveyPayload($team, 'Manipuliert'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('survey');
        $this->putJson('/api/v1/clubs/'.$club->id.'/surveys/'.$cleanSurveyId, $this->surveyPayload($team, 'Neue Trikotfarbe'))
            ->assertOk()
            ->assertJsonPath('data.question', 'Neue Trikotfarbe');
        $this->putJson('/api/v1/clubs/'.$club->id.'/surveys/'.$cleanSurveyId, $this->surveyPayload($otherTeam, 'Verschoben'))
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->assignSurveyRole($club, $actor, $closer, $team);

        Sanctum::actingAs($actor);
        $this->getJson('/api/v1/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('data.can_edit_surveys', false)
            ->assertJsonPath('data.can_close_surveys', true)
            ->assertJsonPath('data.can_delete_surveys', false);
        $this->getJson('/api/v1/clubs/'.$club->id.'/surveys')
            ->assertOk()
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.can_close', true);
        $this->postJson('/api/v1/clubs/'.$club->id.'/surveys/'.$votedSurveyId.'/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/surveys/'.$cleanSurveyId)->assertForbidden();

        Sanctum::actingAs($owner);
        $this->assignSurveyRole($club, $actor, $deleter, $team);

        Sanctum::actingAs($actor);
        $this->getJson('/api/v1/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('data.can_edit_surveys', false)
            ->assertJsonPath('data.can_close_surveys', false)
            ->assertJsonPath('data.can_delete_surveys', true);
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/surveys/'.$votedSurveyId)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('survey');
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/surveys/'.$cleanSurveyId)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('club_surveys', ['id' => $cleanSurveyId]);
        $this->assertDatabaseHas('club_surveys', ['id' => $votedSurveyId]);
    }

    private function surveyRole(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
    }

    private function assignSurveyRole(Club $club, User $actor, ClubRoleDefinition $role, Team $team): void
    {
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$actor->id}/role-definitions", [
            'assignments' => [[
                'role_definition_id' => $role->id,
                'scope_type' => 'team',
                'scope_id' => $team->id,
            ]],
        ])->assertOk();
    }

    private function surveyPayload(Team $team, string $question): array
    {
        return [
            'question' => $question,
            'description' => null,
            'audience_type' => 'team',
            'team_id' => $team->id,
            'quorum' => null,
            'closes_at' => null,
            'options' => ['Ja', 'Nein'],
        ];
    }
}
