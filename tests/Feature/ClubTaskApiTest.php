<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubTask;
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
        ])->assertCreated()
            ->assertJsonPath('data.club_id', $club->id)->json('data.id');
        $this->putJson($url.'/'.$id, ['title' => 'Book court', 'completed' => true])
            ->assertOk()
            ->assertJsonPath('data.title', 'Book court')
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.assignee.id', $owner->id)
            ->assertJsonPath('data.checklist.0.title', 'Call caretaker');
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
}
