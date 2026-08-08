<?php

namespace Tests\Feature;

use App\Events\DomainEventPublished;
use App\Jobs\PublishDomainOutboxEvent;
use App\Models\DomainOutboxEvent;
use App\Models\User;
use App\Services\DomainEventPublisher;
use App\Support\Authorization\AuthorizationContext;
use App\Support\Privacy\DataClassification;
use App\Support\Privacy\ProcessingPurpose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformDeliveryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_success_responses_expose_contract_and_request_id_headers(): void
    {
        $response = $this->withHeader('X-Request-Id', 'foundation-test-request')
            ->getJson('/api/v1/meta')
            ->assertOk();

        $response->assertHeader('X-Airmius-Api-Version', 'v1');
        $response->assertHeader('X-Airmius-Contract', '2026-08-08');
        $response->assertHeader('X-Request-Id', 'foundation-test-request');
    }

    public function test_authenticated_api_mutations_are_replayed_for_the_same_idempotency_key(): void
    {
        $user = User::factory()->create(['language' => 'de']);
        Sanctum::actingAs($user);

        $headers = ['Idempotency-Key' => 'language-change-0001'];
        $first = $this->withHeaders($headers)
            ->patchJson('/api/v1/me/language', ['language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.language', 'en');

        $second = $this->withHeaders($headers)
            ->patchJson('/api/v1/me/language', ['language' => 'en'])
            ->assertOk()
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('data.language', 'en');

        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('api_idempotency_keys', 1);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/me/language', ['language' => 'fr'])
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_payload_conflict');

        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_invalid_idempotency_keys_use_the_versioned_error_contract(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->withHeader('Idempotency-Key', 'bad key')
            ->patchJson('/api/v1/me/language', ['language' => 'en'])
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_idempotency_key')
            ->assertJsonStructure([
                'error' => ['code', 'message'],
                'meta' => ['api_version', 'contract_version', 'request_id'],
            ]);
    }

    public function test_training_routes_publish_their_processing_purpose(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/training/plans')
            ->assertOk()
            ->assertHeader('X-Airmius-Data-Purpose', 'training');
    }

    public function test_authenticated_modules_publish_governance_headers_automatically(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertHeader('X-Airmius-Module', 'events')
            ->assertHeader('X-Airmius-Data-Purpose', 'organization');

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertHeader('X-Airmius-Module', 'notifications')
            ->assertHeader('X-Airmius-Data-Purpose', 'product_operation');
    }

    public function test_web_and_api_language_updates_share_the_same_action_and_domain_event(): void
    {
        Queue::fake();
        $user = User::factory()->create(['language' => 'de']);

        $this->actingAs($user)
            ->from('/settings')
            ->post('/user/language', ['language' => 'fr'])
            ->assertRedirect('/settings');

        $this->assertSame('fr', $user->fresh()->language);
        $webEvent = DomainOutboxEvent::query()
            ->where('event_name', 'identity.user.language_updated.v1')
            ->latest('occurred_at')
            ->firstOrFail();
        $this->assertSame('product_operation', $webEvent->metadata['purpose']);
        $this->assertSame('fr', $webEvent->payload['language']);

        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/me/language', ['language' => 'ar'])
            ->assertOk()
            ->assertHeader('X-Airmius-Data-Purpose', 'product_operation')
            ->assertJsonPath('data.language', 'ar');

        $this->assertDatabaseCount('domain_outbox_events', 2);
    }

    public function test_domain_outbox_can_recover_enqueue_and_publish_an_event(): void
    {
        Queue::fake();
        Event::fake([DomainEventPublished::class]);
        $user = User::factory()->create();

        $event = app(DomainEventPublisher::class)->record(
            'identity.user.profile_updated.v1',
            $user,
            payload: ['changed_fields' => ['language']],
            audience: ['users' => [$user->id]],
        );

        $this->assertDatabaseHas('domain_outbox_events', [
            'id' => $event->id,
            'event_name' => 'identity.user.profile_updated.v1',
            'published_at' => null,
        ]);

        $this->artisan('airmius:dispatch-domain-outbox --limit=20')->assertSuccessful();
        Queue::assertPushed(PublishDomainOutboxEvent::class, fn ($job) => $job->outboxEventId === $event->id);

        (new PublishDomainOutboxEvent($event->id))->handle();

        Event::assertDispatched(DomainEventPublished::class, function ($published) use ($event, $user) {
            return $published->envelope['event_id'] === $event->id
                && $published->envelope['aggregate']['id'] === (string) $user->id
                && $published->envelope['payload']['changed_fields'] === ['language'];
        });
        $this->assertNotNull(DomainOutboxEvent::query()->findOrFail($event->id)->published_at);
    }

    public function test_sensitive_data_rules_are_purpose_bound_and_deny_marketing_by_default(): void
    {
        $user = User::factory()->create();

        $marketing = new AuthorizationContext($user, ProcessingPurpose::Marketing);
        $this->assertFalse($marketing->allowsData(DataClassification::Sensitive, explicitGrant: true));
        $this->assertTrue($marketing->allowsData(DataClassification::Personal, explicitGrant: true));

        $analytics = new AuthorizationContext($user, ProcessingPurpose::Analytics);
        $this->assertFalse($analytics->allowsData(DataClassification::Personal));
        $this->assertTrue($analytics->allowsData(DataClassification::Sensitive, pseudonymized: true));
        $this->assertFalse($analytics->allowsData(DataClassification::HighlySensitive, pseudonymized: true));

        $supportWithoutCase = new AuthorizationContext($user, ProcessingPurpose::Support);
        $this->assertFalse($supportWithoutCase->allowsData(DataClassification::HighlySensitive, explicitGrant: true));

        $supportWithCase = new AuthorizationContext($user, ProcessingPurpose::Support, reasonCode: 'ticket:123');
        $this->assertTrue($supportWithCase->allowsData(DataClassification::HighlySensitive, explicitGrant: true));
    }

    public function test_least_privilege_platform_roles_are_installed_without_full_access(): void
    {
        $engineer = Role::findByName('platform_engineer');
        $security = Role::findByName('security_admin');

        $this->assertTrue($engineer->hasPermissionTo('logs.view'));
        $this->assertTrue($engineer->hasPermissionTo('api.manage'));
        $this->assertFalse($engineer->permissions->contains('name', 'system.manage'));
        $this->assertFalse($engineer->permissions->contains('name', 'users.view'));

        $this->assertTrue($security->hasPermissionTo('security.manage'));
        $this->assertTrue($security->hasPermissionTo('users.view'));
        $this->assertFalse($security->permissions->contains('name', 'billing.manage'));
    }
}
