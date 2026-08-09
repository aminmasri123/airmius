<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\File;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EventFileContextWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_detail_exposes_only_bounded_protected_file_references(): void
    {
        [$owner, $member, $outsider, $event] = $this->teamEventFixture();
        $this->grantUserPermissions($owner, ['file.upload']);

        foreach (range(1, 8) as $index) {
            File::query()->create([
                'user_id' => $owner->id,
                'event_id' => $event->id,
                'path' => "private/events/{$event->id}/document-{$index}.pdf",
                'thumbnail_path' => "private/events/{$event->id}/document-{$index}.jpg",
                'display_name' => "Dokument {$index}.pdf",
                'type' => 'application/pdf',
                'size' => $index * 1024,
            ]);
        }

        $this->actingAs($member)
            ->get(route('auth.events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Events/Show')
                ->where('fileContext.count', 8)
                ->has('fileContext.files', 6)
                ->where('fileContext.can_upload', false)
                ->where('fileContext.files.0.preview_url', fn ($url) => str_contains($url, '/files/'))
                ->missing('fileContext.files.0.path')
                ->missing('fileContext.files.0.thumbnail_path')
                ->missing('fileContext.files.0.url'));

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.files_count', 8)
            ->assertJsonCount(6, 'data.file_context.files')
            ->assertJsonPath('data.file_context.can_upload', false)
            ->assertJsonMissingPath('data.file_context.files.0.path')
            ->assertJsonMissingPath('data.file_context.files.0.thumbnail_path')
            ->assertJsonMissingPath('data.file_context.files.0.url');

        $this->getJson("/api/v1/files?scope=event&event_id={$event->id}")
            ->assertOk()
            ->assertJsonPath('data.scope.event_id', $event->id)
            ->assertJsonPath('data.capabilities.upload', false)
            ->assertJsonPath('data.capabilities.create_folder', false);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/events/{$event->id}")->assertNotFound();
        $this->getJson("/api/v1/files?scope=event&event_id={$event->id}")->assertNotFound();
    }

    public function test_event_owner_has_consistent_file_scope_and_authorized_preview_access(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Persönliche Leistungsanalyse',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);
        $file = File::query()->create([
            'user_id' => $owner->id,
            'event_id' => $event->id,
            'path' => 'private/events/performance.pdf',
            'display_name' => 'performance.pdf',
            'type' => 'application/pdf',
            'size' => 128,
        ]);
        Storage::disk(UploadStorage::disk())->put($file->path, 'protected-event-file');

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/files?scope=event&event_id={$event->id}")
            ->assertOk()
            ->assertJsonPath('data.files.0.id', $file->id);
        $preview = $this->getJson("/api/v1/files/{$file->id}/preview")
            ->assertOk();
        $this->assertStringContainsString('private', (string) $preview->headers->get('cache-control'));
        $this->assertStringContainsString('max-age=86400', (string) $preview->headers->get('cache-control'));

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/files/{$file->id}/preview")->assertForbidden();
    }

    public function test_flutter_event_context_opens_files_and_prefilled_training_log_natively(): void
    {
        $models = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_api_models.dart'));
        $files = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/file_manager_screen.dart'));
        $detail = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/training_event_detail_screen.dart'));
        $training = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/training_plans_logs_screen.dart'));
        $translations = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_l10n.dart'));

        $this->assertStringContainsString('final int filesCount;', $models);
        $this->assertStringContainsString('this.initialEventId', $files);
        $this->assertStringContainsString("_UploadDestination(scope: 'event'", $files);
        $this->assertStringContainsString("initialScope: 'event'", $detail);
        $this->assertStringContainsString('createLogOnOpen: true', $detail);
        $this->assertStringContainsString('prefillSportRouteId', $training);
        $this->assertSame(4, substr_count($translations, "'events.files':"));
        $this->assertSame(4, substr_count($translations, "'events.documentTraining':"));
    }

    /** @return array{User, User, User, Event} */
    private function teamEventFixture(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($owner->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($member->id, ['role' => TeamRoles::PLAYER]);

        $event = Event::query()->create([
            'user_id' => $owner->id,
            'team_id' => $team->id,
            'title' => 'Teamtraining mit Unterlagen',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);

        return [$owner, $member, $outsider, $event];
    }

    private function grantUserPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
