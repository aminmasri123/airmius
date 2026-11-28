<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteRequestAttachment;
use App\Services\WebsiteProjectAccess;
use App\Services\WebsiteRequestService;
use App\Support\AdminTwoFactor;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebsiteProjectCollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function test_detailed_agency_brief_is_stored_and_only_visible_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = app(WebsiteRequestService::class)->createForUser($owner, [
            'request_type' => 'campaign',
            'project_title' => 'Saisonkampagne',
            'goals' => 'Neue Mitglieder gewinnen',
            'target_audience' => 'Jugendliche und Eltern',
            'deliverables' => ['strategy', 'banner', 'social_post'],
            'channels' => ['instagram', 'website'],
            'budget_range' => '500–1.000 EUR',
            'desired_deadline' => now()->addMonth()->toDateString(),
            'brand_guidelines' => 'Blau, sachlich und barrierearm',
            'contact_preference' => 'app',
        ]);

        $this->assertSame('agency_brief', $project->package);
        $this->assertSame(['strategy', 'banner', 'social_post'], $project->deliverables);
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/commerce/website-requests/'.$project->id)
            ->assertOk()
            ->assertJsonPath('data.request_type', 'campaign')
            ->assertJsonPath('data.budget_range', '500–1.000 EUR')
            ->assertJsonPath('data.contact_preference', 'app');
        Sanctum::actingAs($stranger);
        $this->getJson('/api/v1/commerce/website-requests/'.$project->id)->assertForbidden();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['two_factor_secret' => 'test', 'two_factor_confirmed_at' => now()]);
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $user;
    }

    public function test_owner_edits_before_processing_but_cannot_unlock_a_started_project(): void
    {
        $owner = User::factory()->create();
        $service = app(WebsiteRequestService::class);
        $project = $service->createForUser($owner, ['goals' => 'Alt']);
        $url = '/api/v1/commerce/website-requests/'.$project->id;
        Sanctum::actingAs($owner);
        foreach (['new', 'contacted', 'quoted'] as $status) {
            $service->updateWorkflow($project, $this->admin(), ['status' => $status]);
            $this->patchJson($url, ['goals' => 'Neu '.$status, 'club_id' => null, 'user_id' => 999])->assertOk();
            $this->assertSame($owner->id, $project->fresh()->user_id);
        }
        $service->updateWorkflow($project, $this->admin(), ['status' => 'in_progress']);
        $this->getJson($url)->assertJsonPath('data.can_edit', false)->assertJsonPath('data.can_comment', true);
        $this->patchJson($url, ['goals' => 'Zu spät'])->assertConflict();
        $service->updateWorkflow($project, $this->admin(), ['status' => 'contacted']);
        $this->patchJson($url, ['goals' => 'Noch zu spät'])->assertConflict();
        Sanctum::actingAs(User::factory()->create());
        $this->patchJson($url, ['goals' => 'Fremd'])->assertForbidden();
        $this->postJson($url.'/withdraw')->assertForbidden();
    }

    public function test_withdrawal_keeps_history_is_idempotent_and_cannot_be_reopened(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin();
        $service = app(WebsiteRequestService::class);
        $project = $service->createForUser($owner, []);
        $service->updateWorkflow($project, $admin, ['status' => 'in_progress']);
        $url = '/api/v1/commerce/website-requests/'.$project->id;
        Sanctum::actingAs($owner);
        $this->postJson($url.'/messages', ['body' => 'Bestehender Verlauf', 'request_key' => 'first'])->assertOk();
        $this->postJson($url.'/withdraw')->assertOk();
        $count = $project->messages()->count();
        $this->postJson($url.'/withdraw')->assertOk();
        $this->assertSame($count, $project->messages()->count());
        $this->getJson($url)->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_comment', false);
        $this->postJson($url.'/messages', ['body' => 'Nach Abschluss', 'request_key' => 'second'])->assertConflict();
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $this->patchJson('/api/v1/admin/commerce/website-requests/'.$project->id, ['status' => 'in_progress'])->assertConflict();
        $this->assertDatabaseHas('website_request_messages', ['body' => 'Bestehender Verlauf']);
    }

    public function test_draft_is_private_until_submitted_and_cannot_be_restored_after_submission(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $project = app(WebsiteRequestService::class)->createForUser($owner, ['status' => 'draft']);
        $url = '/api/v1/commerce/website-requests/'.$project->id;
        $this->assertDatabaseCount('notifications', 0);
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs($owner);
        $this->patchJson($url, ['project_title' => 'Entwurf'])->assertOk();
        $this->postJson($url.'/messages', ['body' => 'Noch privat', 'request_key' => 'draft'])->assertConflict();
        $this->patchJson($url, ['status' => 'new'])->assertOk();
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'club.website_project.updated']);
        $this->patchJson($url, ['status' => 'draft'])->assertConflict();
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $this->getJson($url)->assertOk();
    }

    public function test_withdrawn_draft_is_never_exposed_to_administration(): void
    {
        $owner = User::factory()->create();
        $project = app(WebsiteRequestService::class)->createForUser($owner, ['status' => 'draft']);
        Sanctum::actingAs($owner);
        $url = '/api/v1/commerce/website-requests/'.$project->id;
        $this->postJson($url.'/withdraw')->assertOk();
        $this->patchJson($url, ['status' => 'new'])->assertConflict();
        Sanctum::actingAs($this->admin(), ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $this->getJson($url)->assertForbidden();
    }

    public function test_creation_endpoint_saves_a_club_draft_without_notifying_admins(): void
    {
        $this->admin();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/commerce/seller/website-requests', [
            'club_id' => $club->id, 'status' => 'draft', 'goals' => 'Website planen', 'accepted_privacy' => true,
        ])->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_both_sides_receive_private_notifications_linked_to_the_project(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $club->users()->attach($member, ['role' => 'member', 'membership_status' => 'active']);
        $project = app(WebsiteRequestService::class)->createForUser($owner, ['club_id' => $club->id]);
        Notification::query()->delete();
        $url = '/api/v1/commerce/website-requests/'.$project->id;
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $payload = ['body' => 'Vertraulicher Projekttext', 'request_key' => 'admin-comment'];
        $this->postJson($url.'/messages', $payload)->assertOk();
        $this->postJson($url.'/messages', $payload)->assertOk();
        $notice = Notification::sole();
        $this->assertSame($owner->id, $notice->user_id);
        $this->assertSame('airmius://website-projects/'.$project->id, $notice->data['mobile_url']);
        $this->assertSame('/commerce/website-projects/'.$project->id, $notice->data['url']);
        $this->assertStringNotContainsString('Vertraulicher', json_encode($notice->data));
        Sanctum::actingAs($member);
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs($owner);
        $this->postJson($url.'/messages', ['body' => 'Antwort', 'request_key' => 'reply'])->assertOk();
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'club.website_project.comment']);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_multiple_private_attachments_survive_reload_and_retries_check_file_bytes(): void
    {
        Storage::fake(UploadStorage::privateDisk());
        $owner = User::factory()->create();
        $project = app(WebsiteRequestService::class)->createForUser($owner, []);
        Sanctum::actingAs($owner);
        $url = '/api/v1/commerce/website-requests/'.$project->id;
        $pdf = fn ($text) => UploadedFile::fake()->createWithContent('plan.pdf', "%PDF-1.4\n".$text);
        $payload = fn () => ['body' => '', 'request_key' => 'upload', 'files' => [$pdf('Plan'), UploadedFile::fake()->image('design.png')]];
        $this->post($url.'/messages', $payload(), ['Accept' => 'application/json', 'Idempotency-Key' => 'multipart'])->assertOk();
        $this->post($url.'/messages', $payload(), ['Accept' => 'application/json', 'Idempotency-Key' => 'multipart'])->assertOk();
        $this->assertDatabaseCount('website_request_attachments', 2);
        $changed = $payload();
        $changed['files'][0] = $pdf('Changed');
        $this->post($url.'/messages', $changed, ['Accept' => 'application/json', 'Idempotency-Key' => 'multipart'])->assertConflict();
        $response = $this->getJson($url)->assertJsonCount(2, 'messages.data.0.attachments');
        $link = $response->json('messages.data.0.attachments.0.download_url');
        $this->assertStringNotContainsString('private-files', $response->getContent());
        $this->get($link)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $file = WebsiteRequestAttachment::first();
        Storage::disk($file->disk)->assertExists($file->path);
        $this->get($link.'&actor=999')->assertForbidden();
        $this->travel(6)->minutes();
        $this->get($link)->assertForbidden();
    }

    public function test_upload_validation_and_download_authorization_are_enforced(): void
    {
        Storage::fake(UploadStorage::privateDisk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $project = app(WebsiteRequestService::class)->createForUser($owner, ['club_id' => $club->id]);
        Sanctum::actingAs($owner);
        $url = '/api/v1/commerce/website-requests/'.$project->id;
        foreach ([
            [UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;')],
            array_fill(0, 6, UploadedFile::fake()->image('test.png')),
            [UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')],
        ] as $files) {
            $this->post($url.'/messages', ['request_key' => 'invalid', 'files' => $files], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $this->assertDatabaseCount('website_request_messages', 0);
        $this->post($url.'/messages', ['request_key' => 'valid', 'files' => [UploadedFile::fake()->image('test.png')]], ['Accept' => 'application/json'])->assertOk();
        $link = $this->getJson($url)->json('messages.data.0.attachments.0.download_url');
        $club->update(['owner_id' => User::factory()->create()->id]);
        $club->users()->detach($owner->id);
        $this->get($link)->assertForbidden();
        $this->assertFalse(app(WebsiteProjectAccess::class)->requesterProjects($owner)->whereKey($project->id)->exists());
        $this->assertTrue(app(WebsiteProjectAccess::class)->requesterProjects($club->owner)->whereKey($project->id)->exists());
    }

    public function test_web_edit_withdraw_and_notification_destination_use_the_same_access_rules(): void
    {
        $owner = User::factory()->create();
        $project = app(WebsiteRequestService::class)->createForUser($owner, []);
        $this->actingAs($owner)->get('/commerce/website-projects/'.$project->id)->assertOk();
        $this->patchJson(route('auth.commerce.website-project.update', $project), ['goals' => 'Web-Update'])->assertOk();
        $this->postJson(route('auth.commerce.website-project.withdraw', $project))->assertOk();
        $this->patchJson(route('auth.commerce.website-project.update', $project), ['goals' => 'Gesperrt'])->assertConflict();
    }
}
