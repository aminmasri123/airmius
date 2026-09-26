<?php

namespace Tests\Feature;

use App\Models\DomainOutboxEvent;
use App\Models\Event;
use App\Models\EventRecurrenceException;
use App\Models\EventRecurrenceRuleVersion;
use App\Models\EventRecurrenceSeries;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expands_daily_recurring_events(): void
    {
        $this->actingAs(User::factory()->create());

        app(EventService::class)->create([
            'title' => 'Morning run',
            'type' => 'training',
            'visibility' => 'public',
            'start_time' => '2026-05-16 10:00',
            'end_time' => '2026-05-16 11:00',
            'recurring' => 'daily',
            'recurrence_ends_at' => '2026-05-18',
            'event_timezone' => 'Europe/Berlin',
        ]);

        $this->assertDatabaseHas('domain_outbox_events', [
            'event_name' => 'organization.event.series_created.v1',
            'aggregate_type' => Event::class,
        ]);

        $this->assertSame([
            '2026-05-16 10:00',
            '2026-05-17 10:00',
            '2026-05-18 10:00',
        ], $this->localEventStarts());
    }

    public function test_it_expands_monthly_recurring_events_without_month_overflow(): void
    {
        $this->actingAs(User::factory()->create());

        app(EventService::class)->create([
            'title' => 'Monthly planning',
            'type' => 'meeting',
            'visibility' => 'public',
            'start_time' => '2026-01-31 18:30',
            'end_time' => '2026-01-31 19:30',
            'recurring' => 'monthly',
            'recurrence_ends_at' => '2026-03-31',
            'event_timezone' => 'Europe/Berlin',
        ]);

        $seriesEvent = DomainOutboxEvent::query()
            ->where('event_name', 'organization.event.series_created.v1')
            ->firstOrFail();
        $this->assertSame(3, $seriesEvent->payload['occurrence_count']);

        $this->assertSame([
            '2026-01-31 18:30',
            '2026-02-28 18:30',
            '2026-03-31 18:30',
        ], $this->localEventStarts());
    }

    public function test_it_models_recurrence_rules_exceptions_and_future_updates_without_rewriting_completed_events(): void
    {
        $this->actingAs(User::factory()->create());

        $first = app(EventService::class)->create([
            'title' => 'Youth practice',
            'type' => 'training',
            'visibility' => 'public',
            'start_time' => '2026-10-01 17:00',
            'end_time' => '2026-10-01 18:00',
            'recurring' => 'daily',
            'recurrence_ends_at' => '2026-10-04',
            'event_timezone' => 'Europe/Berlin',
            'holidays' => ['Tag der Deutschen Einheit'],
            'blackout_windows' => [['starts_at' => '2026-10-02 00:00', 'ends_at' => '2026-10-02 23:59']],
            'seasonal_adjustments' => [['starts_at' => '2026-10-04 16:00', 'name' => 'Winterzeitplan']],
            'recurrence_exceptions' => [
                ['kind' => 'holiday', 'local_date' => '2026-10-03', 'name' => 'Tag der Deutschen Einheit'],
                ['kind' => 'seasonal_adjustment', 'local_date' => '2026-10-04', 'starts_at' => '2026-10-04 16:00', 'name' => 'Winterzeitplan'],
            ],
        ]);

        $this->assertSame(1, EventRecurrenceSeries::query()->count());
        $this->assertSame(1, EventRecurrenceRuleVersion::query()->count());
        $this->assertSame(2, EventRecurrenceException::query()->count());
        $this->assertSame(['2026-10-01 17:00', '2026-10-02 17:00', '2026-10-03 17:00', '2026-10-04 16:00'], $this->localEventStarts());
        $this->assertSame('seasonal_adjustment', Event::query()->latest('start_time')->firstOrFail()->recurrence_exception_kind);

        $first->forceFill(['completed_at' => now()])->save();
        app(EventService::class)->update($first, [
            'update_scope' => 'future_series',
            'effective_from' => '2026-10-01 00:00',
            'title' => 'Youth practice updated',
            'event_timezone' => 'Europe/Berlin',
        ]);

        $this->assertSame(['Youth practice', 'Youth practice updated', 'Youth practice updated', 'Youth practice updated'], Event::query()->orderBy('start_time')->pluck('title')->all());
    }

    private function localEventStarts(): array
    {
        return Event::query()
            ->orderBy('start_time')
            ->get()
            ->map(fn (Event $event) => $event->start_time->timezone('Europe/Berlin')->format('Y-m-d H:i'))
            ->all();
    }
}
