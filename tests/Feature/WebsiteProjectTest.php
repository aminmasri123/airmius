<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Role;
use App\Models\User;
use App\Services\WebsiteRequestService;
use App\Support\AdminTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebsiteProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_session_can_read_and_comment_on_its_project(): void
    {
        $owner = User::factory()->create();
        $project = app(WebsiteRequestService::class)->createForUser($owner, ['goals' => 'Neue Website']);
        $show = route('auth.commerce.website-project.show', $project);
        $send = route('auth.commerce.website-project.messages', $project);
        $this->actingAs($owner)->getJson($show)->assertOk();
        $this->postJson($send, ['body' => 'Frage aus dem Web', 'request_key' => 'web-1'])->assertOk();
        $this->getJson($show)->assertJsonPath('messages.data.0.body', 'Frage aus dem Web');
        $this->actingAs(User::factory()->create())->getJson($show)->assertForbidden();
    }

    public function test_web_admin_uses_session_confirmation_instead_of_mobile_token(): void
    {
        $owner = User::factory()->create();
        $project = app(WebsiteRequestService::class)->createForUser($owner, []);
        $admin = User::factory()->create(['two_factor_secret' => 'test', 'two_factor_confirmed_at' => now()]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $url = route('auth.commerce.website-project.messages', $project);
        $this->actingAs($admin)->getJson(route('auth.commerce.website-project.show', $project))->assertOk();
        $this->postJson($url, ['body' => 'Antwort', 'request_key' => 'web-admin'])->assertForbidden();
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])->postJson($url, ['body' => 'Antwort', 'request_key' => 'web-admin'])->assertOk();
    }

    public function test_club_owner_and_stepped_up_admin_share_project_but_outsiders_cannot(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $project = app(WebsiteRequestService::class)->createForUser($owner, ['club_id' => $club->id]);
        $url = '/api/v1/commerce/website-requests/'.$project->id;
        Sanctum::actingAs($owner);
        $this->getJson($url)->assertOk();
        $this->patchJson('/api/v1/admin/commerce/website-requests/'.$project->id, ['status' => 'done'])->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        $admin = User::factory()->create(['two_factor_secret' => 'test', 'two_factor_confirmed_at' => now()]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
        $this->getJson($url)->assertOk();
        $this->postJson($url.'/messages', ['body' => 'Bitte Logo senden', 'request_key' => 'admin-1'])->assertOk();
        $this->patchJson('/api/v1/admin/commerce/website-requests/'.$project->id, ['status' => 'contacted'])->assertOk();
        Sanctum::actingAs($owner);
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'contacted')->assertJsonCount(2, 'messages.data');
    }

    public function test_requester_can_read_and_comment_but_other_users_cannot(): void
    {
        $owner = User::factory()->create();
        $project = app(WebsiteRequestService::class)->createForUser($owner, [
            'project_title' => 'Neue Vereinswebsite', 'goals' => 'Mitglieder gewinnen',
            'target_audience' => 'Familien', 'inspiration_urls' => ['https://example.org'],
        ]);
        $url = '/api/v1/commerce/website-requests/'.$project->id;
        Sanctum::actingAs($owner);
        $this->getJson($url)->assertOk()->assertJsonPath('data.inspiration_urls.0', 'https://example.org');
        $body = ['body' => 'Können wir Termine anzeigen?', 'request_key' => 'retry-1'];
        $this->postJson($url.'/messages', $body)->assertOk();
        $this->postJson($url.'/messages', $body)->assertOk();
        $this->assertSame(1, $project->messages()->count());
        $this->postJson($url.'/messages', [...$body, 'body' => 'Anderer Text'])->assertConflict();
        $this->postJson($url.'/messages', ['body' => ' ', 'request_key' => 'empty'])->assertUnprocessable();
        $this->getJson($url)->assertOk()->assertJsonPath('messages.data.0.body', $body['body']);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        $this->postJson($url.'/messages', ['body' => 'Fremd', 'request_key' => 'other'])->assertForbidden();
    }

    public function test_status_changes_have_a_single_timeline_entry_and_preserve_the_brief(): void
    {
        $owner = User::factory()->create();
        $service = app(WebsiteRequestService::class);
        $project = $service->createForUser($owner, ['notes' => 'Logo behalten']);
        $service->updateWorkflow($project, $owner, ['status' => 'contacted']);
        $service->updateWorkflow($project, $owner, ['status' => 'contacted']);
        $this->assertSame('Logo behalten', $project->fresh()->notes);
        $this->assertSame(1, $project->messages()->count());
        $this->assertSame('contacted', $project->messages()->sole()->status);
        $project->delete();
        $this->assertDatabaseCount('website_request_messages', 0);
    }

    public function test_inspirations_only_accept_up_to_five_web_links(): void
    {
        foreach ([['javascript:alert(1)'], ['file:///etc/passwd'], array_fill(0, 6, 'https://example.org')] as $links) {
            $this->assertTrue(Validator::make([
                'accepted_privacy' => true, 'inspiration_urls' => $links,
            ], WebsiteRequestService::authenticatedRules())->fails());
        }
        $this->assertFalse(Validator::make([
            'accepted_privacy' => true, 'inspiration_urls' => ['https://example.org'],
        ], WebsiteRequestService::authenticatedRules())->fails());
    }
}
