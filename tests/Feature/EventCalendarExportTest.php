<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventCalendarExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_event_can_be_exported_from_web_and_api_with_its_full_address(): void
    {
        $owner = User::factory()->create();
        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Training, Jugend',
            'type' => 'training',
            'status' => 'scheduled',
            'visibility' => 'private',
            'start_time' => '2026-11-24 17:00:00',
            'end_time' => '2026-11-24 18:30:00',
            'location_name' => 'Sporthalle',
            'location_street' => 'Vereinsweg',
            'location_house_number' => '12a',
            'location_postal_code' => '10115',
            'location_city' => 'Berlin',
            'location_country' => 'DE',
        ]);

        $this->actingAs($owner)
            ->get(route('auth.events.calendar', $event))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="event-'.$event->id.'.ics"')
            ->assertSee('SUMMARY:Training\, Jugend', false)
            ->assertSee('LOCATION:Sporthalle\, Vereinsweg 12a\, 10115 Berlin\, DE', false);

        Sanctum::actingAs($owner);
        $this->get('/api/v1/events/'.$event->id.'/calendar.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR', false);
    }

    public function test_private_event_export_is_forbidden_for_an_unrelated_user(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Privater Termin',
            'type' => 'meeting',
            'status' => 'scheduled',
            'visibility' => 'private',
            'start_time' => now()->addDay(),
        ]);

        $this->actingAs($outsider)
            ->get(route('auth.events.calendar', $event))
            ->assertForbidden();

        Sanctum::actingAs($outsider);
        $this->get('/api/v1/events/'.$event->id.'/calendar.ics')
            ->assertForbidden();
    }
}
