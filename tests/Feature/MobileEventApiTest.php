<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_event_participation_can_be_saved_changed_and_withdrawn(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();

        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Lauftreff',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'location_name' => 'Sportplatz',
            'location_city' => 'Saarbruecken',
            'notes' => 'Bitte 10 Minuten vorher da sein.',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/events/'.$event->id)
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', null)
            ->assertJsonPath('data.can_join', true);

        $this->postJson('/api/v1/events/'.$event->id.'/participation', ['status' => 'yes'])
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'yes')
            ->assertJsonPath('data.yes_count', 1);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'yes',
            'response_mode' => 'mobile',
        ]);

        $this->postJson('/api/v1/events/'.$event->id.'/participation', ['status' => 'maybe'])
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'maybe')
            ->assertJsonPath('data.maybe_count', 1);

        $this->deleteJson('/api/v1/events/'.$event->id.'/participation')
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', null);

        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_mobile_event_capacity_blocks_new_yes_responses(): void
    {
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Teamtraining',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'max_participants' => 1,
        ]);

        EventParticipant::query()->create([
            'event_id' => $event->id,
            'user_id' => $first->id,
            'status' => 'yes',
        ]);

        Sanctum::actingAs($second);

        $this->postJson('/api/v1/events/'.$event->id.'/participation', ['status' => 'yes'])
            ->assertStatus(422);

        $this->postJson('/api/v1/events/'.$event->id.'/participation', ['status' => 'maybe'])
            ->assertOk()
            ->assertJsonPath('data.my_participation_status', 'maybe');
    }
}
