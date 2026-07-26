<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
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
}
