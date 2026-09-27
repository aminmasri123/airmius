<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $id = $this->postJson($url, ['title' => 'Book gym'])->assertCreated()
            ->assertJsonPath('data.club_id', $club->id)->json('data.id');
        $this->putJson($url.'/'.$id, ['title' => 'Book court', 'completed' => true])
            ->assertOk()->assertJsonPath('data.title', 'Book court');
        $this->assertNotNull(ClubTask::findOrFail($id)->completed_at);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->putJson($url.'/'.$id, ['completed' => false])->assertOk()
            ->assertJsonPath('data.completed_at', null);
        $this->deleteJson($url.'/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('club_tasks', ['id' => $id]);
    }

    public function test_tasks_are_club_scoped_and_forbidden_to_regular_members(): void
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
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, ['title' => 'Denied'])->assertForbidden();
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
    }
}
