<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrainingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_athlete_can_document_private_plan_training_with_media_without_notifying_trainer(): void
    {
        Storage::fake('public');

        [$trainer, $athlete, $plan, $item] = $this->assignedTrainingPlan();

        $response = $this->actingAs($athlete)->post(route('auth.training.logs.store'), [
            'training_plan_item_id' => $item->id,
            'title' => 'Private Krafteinheit',
            'sport_type' => 'gym',
            'status' => 'completed',
            'privacy_scope' => 'private',
            'performed_at' => now()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'notify_people' => true,
            'wellness' => [
                'rpe' => 8,
                'energy' => 6,
                'pain' => 1,
                'sleep_hours' => 7.5,
            ],
            'entries' => [
                [
                    'title' => '',
                ],
                [
                    'title' => 'Kniebeuge - Satz 1',
                    'sets' => 1,
                    'reps' => 8,
                    'weight_kg' => 80,
                    'media_file' => UploadedFile::fake()->image('technik.jpg'),
                ],
            ],
        ]);

        $response->assertRedirect();

        $log = TrainingLog::query()->with('entries')->latest('id')->firstOrFail();

        $this->assertSame($athlete->id, $log->user_id);
        $this->assertSame('private', $log->metrics['privacy_scope']);
        $this->assertCount(1, $log->entries);
        $this->assertSame('Kniebeuge - Satz 1', $log->entries->first()->title);
        $this->assertNotEmpty($log->entries->first()->metrics['media_path'] ?? null);
        Storage::disk('public')->assertExists($log->entries->first()->metrics['media_path']);

        $this->assertFalse(Notification::query()
            ->where('user_id', $trainer->id)
            ->where('type', 'training.log.saved')
            ->exists());
    }

    public function test_private_training_log_is_hidden_from_trainer_even_when_plan_was_assigned(): void
    {
        [$trainer, $athlete, $plan, $item] = $this->assignedTrainingPlan();

        $log = TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'trainer_id' => $trainer->id,
            'training_plan_id' => $plan->id,
            'training_plan_item_id' => $item->id,
            'title' => 'Privates Training',
            'sport_type' => 'laufen',
            'status' => 'completed',
            'performed_at' => now(),
            'metrics' => ['privacy_scope' => 'private'],
        ]);

        $this->actingAs($trainer)
            ->get(route('auth.training.logs.show', $log))
            ->assertForbidden();
    }

    public function test_plan_item_can_be_rescheduled_and_notifies_assigned_athlete(): void
    {
        [$trainer, $athlete, $plan, $item] = $this->assignedTrainingPlan(permission: 'write');
        $athlete->forceFill(['language' => 'ar'])->save();

        $scheduledAt = now()->addDays(3)->setTime(18, 30);

        $this->actingAs($trainer)
            ->put(route('auth.training.plans.items.update', [$plan, $item]), [
                'title' => $item->title,
                'sport_type' => 'laufen',
                'description' => 'Neue Belastungssteuerung',
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i'),
                'duration_minutes' => 75,
                'distance_km' => 12,
                'intensity' => 'mittel',
                'load' => 'medium',
                'focus' => 'Zone 2',
                'todos' => "Einlaufen\nHauptteil\nAuslaufen",
            ])
            ->assertRedirect();

        $item->refresh();

        $this->assertSame('Zone 2', $item->metrics['Fokus']);
        $this->assertSame(12000, $item->distance_meters);
        $this->assertTrue($item->scheduled_at->isSameMinute($scheduledAt));
        $notification = Notification::query()
            ->where('user_id', $athlete->id)
            ->where('type', 'training.plan.changed')
            ->firstOrFail();

        $this->assertSame('ar', $notification->data['locale']);
        $this->assertSame(
            trans('server.training.notifications.item_updated_title', locale: 'ar'),
            $notification->data['title'],
        );
        $this->assertSame(
            trans('server.training.notifications.item_updated_body', [
                'item' => $item->title,
                'plan' => $plan->title,
            ], 'ar'),
            $notification->data['body'],
        );
        $this->assertSame(
            'server.training.notifications.item_updated_title',
            $notification->data['i18n']['title_key'],
        );
    }

    public function test_assigned_athlete_can_open_plan_item_detail_page(): void
    {
        [, $athlete, $plan, $item] = $this->assignedTrainingPlan();

        $this->actingAs($athlete)
            ->get(route('auth.training.plans.items.show', [$plan, $item]))
            ->assertOk()
            ->assertSee($item->title);
    }

    private function assignedTrainingPlan(string $permission = 'read'): array
    {
        $trainer = User::factory()->create();
        $athlete = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $trainer->id]);
        $team = Team::factory()->create(['club_id' => $club->id, 'sport_type' => 'laufen']);

        $team->users()->attach($trainer->id, ['role' => 'Trainer']);
        $team->users()->attach($athlete->id, ['role' => 'Player']);

        $plan = TrainingPlan::query()->create([
            'created_by' => $trainer->id,
            'team_id' => $team->id,
            'title' => '10k Aufbau',
            'description' => 'Progressiver Trainingsplan',
            'cadence' => 'weekly',
            'status' => 'published',
            'share_permission' => $permission,
            'settings' => ['goal' => '10 km stabil laufen'],
        ]);

        $plan->assignments()->create([
            'user_id' => $athlete->id,
            'permission' => $permission,
        ]);

        $item = $plan->items()->create([
            'title' => 'Long Run',
            'sport_type' => 'laufen',
            'description' => 'Locker und sauber laufen.',
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
            'distance_meters' => 10000,
            'intensity' => 'mittel',
            'todos' => ['Einlaufen', 'Hauptteil', 'Auslaufen'],
            'metrics' => ['Woche' => 1, 'Belastung' => 'medium', 'Fokus' => 'Grundlage'],
            'sort_order' => 1,
        ]);

        return [$trainer, $athlete, $plan, $item];
    }
}
