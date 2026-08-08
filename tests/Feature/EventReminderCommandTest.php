<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Club;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_due_event_reminders_to_team_members_once(): void
    {
        $owner = User::factory()->create();
        $yes = User::factory()->create();
        $maybe = User::factory()->create();
        $declined = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach([
            $owner->id => ['role' => 'Coach'],
            $yes->id => ['role' => 'Player'],
            $maybe->id => ['role' => 'Player'],
            $declined->id => ['role' => 'Player'],
        ]);

        $event = Event::create([
            'user_id' => $owner->id,
            'team_id' => $team->id,
            'club_id' => $team->club_id,
            'title' => 'Abendtraining',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addHour(),
            'reminder_at' => now()->subMinute(),
            'location' => 'Halle 1',
        ]);
        $event->participants()->attach($yes->id, ['status' => 'yes']);
        $event->participants()->attach($maybe->id, ['status' => 'maybe']);
        $event->participants()->attach($declined->id, ['status' => 'no']);

        $this->artisan('airmius:send-event-reminders')
            ->expectsOutput('3 Event-Erinnerungen für 1 Events versendet.')
            ->assertSuccessful();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
        ]);
        $this->assertNotNull($event->fresh()->reminder_sent_at);
        $this->assertSame(3, Notification::where('type', 'event.reminder')->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'type' => 'event.reminder']);
        $this->assertDatabaseHas('notifications', ['user_id' => $yes->id, 'type' => 'event.reminder']);
        $this->assertDatabaseHas('notifications', ['user_id' => $maybe->id, 'type' => 'event.reminder']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $declined->id, 'type' => 'event.reminder']);

        $this->artisan('airmius:send-event-reminders')
            ->expectsOutput('0 Event-Erinnerungen für 0 Events versendet.')
            ->assertSuccessful();
    }

    public function test_it_does_not_send_reminders_for_cancelled_or_started_events(): void
    {
        $user = User::factory()->create();

        Event::create([
            'user_id' => $user->id,
            'title' => 'Cancelled',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'cancelled',
            'start_time' => now()->addHour(),
            'reminder_at' => now()->subMinute(),
        ]);
        Event::create([
            'user_id' => $user->id,
            'title' => 'Started',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->subMinute(),
            'reminder_at' => now()->subHour(),
        ]);

        $this->artisan('airmius:send-event-reminders')
            ->expectsOutput('0 Event-Erinnerungen für 0 Events versendet.')
            ->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'event.reminder')->count());
    }
}
