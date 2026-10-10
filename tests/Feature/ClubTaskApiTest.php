<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubTask;
use App\Models\Friendship;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_edit_complete_reopen_and_delete_tasks(): void
    {
        $owner = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Tasks Club']);
        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $id = $this->postJson($url, [
            'title' => 'Book gym',
            'description' => 'Coordinate with the city.',
            'priority' => 'high',
            'status' => 'in_progress',
            'assigned_to' => $owner->id,
            'participant_ids' => [$owner->id],
            'checklist' => [['title' => 'Call caretaker', 'done' => false]],
            'attachment_links' => [['title' => 'Offer', 'url' => 'https://example.test/offer.pdf']],
        ])->assertCreated()
            ->assertJsonPath('data.club_id', $club->id)->json('data.id');
        $this->putJson($url.'/'.$id, ['title' => 'Book court', 'completed' => true])
            ->assertOk()
            ->assertJsonPath('data.title', 'Book court')
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.assignee.id', $owner->id)
            ->assertJsonPath('data.checklist.0.title', 'Call caretaker')
            ->assertJsonPath('data.attachment_links.0.url', 'https://example.test/offer.pdf');
        $this->assertNotNull(ClubTask::findOrFail($id)->completed_at);
        $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.members.0.id', $owner->id);
        $this->putJson($url.'/'.$id, ['status' => 'read'])
            ->assertOk()
            ->assertJsonPath('data.status', 'read');
        $this->putJson($url.'/'.$id, ['completed' => false])->assertOk()
            ->assertJsonPath('data.completed_at', null);
        $this->deleteJson($url.'/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('club_tasks', ['id' => $id]);
    }

    public function test_tasks_are_club_scoped_and_regular_members_can_collaborate_only_in_their_club(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'First']);
        $other = Club::create(['owner_id' => $owner->id, 'name' => 'Second']);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        $task = ClubTask::create(['club_id' => $other->id, 'created_by' => $owner->id, 'title' => 'Private']);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        Sanctum::actingAs($owner);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
        $this->putJson($url.'/'.$task->id, ['completed' => true])->assertNotFound();
        $this->deleteJson($url.'/'.$task->id)->assertNotFound();
        Sanctum::actingAs($member);
        $memberTaskId = $this->postJson($url, ['title' => 'Bring bib numbers'])
            ->assertCreated()
            ->assertJsonPath('data.created_by', $member->id)
            ->json('data.id');
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson($url.'/'.$memberTaskId.'/comments', ['body' => 'Ich kümmere mich darum.'])
            ->assertCreated();
        $this->putJson($url.'/'.$memberTaskId, ['completed' => true])->assertOk();
        $this->putJson('/api/v1/clubs/'.$other->id.'/tasks/'.$task->id, ['completed' => true])->assertForbidden();
        $this->deleteJson('/api/v1/clubs/'.$other->id.'/tasks/'.$task->id)->assertForbidden();
    }

    public function test_shared_tasks_never_leak_between_clubs_for_multi_club_users(): void
    {
        $creator = User::factory()->create();
        $assignee = User::factory()->create();
        $first = Club::create(['owner_id' => $creator->id, 'name' => 'First Club']);
        $second = Club::create(['owner_id' => $creator->id, 'name' => 'Second Club']);
        $first->users()->attach($assignee->id, ['role' => 'member', 'membership_status' => 'active']);
        $second->users()->attach($assignee->id, ['role' => 'member', 'membership_status' => 'active']);

        Sanctum::actingAs($creator);
        $taskId = $this->postJson('/api/v1/clubs/'.$second->id.'/tasks', [
            'title' => 'Only for the second club',
            'visibility' => 'shared',
            'assigned_to' => $assignee->id,
        ])->assertCreated()
            ->assertJsonPath('data.club_id', $second->id)
            ->json('data.id');
        $privateTaskId = $this->postJson('/api/v1/clubs/'.$first->id.'/tasks', [
            'title' => 'Private first-club task',
            'visibility' => 'personal',
        ])->assertCreated()->json('data.id');

        $creatorGlobalTasks = collect(
            $this->getJson('/api/v1/tasks')
                ->assertOk()
                ->assertJsonPath('data.0.relationship', 'creator')
                ->json('data')
        );
        $this->assertEqualsCanonicalizing(
            [$taskId, $privateTaskId],
            $creatorGlobalTasks->pluck('id')->all(),
        );
        $this->assertSame(
            'Second Club',
            $creatorGlobalTasks->firstWhere('id', $taskId)['club']['name'],
        );

        $creatorFirstClubIds = collect(
            $this->getJson('/api/v1/clubs/'.$first->id.'/tasks')
                ->assertOk()
                ->json('data')
        )->pluck('id');
        $this->assertFalse($creatorFirstClubIds->contains($taskId));
        $creatorSecondClubIds = collect(
            $this->getJson('/api/v1/clubs/'.$second->id.'/tasks')
                ->assertOk()
                ->json('data')
        )->pluck('id');
        $this->assertTrue($creatorSecondClubIds->contains($taskId));

        Sanctum::actingAs($assignee);
        $assigneeFirstClubIds = collect(
            $this->getJson('/api/v1/clubs/'.$first->id.'/tasks')
                ->assertOk()
                ->json('data')
        )->pluck('id');
        $this->assertFalse($assigneeFirstClubIds->contains($taskId));
        $assigneeSecondClubIds = collect(
            $this->getJson('/api/v1/clubs/'.$second->id.'/tasks')
                ->assertOk()
                ->json('data')
        )->pluck('id');
        $this->assertTrue($assigneeSecondClubIds->contains($taskId));
        $assigneeGlobalTasks = collect(
            $this->getJson('/api/v1/tasks')->assertOk()->json('data')
        );
        $this->assertTrue($assigneeGlobalTasks->pluck('id')->contains($taskId));
        $this->assertFalse($assigneeGlobalTasks->pluck('id')->contains($privateTaskId));
        $this->assertSame(
            'assigned',
            $assigneeGlobalTasks->firstWhere('id', $taskId)['relationship'],
        );
    }

    public function test_team_only_sportler_can_open_club_tasks_for_their_team(): void
    {
        $owner = User::factory()->create();
        $sportler = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Team Club']);
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'U15']);
        $team->users()->attach($sportler->id, ['role' => 'player']);

        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $taskId = $this->postJson($url, [
            'title' => 'Bring bottle',
            'visibility' => 'team',
            'team_id' => $team->id,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($sportler);
        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.id', $taskId)
            ->assertJsonPath('meta.teams.0.id', $team->id);
    }

    public function test_task_input_is_validated(): void
    {
        $owner = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Validation']);
        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $this->postJson($url, ['title' => '   '])->assertUnprocessable();
        $this->postJson($url, ['title' => str_repeat('a', 256)])->assertUnprocessable();
        $id = $this->postJson($url, ['title' => 'Valid'])->assertCreated()->json('data.id');
        $this->putJson($url.'/'.$id, ['title' => ''])->assertUnprocessable();
        $this->putJson($url.'/'.$id, ['completed' => 'invalid'])->assertUnprocessable();
        $this->postJson($url, ['title' => 'Bad user', 'assigned_to' => User::factory()->create()->id])
            ->assertStatus(422);
        $this->postJson($url, [
            'title' => 'Bad link',
            'attachment_links' => [['url' => 'javascript:alert(1)']],
        ])->assertUnprocessable();
    }

    public function test_task_assignment_notifies_new_assignee_only(): void
    {
        $owner = User::factory()->create(['name' => 'Board User']);
        $assignee = User::factory()->create(['name' => 'Assigned Member']);
        $nextAssignee = User::factory()->create(['name' => 'Next Member']);
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Notify Club']);
        $club->users()->attach($assignee->id, ['role' => 'member', 'membership_status' => 'active']);
        $club->users()->attach($nextAssignee->id, ['role' => 'member', 'membership_status' => 'active']);
        Sanctum::actingAs($owner);

        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $id = $this->postJson($url, [
            'title' => 'Bring balls',
            'assigned_to' => $assignee->id,
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $assignee->id,
            'type' => 'club.task.assigned',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $owner->id,
            'type' => 'club.task.assigned',
        ]);

        $this->putJson($url.'/'.$id, [
            'title' => 'Bring balls',
            'assigned_to' => $assignee->id,
        ])->assertOk();
        $this->assertSame(1, Notification::query()
            ->where('user_id', $assignee->id)
            ->where('type', 'club.task.assigned')
            ->count());

        $this->putJson($url.'/'.$id, [
            'assigned_to' => $nextAssignee->id,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $nextAssignee->id,
            'type' => 'club.task.assigned',
        ]);
    }

    public function test_regular_member_can_assign_tasks_only_to_self_or_club_friends_and_friend_can_respond(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $friend = User::factory()->create();
        $otherMember = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Friends Club']);
        foreach ([$member, $friend, $otherMember] as $user) {
            $club->users()->attach($user->id, ['role' => 'member', 'membership_status' => 'active']);
        }
        Friendship::create(['user_id' => $member->id, 'friend_id' => $friend->id]);
        Friendship::create(['user_id' => $friend->id, 'friend_id' => $member->id]);

        Sanctum::actingAs($member);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $this->postJson($url, [
            'title' => 'Ask random member',
            'assigned_to' => $otherMember->id,
        ])->assertUnprocessable();

        $taskId = $this->postJson($url, [
            'title' => 'Bring cones',
            'assigned_to' => $friend->id,
        ])->assertCreated()
            ->assertJsonPath('data.visibility', 'shared')
            ->assertJsonPath('data.can_update', true)
            ->assertJsonPath('data.assignment_status.'.$friend->id, 'pending')
            ->json('data.id');

        Sanctum::actingAs($friend);
        $this->postJson($url.'/'.$taskId.'/assignment/accept')
            ->assertOk()
            ->assertJsonPath('data.my_assignment_status', 'accepted');
        $this->postJson($url.'/'.$taskId.'/assignment/decline')
            ->assertOk()
            ->assertJsonPath('data.my_assignment_status', 'declined')
            ->assertJsonPath('data.assigned_to', null);

        Sanctum::actingAs($member);
        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.id', $taskId)
            ->assertJsonPath('data.0.can_update', true);
        $this->putJson($url.'/'.$taskId, ['title' => 'Bring cones edited'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Bring cones edited')
            ->assertJsonPath('data.can_update', true);

        Sanctum::actingAs($member);
        $candidateId = $this->postJson($url, [
            'title' => 'Who can drive',
            'participant_ids' => [$friend->id],
        ])->assertCreated()
            ->assertJsonPath('data.assigned_to', null)
            ->assertJsonPath('data.visibility', 'shared')
            ->assertJsonPath('data.assignment_status.'.$friend->id, 'pending')
            ->json('data.id');

        Sanctum::actingAs($friend);
        $this->postJson($url.'/'.$candidateId.'/assignment/accept')
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $friend->id)
            ->assertJsonPath('data.participant_ids.0', $friend->id)
            ->assertJsonPath('data.my_assignment_status', 'accepted');
        $this->putJson($url.'/'.$candidateId, ['status' => 'read'])
            ->assertOk()
            ->assertJsonPath('data.status', 'read');

        Sanctum::actingAs($member);
        $memberTasks = $this->getJson($url)
            ->assertOk()
            ->json('data');
        $candidatePayload = collect($memberTasks)->firstWhere('id', $candidateId);
        $this->assertNotNull($candidatePayload);
        $this->assertTrue($candidatePayload['can_update']);
        $this->putJson($url.'/'.$candidateId, ['title' => 'Who can drive edited'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Who can drive edited')
            ->assertJsonPath('data.can_update', true);
    }

    public function test_participant_can_remove_task_from_own_list_without_deleting_it_for_others(): void
    {
        $owner = User::factory()->create();
        $one = User::factory()->create();
        $two = User::factory()->create();
        $three = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Shared Tasks']);
        foreach ([$one, $two, $three] as $user) {
            $club->users()->attach($user->id, ['role' => 'member', 'membership_status' => 'active']);
        }

        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $taskId = $this->postJson($url, [
            'title' => 'Bring shirts',
            'participant_ids' => [$one->id, $two->id, $three->id],
        ])->assertCreated()
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.can_leave', false)
            ->json('data.id');

        Sanctum::actingAs($one);
        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.can_delete', false)
            ->assertJsonPath('data.0.can_leave', true);
        $this->postJson($url.'/'.$taskId.'/leave')->assertNoContent();
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('club_tasks', ['id' => $taskId]);

        Sanctum::actingAs($two);
        $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $taskId);

        Sanctum::actingAs($owner);
        $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.participant_ids.0', $two->id)
            ->assertJsonPath('data.0.participant_ids.1', $three->id)
            ->assertJsonPath('data.0.assignment_status.'.$one->id, 'declined');
    }

    public function test_manager_can_comment_and_attach_files_to_tasks(): void
    {
        Storage::fake(config('filesystems.uploads_disk', 'public'));
        $owner = User::factory()->create();
        $assignee = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Collab']);
        $club->users()->attach($assignee->id, ['role' => 'member', 'membership_status' => 'active']);
        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $id = $this->postJson($url, [
            'title' => 'Share agenda',
            'assigned_to' => $assignee->id,
        ])->assertCreated()->json('data.id');

        $this->postJson($url.'/'.$id.'/comments', ['body' => 'Bitte bis Freitag prüfen.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Bitte bis Freitag prüfen.')
            ->assertJsonPath('data.user.id', $owner->id);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $assignee->id,
            'type' => 'club.task.comment',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $owner->id,
            'type' => 'club.task.comment',
        ]);

        Sanctum::actingAs($assignee);
        $this->postJson($url.'/'.$id.'/comments', ['body' => 'Ist erledigt.'])
            ->assertCreated();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'club.task.comment',
        ]);

        Sanctum::actingAs($owner);
        $this->post($url.'/'.$id.'/attachments', [
            'attachments' => [UploadedFile::fake()->create('agenda.pdf', 32, 'application/pdf')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.0.display_name', 'agenda.pdf');

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.comments.0.body', 'Bitte bis Freitag prüfen.')
            ->assertJsonPath('data.0.comments.1.body', 'Ist erledigt.')
            ->assertJsonPath('data.0.attachments.0.display_name', 'agenda.pdf');
    }

    public function test_task_visibility_controls_listing_reading_and_collaboration(): void
    {
        $owner = User::factory()->create();
        $assignee = User::factory()->create();
        $teammate = User::factory()->create();
        $clubMember = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Visibility Club']);
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'U18']);
        foreach ([$assignee, $teammate, $clubMember] as $member) {
            $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        }
        $team->users()->attach($teammate->id, ['role' => 'player']);

        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $personalId = $this->postJson($url, ['title' => 'Private board task', 'visibility' => 'personal'])->assertCreated()->json('data.id');
        $sharedId = $this->postJson($url, ['title' => 'Shared task', 'visibility' => 'shared', 'assigned_to' => $assignee->id])->assertCreated()->json('data.id');
        $teamId = $this->postJson($url, ['title' => 'Team task', 'visibility' => 'team', 'team_id' => $team->id])->assertCreated()->json('data.id');
        $clubId = $this->postJson($url, ['title' => 'Club task', 'visibility' => 'club'])->assertCreated()->json('data.id');

        Sanctum::actingAs($assignee);
        $assigneeIds = collect($this->getJson($url)->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($assigneeIds->contains($sharedId));
        $this->assertFalse($assigneeIds->contains($personalId));
        $this->assertFalse($assigneeIds->contains($teamId));
        $this->assertTrue($assigneeIds->contains($clubId));
        $this->putJson($url.'/'.$sharedId, ['status' => 'in_progress'])->assertOk();

        Sanctum::actingAs($teammate);
        $teammateIds = collect($this->getJson($url)->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($teammateIds->contains($teamId));
        $this->assertTrue($teammateIds->contains($clubId));
        $this->assertFalse($teammateIds->contains($sharedId));
        $this->postJson($url.'/'.$teamId.'/comments', ['body' => 'Team can read and comment.'])->assertCreated();
        $this->putJson($url.'/'.$teamId, ['status' => 'done'])->assertForbidden();

        Sanctum::actingAs($clubMember);
        $clubMemberIds = collect($this->getJson($url)->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($clubMemberIds->contains($clubId));
        $this->assertFalse($clubMemberIds->contains($teamId));
        $this->assertFalse($clubMemberIds->contains($sharedId));
        $this->postJson($url.'/'.$teamId.'/comments', ['body' => 'Not in this team'])->assertForbidden();

        Sanctum::actingAs($outsider);
        $this->getJson($url)->assertForbidden();
    }

    public function test_private_member_tasks_are_not_exposed_to_club_managers(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Privacy Club']);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';

        Sanctum::actingAs($member);
        $taskId = $this->postJson($url, [
            'title' => 'Personal reminder',
            'visibility' => 'personal',
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonFragment(['id' => $taskId, 'relationship' => 'creator']);

        Sanctum::actingAs($owner);
        $managerIds = collect($this->getJson($url)->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($managerIds->contains($taskId));
        $globalManagerIds = collect($this->getJson('/api/v1/tasks')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($globalManagerIds->contains($taskId));
        $this->putJson($url.'/'.$taskId, ['status' => 'done'])->assertForbidden();
    }

    public function test_stale_task_update_is_rejected_and_attachment_removal_requires_edit_rights(): void
    {
        Storage::fake(config('filesystems.uploads_disk', 'public'));
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Concurrency Club']);
        $club->users()->attach($reader->id, ['role' => 'member', 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $task = $this->postJson($url, ['title' => 'Prepare handover', 'visibility' => 'club'])
            ->assertCreated()
            ->json('data');

        $this->post($url.'/'.$task['id'].'/attachments', [
            'attachments' => [UploadedFile::fake()->create('handover.pdf', 16, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $fileId = $this->getJson($url)->assertOk()->json('data.0.attachments.0.id');

        $this->travel(1)->second();
        $this->putJson($url.'/'.$task['id'], ['description' => 'Owner update'])->assertOk();

        $this->putJson($url.'/'.$task['id'], [
            'title' => 'Stale update',
            'updated_at' => $task['updated_at'],
        ])->assertStatus(409);

        Sanctum::actingAs($reader);
        $this->deleteJson($url.'/'.$task['id'].'/attachments/'.$fileId)->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson($url.'/'.$task['id'].'/attachments/'.$fileId)->assertNoContent();
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.attachments_count', 0);
    }

    public function test_shared_assignment_keeps_every_participant_and_tracks_progress_individually(): void
    {
        $owner = User::factory()->create();
        $first = User::factory()->create(['name' => 'First helper']);
        $second = User::factory()->create(['name' => 'Second helper']);
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Shared Progress Club']);
        foreach ([$first, $second] as $member) {
            $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        }

        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $taskId = $this->postJson($url, [
            'title' => 'Build event together',
            'visibility' => 'shared',
            'assignment_mode' => 'shared_all',
            'participant_ids' => [$first->id, $second->id],
        ])->assertCreated()
            ->assertJsonPath('data.assignment_mode', 'shared_all')
            ->assertJsonCount(2, 'data.participant_progress')
            ->json('data.id');

        Sanctum::actingAs($first);
        $this->putJson($url.'/'.$taskId.'/progress', ['status' => 'done'])->assertForbidden();
        $this->postJson($url.'/'.$taskId.'/assignment/accept')
            ->assertOk()
            ->assertJsonCount(2, 'data.participant_ids');
        $this->putJson($url.'/'.$taskId.'/progress', ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.my_progress', 'done')
            ->assertJsonPath('data.status', 'open');

        Sanctum::actingAs($second);
        $this->postJson($url.'/'.$taskId.'/assignment/accept')
            ->assertOk()
            ->assertJsonCount(2, 'data.participant_ids');
        $this->putJson($url.'/'.$taskId.'/progress', ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
        $this->putJson($url.'/'.$taskId.'/progress', ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.completed_at', fn ($value) => $value !== null);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'club.task.progress',
        ]);
        $activityTypes = collect(ClubTask::findOrFail($taskId)->activity_log)->pluck('type');
        $this->assertTrue($activityTypes->contains('assignment_accepted'));
        $this->assertTrue($activityTypes->contains('progress_changed'));
    }

    public function test_open_claim_is_taken_by_first_accepting_participant(): void
    {
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Open Claim Club']);
        foreach ([$first, $second] as $member) {
            $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        }

        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $taskId = $this->postJson($url, [
            'title' => 'Who can drive?',
            'visibility' => 'shared',
            'assignment_mode' => 'open_claim',
            'participant_ids' => [$first->id, $second->id],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($first);
        $this->postJson($url.'/'.$taskId.'/assignment/accept')
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $first->id)
            ->assertJsonCount(1, 'data.participant_ids')
            ->assertJsonPath('data.assignment_status.'.$second->id, 'declined');

        Sanctum::actingAs($second);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
    }
}
