<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_paginates_list_events_but_keeps_calendar_month_complete(): void
    {
        Carbon::setTestNow('2026-05-16 10:00:00');

        $user = User::factory()->create();

        foreach (range(1, 35) as $index) {
            Event::create([
                'user_id' => $user->id,
                'title' => 'Mai Event '.$index,
                'type' => 'training',
                'visibility' => 'public',
                'status' => 'scheduled',
                'start_time' => Carbon::parse('2026-05-16 12:00:00')->addHours($index),
                'location' => 'Halle '.$index,
            ]);
        }

        Event::create([
            'user_id' => $user->id,
            'title' => 'Juni Event',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => '2026-06-20 12:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('auth.events.index', ['calendar_month' => '2026-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Events/Index')
                ->has('events.data', 30)
                ->has('calendarEvents', 35)
                ->where('eventStats.upcoming', 36)
                ->where('calendar.month', '2026-05')
                ->where('filters.calendar_month', '2026-05'));
    }

    public function test_realtime_partial_reload_omits_static_event_form_catalogs(): void
    {
        $user = User::factory()->create();

        Event::create([
            'user_id' => $user->id,
            'title' => 'Partial Event',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);

        $assetVersion = app(HandleInertiaRequests::class)->version(request());

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $assetVersion,
                'X-Inertia-Partial-Component' => 'Auth/Dashboard/Events/Index',
                'X-Inertia-Partial-Data' => 'events,calendarEvents,eventStats,nextEvent,calendar',
            ])
            ->get(route('auth.events.index'))
            ->assertOk()
            ->assertJsonMissingPath('props.clubs')
            ->assertJsonMissingPath('props.teams')
            ->assertJsonMissingPath('props.sports')
            ->assertJsonMissingPath('props.eventDefaults')
            ->assertJsonMissingPath('props.eventCreation')
            ->assertJsonPath('props.events.data.0.title', 'Partial Event');
    }
}
