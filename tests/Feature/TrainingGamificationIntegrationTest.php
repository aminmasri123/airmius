<?php

namespace Tests\Feature;

use App\Events\DomainEventPublished;
use App\Jobs\PublishDomainOutboxEvent;
use App\Models\DomainOutboxEvent;
use App\Models\GamificationRule;
use App\Models\GamificationXpEvent;
use App\Models\Notification;
use App\Models\SportRouteTrack;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingGamificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_verified_plan_completion_awards_idempotent_privacy_safe_xp_through_the_outbox(): void
    {
        $athlete = User::factory()->create(['trust_score' => 100, 'language' => 'ar']);
        $plan = TrainingPlan::query()->create([
            'created_by' => $athlete->id,
            'title' => 'Sicherer Trainingsplan',
            'cadence' => 'single',
            'status' => 'published',
            'share_permission' => 'read',
        ]);
        $item = $plan->items()->create([
            'title' => 'Planlauf',
            'scheduled_at' => now(),
            'sort_order' => 1,
        ]);
        $plan->assignments()->create([
            'user_id' => $athlete->id,
            'permission' => 'read',
        ]);

        $this->actingAs($athlete)->post(route('auth.training.logs.store'), [
            'training_plan_item_id' => $item->id,
            'title' => 'Privates Tempotraining',
            'sport_type' => 'laufen',
            'status' => 'completed',
            'performed_at' => now(),
            'duration_minutes' => 48,
            'distance_km' => 8.2,
            'calories' => 610,
            'notes' => 'Persönliche Gesundheitsnotiz',
            'privacy_scope' => 'private',
            'wellness' => ['pain' => 4, 'sleep_hours' => 5.5],
            'notify_people' => false,
        ])->assertRedirect();

        $log = TrainingLog::query()->latest('id')->firstOrFail();

        $outbox = DomainOutboxEvent::query()
            ->where('event_name', 'training.log.completed.v1')
            ->firstOrFail();

        $this->assertSame(['verification' => 'training_plan'], $outbox->payload);
        $this->assertSame(['users' => [$athlete->id]], $outbox->audience);
        $this->assertSame((string) $log->id, $outbox->aggregate_id);

        $serializedPayload = json_encode($outbox->payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('notes', $serializedPayload);
        $this->assertStringNotContainsString('wellness', $serializedPayload);
        $this->assertStringNotContainsString('duration', $serializedPayload);
        $this->assertStringNotContainsString('distance', $serializedPayload);
        $this->assertStringNotContainsString('calories', $serializedPayload);

        (new PublishDomainOutboxEvent($outbox->id))->handle();

        $completion = GamificationXpEvent::query()
            ->where('reason', 'training_completed')
            ->firstOrFail();

        $this->assertSame(8, $completion->amount);
        $this->assertSame(['verification' => 'training_plan'], $completion->meta);
        $this->assertSame(TrainingLog::class, $completion->source_type);
        $this->assertSame($log->id, $completion->source_id);
        $this->assertSame(100, $athlete->refresh()->trust_score);
        $this->assertDatabaseHas('gamification_xp_events', [
            'user_id' => $athlete->id,
            'reason' => 'daily_meaningful_activity',
            'amount' => 2,
        ]);

        $notification = Notification::query()
            ->where('user_id', $athlete->id)
            ->where('type', 'training.gamification.completed')
            ->firstOrFail();

        $this->assertSame('ar', $notification->data['locale']);
        $this->assertSame('gamification.notifications.training_completed_title', $notification->data['i18n']['title_key']);
        $this->assertSame('gamification.notifications.training_completed_body', $notification->data['i18n']['body_key']);
        $this->assertSame(8, $notification->data['i18n']['replace']['xp']);
        $this->assertStringContainsString('8', $notification->data['title']);

        event(new DomainEventPublished($outbox->envelope()));

        $this->assertSame(1, GamificationXpEvent::query()->where('reason', 'training_completed')->count());
        $this->assertSame(1, Notification::query()->where('type', 'training.gamification.completed')->count());
    }

    public function test_unverified_future_and_foreign_gps_logs_do_not_award_completion_xp(): void
    {
        $athlete = User::factory()->create();
        $other = User::factory()->create();

        TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'title' => 'Spontaner Freitext',
            'status' => 'completed',
            'performed_at' => now(),
            'metrics' => ['privacy_scope' => 'private'],
        ]);

        $plan = TrainingPlan::query()->create([
            'created_by' => $athlete->id,
            'title' => 'Zukunftsplan',
            'cadence' => 'single',
            'status' => 'published',
            'share_permission' => 'read',
        ]);
        $futureItem = $plan->items()->create([
            'title' => 'Einheit morgen',
            'scheduled_at' => now()->addDay(),
            'sort_order' => 1,
        ]);
        TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'training_plan_id' => $plan->id,
            'training_plan_item_id' => $futureItem->id,
            'title' => 'Zu früh abgeschlossen',
            'status' => 'completed',
            'performed_at' => now()->addDay(),
        ]);

        $foreignTrack = $this->completedTrack($other, 'Fremder GPS-Track');
        $foreignLog = TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'sport_route_track_id' => $foreignTrack->id,
            'title' => 'Nicht eigener GPS-Track',
            'status' => 'completed',
            'performed_at' => now(),
        ]);

        $foreignOutbox = DomainOutboxEvent::query()
            ->where('event_name', 'training.log.completed.v1')
            ->where('aggregate_id', (string) $foreignLog->id)
            ->firstOrFail();

        (new PublishDomainOutboxEvent($foreignOutbox->id))->handle();

        $this->assertSame(1, DomainOutboxEvent::query()->where('event_name', 'training.log.completed.v1')->count());
        $this->assertDatabaseMissing('gamification_xp_events', [
            'user_id' => $athlete->id,
            'reason' => 'training_completed',
        ]);
    }

    public function test_owned_completed_gps_tracks_award_once_per_day_and_record_the_daily_cap(): void
    {
        $athlete = User::factory()->create(['trust_score' => 100]);
        Sanctum::actingAs($athlete);

        $first = $this->gpsTrainingLog($athlete, 'Morgenrunde');
        $second = $this->gpsTrainingLog($athlete, 'Abendrunde');

        foreach ([$first, $second] as $log) {
            $outbox = DomainOutboxEvent::query()
                ->where('event_name', 'training.log.completed.v1')
                ->where('aggregate_id', (string) $log->id)
                ->firstOrFail();

            (new PublishDomainOutboxEvent($outbox->id))->handle();
        }

        $events = GamificationXpEvent::query()
            ->where('user_id', $athlete->id)
            ->where('reason', 'training_completed')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $events);
        $this->assertSame([8, 0], $events->pluck('amount')->all());
        $this->assertFalse($events[0]->limited_by_daily_cap);
        $this->assertTrue($events[1]->limited_by_daily_cap);
        $this->assertSame('verified_gps_track', $events[0]->meta['verification']);
        $this->assertSame('daily_limit', $events[1]->meta['ignored_reason']);
        $this->assertSame([$first->id, $second->id], $events->pluck('source_id')->all());
        $this->assertSame(100, $athlete->refresh()->trust_score);
        $this->assertSame(10, app(GamificationService::class)->summaryFor($athlete)['xp']);
        $this->assertSame(1, Notification::query()->where('type', 'training.gamification.completed')->count());
    }

    public function test_training_completion_notification_catalogs_have_key_and_placeholder_parity(): void
    {
        $keys = [
            'gamification.notifications.training_completed_title',
            'gamification.notifications.training_completed_body',
        ];

        foreach ($keys as $key) {
            $german = trans($key, locale: 'de');
            preg_match_all('/:[a-z_][a-z0-9_]*/i', $german, $germanPlaceholders);

            foreach (['en', 'fr', 'ar'] as $locale) {
                $translated = trans($key, locale: $locale);
                preg_match_all('/:[a-z_][a-z0-9_]*/i', $translated, $translatedPlaceholders);

                $this->assertNotSame($key, $translated, "Missing {$locale} translation for {$key}.");
                $this->assertSame(
                    $germanPlaceholders[0],
                    $translatedPlaceholders[0],
                    "Placeholder mismatch for {$locale}:{$key}.",
                );
            }
        }
    }

    public function test_training_completion_rule_migration_is_reversible_and_preserves_the_privacy_contract(): void
    {
        $path = database_path('migrations/2026_08_09_000005_connect_training_completion_to_gamification.php');
        $migration = require $path;

        $migration->down();

        $this->assertDatabaseMissing('gamification_rules', [
            'key' => 'training_completed',
            'actor_type' => 'sportler',
        ]);

        $athlete = User::factory()->create(['trust_score' => 100]);
        $source = TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'title' => 'Fallback-Prüfung',
            'status' => 'completed',
            'performed_at' => now(),
        ]);
        $fallbackReward = app(GamificationService::class)->grant(
            $athlete,
            'training_completed',
            $source,
        );

        $this->assertSame(8, $fallbackReward?->amount);
        $this->assertSame(100, $athlete->refresh()->trust_score);

        $migration->up();

        $rule = GamificationRule::query()
            ->where('key', 'training_completed')
            ->where('actor_type', 'sportler')
            ->firstOrFail();

        $this->assertSame(8, $rule->xp_amount);
        $this->assertSame(1, $rule->daily_limit);
        $this->assertSame(0, $rule->trust_delta);
        $this->assertSame(
            ['notes', 'wellness', 'calories', 'body_metrics'],
            $rule->meta['excludes'],
        );
    }

    private function gpsTrainingLog(User $athlete, string $title): TrainingLog
    {
        $track = $this->completedTrack($athlete, $title.' GPS');

        $response = $this->postJson('/api/v1/training/logs', [
            'sport_route_track_id' => $track->id,
            'title' => $title,
            'sport_type' => 'laufen',
            'status' => 'completed',
            'performed_at' => now()->toIso8601String(),
            'privacy_scope' => 'private',
        ])->assertCreated();

        return TrainingLog::query()->findOrFail($response->json('data.id'));
    }

    private function completedTrack(User $owner, string $title): SportRouteTrack
    {
        return SportRouteTrack::query()->create([
            'user_id' => $owner->id,
            'title' => $title,
            'sport_type' => 'laufen',
            'status' => 'completed',
            'source' => 'gps',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'distance_meters' => 7000,
            'duration_seconds' => 3600,
            'track_points' => [],
            'track_geometry' => [],
        ]);
    }
}
