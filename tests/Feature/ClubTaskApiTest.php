<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubTask;
use App\Models\Friendship;
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
        $this->assertSame(1, \App\Models\Notification::query()
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
    }

    public function test_manager_can_comment_and_attach_files_to_tasks(): void
    {
        Storage::fake(config('filesystems.uploads_disk', 'public'));
        $owner = User::factory()->create();
        $club = Club::create(['owner_id' => $owner->id, 'name' => 'Collab']);
        Sanctum::actingAs($owner);
        $url = '/api/v1/clubs/'.$club->id.'/tasks';
        $id = $this->postJson($url, ['title' => 'Share agenda'])->assertCreated()->json('data.id');

        $this->postJson($url.'/'.$id.'/comments', ['body' => 'Bitte bis Freitag prüfen.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Bitte bis Freitag prüfen.')
            ->assertJsonPath('data.user.id', $owner->id);

        $this->post($url.'/'.$id.'/attachments', [
            'attachments' => [UploadedFile::fake()->create('agenda.pdf', 32, 'application/pdf')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.0.display_name', 'agenda.pdf');

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.comments.0.body', 'Bitte bis Freitag prüfen.')
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
}
