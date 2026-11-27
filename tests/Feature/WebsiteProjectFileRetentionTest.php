<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteRequest;
use App\Services\UserDataErasureService;
use App\Services\UserPrivacyExportService;
use App\Services\WebsiteRequestService;
use App\Support\UploadStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebsiteProjectFileRetentionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Exercise real commit/rollback callbacks in the isolated test database.
        // A surrounding RefreshDatabase transaction would defer all deletions.
        config([
            'database.default' => 'website_project_retention',
            'database.connections.website_project_retention' => [
                ...config('database.connections.sqlite'), 'database' => ':memory:',
            ],
        ]);
        $this->artisan('migrate:fresh', ['--database' => 'website_project_retention'])->assertSuccessful();
    }

    private function attach(WebsiteRequest $project, User $author, string $name): string
    {
        $path = UploadStorage::privatePath('website-projects/'.$project->id.'/'.$name);
        Storage::disk(UploadStorage::privateDisk())->put($path, 'private plan');
        $project->messages()->create(['user_id' => $author->id, 'body' => 'Plan', 'kind' => 'comment'])
            ->attachments()->create(['name' => $name, 'path' => $path, 'disk' => UploadStorage::privateDisk(),
                'mime_type' => 'application/pdf', 'size' => 12, 'sha256' => hash('sha256', 'private plan')]);

        return $path;
    }

    public function test_retention_and_privacy_deletion_remove_files_only_after_commit(): void
    {
        Storage::fake(UploadStorage::privateDisk());
        $owner = User::factory()->create();
        $service = app(WebsiteRequestService::class);
        $project = $service->createForUser($owner, []);
        $path = $this->attach($project, $owner, 'plan.pdf');
        try {
            DB::transaction(function () use ($project) {
                $project->delete();
                throw new \RuntimeException('Rollback');
            });
        } catch (\RuntimeException) {
            // Failed transactions must retain their files and conversation.
        }
        Storage::disk(UploadStorage::privateDisk())->assertExists($path);
        $this->assertDatabaseCount('website_request_attachments', 1);
        WebsiteRequest::findOrFail($project->id)->update(['retention_expires_at' => now()->subDay()]);
        $this->artisan('airmius:prune-website-requests')->assertSuccessful();
        Storage::disk(UploadStorage::privateDisk())->assertMissing($path);
        $this->assertDatabaseCount('website_request_attachments', 0);

        $other = User::factory()->create();
        $shared = $service->createForUser($other, []);
        $sharedPath = $this->attach($shared, $owner, 'design.pdf');
        $own = $service->createForUser($owner, []);
        $ownPath = $this->attach($own, $owner, 'own.pdf');
        $export = app(UserPrivacyExportService::class)->export($owner);
        $this->assertSame('design.pdf', data_get($export, 'agency.messages.0.attachments.0.name'));
        $this->assertStringNotContainsString('private-files', json_encode($export['agency']));
        app(UserDataErasureService::class)->erase($owner, ['commerce']);
        Storage::disk(UploadStorage::privateDisk())->assertMissing($sharedPath);
        Storage::disk(UploadStorage::privateDisk())->assertMissing($ownPath);
        $this->assertDatabaseHas('website_requests', ['id' => $shared->id]);
        $this->assertDatabaseMissing('website_requests', ['id' => $own->id]);
        $lastPath = $this->attach($shared, $owner, 'last.pdf');
        $owner->delete();
        Storage::disk(UploadStorage::privateDisk())->assertMissing($lastPath);
        $this->assertDatabaseCount('website_request_attachments', 0);
    }
}
