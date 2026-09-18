<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification as AppNotification;
use App\Models\OrganizationJob;
use App\Models\OrganizationJobInterest;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserSport;
use App\Services\RecruitingPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RecruitingOpportunityContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_share_only_selected_sport_fields_for_an_explainable_assistive_match(): void
    {
        Notification::fake();
        $owner = $this->manager();
        $candidate = User::factory()->create(['language' => 'de']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $sport = $this->sport();
        $job = $this->job($club, $sport, 'advanced');
        UserSport::query()->create([
            'user_id' => $candidate->id,
            'sport_id' => $sport->id,
            'status' => 'active',
            'experience_level' => 'expert',
            'visibility' => 'private',
            'performance_metrics' => ['secret_metric' => 9001],
        ]);

        Sanctum::actingAs($candidate);
        $this->postJson("/api/v1/public/recruiting/jobs/{$job->id}/interest", [
            'name' => $candidate->name,
            'email' => $candidate->email,
            'message' => 'Ich möchte mitmachen.',
            'accepted_privacy' => true,
            'shared_profile_fields' => ['sports', 'experience'],
            'accepted_profile_sharing' => true,
            'allow_in_app_contact' => true,
        ])->assertCreated();

        $interest = OrganizationJobInterest::query()->firstOrFail();
        $this->assertSame($candidate->id, $interest->user_id);
        $this->assertSame(['sports', 'experience'], $interest->shared_profile_fields);
        $this->assertNotNull($interest->profile_consent_at);
        $this->assertTrue($interest->allow_in_app_contact);

        Sanctum::actingAs($owner);
        $response = $this->getJson('/api/v1/recruiting-pipeline')
            ->assertOk()
            ->assertJsonPath('data.applications.data.0.profile_match.score', 100)
            ->assertJsonPath('data.applications.data.0.profile_match.decision_policy', 'assistive_only')
            ->assertJsonPath('data.applications.data.0.profile_match.dimensions.0.key', 'sport')
            ->assertJsonPath('data.applications.data.0.profile_match.dimensions.0.matched', true)
            ->assertJsonPath('data.applications.data.0.profile_match.dimensions.1.key', 'experience')
            ->assertJsonPath('data.applications.data.0.profile_match.dimensions.1.matched', true)
            ->assertJsonPath('data.applications.data.0.can_open_chat', true);

        $payload = $response->json('data.applications.data.0.profile_match');
        $this->assertArrayNotHasKey('performance_metrics', $payload);
        $this->assertStringNotContainsString('secret_metric', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertSame($sport->name, $response->json('data.jobs.0.sport.name'));
        $this->assertSame('advanced', $response->json('data.jobs.0.minimum_experience_level'));
    }

    public function test_guest_cannot_share_a_profile_and_empty_optional_consent_does_not_block_submission(): void
    {
        Notification::fake();
        $owner = $this->manager();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $job = $this->job($club, $this->sport(), 'beginner');

        $this->postJson("/api/v1/public/recruiting/jobs/{$job->id}/interest", [
            'name' => 'Guest Applicant',
            'email' => 'guest@example.test',
            'accepted_privacy' => true,
            'shared_profile_fields' => ['sports'],
            'accepted_profile_sharing' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('shared_profile_fields');

        $this->postJson("/api/v1/public/recruiting/jobs/{$job->id}/interest", [
            'name' => 'Guest Applicant',
            'email' => 'guest@example.test',
            'accepted_privacy' => true,
            'shared_profile_fields' => [],
            'accepted_profile_sharing' => false,
            'allow_in_app_contact' => true,
        ])->assertCreated();

        $interest = OrganizationJobInterest::query()->firstOrFail();
        $this->assertNull($interest->user_id);
        $this->assertNull($interest->shared_profile_fields);
        $this->assertNull($interest->profile_consent_at);
        $this->assertFalse($interest->allow_in_app_contact);
    }

    public function test_status_transitions_are_constrained_and_offer_hands_off_without_auto_membership(): void
    {
        $owner = $this->manager();
        $candidate = User::factory()->create(['language' => 'fr']);
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'Club Horizon']);
        $job = $this->job($club, $this->sport(), 'intermediate');
        $interest = $this->interest($job, $candidate);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/recruiting-pipeline/applications/{$interest->id}", [
            'status' => 'hired',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->putJson("/api/v1/recruiting-pipeline/applications/{$interest->id}", [
            'status' => 'interview',
        ])->assertOk();
        $this->putJson("/api/v1/recruiting-pipeline/applications/{$interest->id}", [
            'status' => 'offered',
        ])->assertOk();

        $interest->refresh();
        $this->assertSame('offered', $interest->status);
        $this->assertDatabaseCount('club_membership_requests', 0);
        $notification = AppNotification::query()
            ->where('user_id', $candidate->id)
            ->where('type', 'recruiting.offered')
            ->firstOrFail();
        $this->assertSame('fr', data_get($notification->data, 'locale'));
        $this->assertSame('recruiting.notifications.offer_body', data_get($notification->data, 'i18n.body_key'));
        $this->assertSame($club->id, data_get($notification->data, 'club_id'));

        $this->getJson('/api/v1/recruiting-pipeline')
            ->assertOk()
            ->assertJsonPath('data.applications.data.0.membership_handoff.club_id', $club->id)
            ->assertJsonPath('data.applications.data.0.allowed_statuses.1', 'interview');

        $this->putJson("/api/v1/recruiting-pipeline/applications/{$interest->id}", [
            'status' => 'offered',
        ])->assertOk();
        $this->assertSame(1, AppNotification::query()->where('user_id', $candidate->id)
            ->where('type', 'recruiting.offered')->count());
        $this->putJson("/api/v1/recruiting-pipeline/applications/{$interest->id}", [
            'status' => 'hired',
        ])->assertOk();
        $this->assertSame(1, AppNotification::query()->where('user_id', $candidate->id)
            ->where('type', 'recruiting.hired')->count());
        $this->putJson("/api/v1/recruiting-pipeline/applications/{$interest->id}", [
            'status' => 'reviewing',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('hired', $interest->fresh()->status);
        $this->assertDatabaseCount('club_membership_requests', 0);
    }

    public function test_recruiting_chat_requires_opt_in_is_scoped_and_reuses_the_existing_chat_domain(): void
    {
        $owner = $this->manager();
        $outsider = $this->manager();
        $candidate = User::factory()->create(['language' => 'ar']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $job = $this->job($club, $this->sport(), null);
        $interest = $this->interest($job, $candidate, false);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/recruiting-pipeline/applications/{$interest->id}/chat")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversation');

        $interest->forceFill(['allow_in_app_contact' => true])->save();
        $first = $this->postJson("/api/v1/recruiting-pipeline/applications/{$interest->id}/chat")
            ->assertOk();
        $conversationId = (int) $first->json('data.conversation_id');
        $this->assertGreaterThan(0, $conversationId);
        $this->assertDatabaseHas('conversation_users', ['conversation_id' => $conversationId, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('conversation_users', ['conversation_id' => $conversationId, 'user_id' => $candidate->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $candidate->id, 'type' => 'recruiting.chat_opened']);

        $this->postJson("/api/v1/recruiting-pipeline/applications/{$interest->id}/chat")
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversationId);
        $this->assertDatabaseCount('conversations', 1);

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/recruiting-pipeline/applications/{$interest->id}/chat")
            ->assertForbidden();
    }

    public function test_privacy_withdrawal_revokes_profile_sharing_without_deleting_the_application_or_contact_choice(): void
    {
        $owner = $this->manager();
        $candidate = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $job = $this->job($club, $this->sport(), 'expert');
        $interest = $this->interest($job, $candidate, true, ['sports', 'experience']);

        Sanctum::actingAs($candidate);
        $this->postJson('/api/v1/privacy/withdraw-consents', [
            'consents' => ['recruiting_profile_sharing'],
        ])->assertOk()->assertJsonPath('data.withdrawn_consents.0', 'recruiting_profile_sharing');

        $interest->refresh();
        $this->assertNull($interest->shared_profile_fields);
        $this->assertNull($interest->profile_consent_at);
        $this->assertTrue($interest->allow_in_app_contact);
        $this->assertSame('new', $interest->status);
        $this->assertDatabaseHas('organization_job_interests', ['id' => $interest->id]);
    }

    public function test_pipeline_query_count_stays_constant_as_profile_matched_applications_grow(): void
    {
        $owner = $this->manager();
        $candidate = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $sport = $this->sport();
        $job = $this->job($club, $sport, 'intermediate');
        UserSport::query()->create([
            'user_id' => $candidate->id,
            'sport_id' => $sport->id,
            'status' => 'active',
            'experience_level' => 'advanced',
        ]);
        $this->interest($job, $candidate, true, ['sports', 'experience']);

        $pipeline = app(RecruitingPipelineService::class);
        $pipeline->canOpen($owner);
        $singleCount = $this->countQueries(fn () => $pipeline->payload($owner, [], 25));

        foreach (range(1, 7) as $index) {
            OrganizationJobInterest::query()->create([
                'organization_job_id' => $job->id,
                'user_id' => $candidate->id,
                'name' => $candidate->name.' '.$index,
                'email' => "candidate{$index}@example.test",
                'status' => 'new',
                'shared_profile_fields' => ['sports', 'experience'],
                'profile_consent_at' => now(),
                'allow_in_app_contact' => true,
                'consent_at' => now(),
                'retention_expires_at' => now()->addMonths(6),
            ]);
        }

        $manyCount = $this->countQueries(fn () => $pipeline->payload($owner, [], 25));
        $this->assertLessThanOrEqual($singleCount + 1, $manyCount);
        $this->assertLessThanOrEqual(25, $manyCount);
    }

    private function manager(): User
    {
        $permission = Permission::query()->firstOrCreate([
            'name' => 'club.jobs.manage',
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    private function sport(): Sport
    {
        return Sport::query()->create([
            'name' => 'Leichtathletik',
            'slug' => 'athletics-'.fake()->unique()->numerify('####'),
            'category' => 'individual',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function job(Club $club, Sport $sport, ?string $minimumExperience): OrganizationJob
    {
        return OrganizationJob::query()->create([
            'club_id' => $club->id,
            'created_by' => $club->owner_id,
            'sport_id' => $sport->id,
            'title' => 'Athletik-Coach',
            'type' => 'professional',
            'minimum_experience_level' => $minimumExperience,
            'description' => 'Eine klar beschriebene Chance.',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    private function interest(
        OrganizationJob $job,
        User $candidate,
        bool $allowContact = true,
        ?array $sharedFields = null,
    ): OrganizationJobInterest {
        return OrganizationJobInterest::query()->create([
            'organization_job_id' => $job->id,
            'user_id' => $candidate->id,
            'name' => $candidate->name,
            'email' => $candidate->email,
            'status' => 'new',
            'shared_profile_fields' => $sharedFields,
            'profile_consent_at' => $sharedFields ? now() : null,
            'allow_in_app_contact' => $allowContact,
            'consent_at' => now(),
            'retention_expires_at' => now()->addMonths(6),
        ]);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
