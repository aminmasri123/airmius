<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\Notification;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pause_limit_and_immediate_approval_shift_next_invoice_once(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'member_pause_requests_enabled' => true,
            'member_pause_max_months' => 1,
        ]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'contribution_amount' => 36,
            'contribution_interval' => 'yearly',
            'contribution_next_invoice_on' => '2027-01-01',
        ]);

        $this->travelTo('2026-10-09');
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/pause-requests", [
            'requested_pause_from' => '2026-10-09',
            'requested_pause_until' => '2026-11-09',
        ])->assertJsonValidationErrors(['requested_pause_until']);

        $this->postJson("/api/v1/clubs/{$club->id}/pause-requests", [
            'requested_pause_from' => '2026-10-09',
            'requested_pause_until' => '2026-11-08',
        ])->assertCreated();
        $pauseRequest = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->where('user_id', $member->id)
            ->where('type', 'pause')
            ->firstOrFail();

        $this->actingAs($owner)
            ->post(route('auth.club-membership-requests.approve', $pauseRequest))
            ->assertRedirect();

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'membership_status' => 'paused',
            'contribution_next_invoice_on' => '2027-02-01',
        ]);

        $this->artisan('airmius:process-scheduled-membership-transitions', ['--date' => '2026-10-09'])
            ->assertSuccessful();
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'contribution_next_invoice_on' => '2027-02-01',
        ]);
        $this->travelBack();
    }

    public function test_type_scoped_required_documents_use_the_selected_membership_type_in_web_and_api(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create(['gender' => 'female']);
        $club = $this->club($owner, [
            'membership_application_documents' => [],
        ]);
        $selectedType = $this->membershipType($club, 'Aktiv');
        $otherType = $this->membershipType($club, 'Fördernd');
        $club->update([
            'membership_application_documents' => [
                $this->document('selected-rules', $selectedType->id, 'Satzung Aktiv'),
                $this->document('other-rules', $otherType->id, 'Satzung Fördernd'),
            ],
        ]);

        Sanctum::actingAs($applicant);

        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests", [
            'type' => 'membership',
            'club_membership_type_id' => $selectedType->id,
            'application_data' => ['gender' => 'female'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accepted_documents.selected-rules'])
            ->assertJsonMissingValidationErrors(['accepted_documents.other-rules']);

        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests", [
            'type' => 'membership',
            'club_membership_type_id' => $selectedType->id,
            'application_data' => ['gender' => 'female'],
            'accepted_documents' => ['selected-rules' => true],
        ])
            ->assertCreated()
            ->assertJsonPath('data.accepted_documents.0.id', 'selected-rules');

        $membershipRequest = ClubMembershipRequest::query()->where('user_id', $applicant->id)->firstOrFail();
        $this->assertCount(1, $membershipRequest->accepted_documents);
        $this->assertSame($selectedType->id, $membershipRequest->club_membership_type_id);

        $audit = Activity::query()->where('type', 'club.membership_request.submitted')->firstOrFail();
        $this->assertSame($applicant->id, $audit->data['target_user_id']);
        $this->assertArrayNotHasKey('application_data', $audit->data);
        $this->assertArrayNotHasKey('accepted_documents', $audit->data);
        $this->assertArrayNotHasKey('consent_ip', $audit->data);
    }

    public function test_web_and_api_share_approval_decline_pause_and_audit_transitions(): void
    {
        $owner = User::factory()->create(['language' => 'en']);
        $applicant = User::factory()->create(['gender' => 'diverse', 'language' => 'de']);
        $declinedApplicant = User::factory()->create(['gender' => 'female']);
        $member = User::factory()->create();
        $club = $this->club($owner, [
            'member_pause_requests_enabled' => true,
            'membership_application_fields' => [
                'gender' => 'required',
                'sepa_iban' => 'optional',
                'sepa_bic' => 'optional',
                'sepa_mandate_consent' => 'optional',
            ],
            'membership_payment_methods' => ['sepa_debit'],
        ]);
        $activeType = $this->membershipType($club, 'Aktiv');
        $club->users()->syncWithoutDetaching([
            $owner->id => [
                'role' => 'owner',
                'roles' => ['owner'],
                'membership_status' => 'active',
            ],
            $member->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ],
        ]);

        $this->actingAs($applicant)
            ->post(route('auth.club-membership-requests.store', $club), [
                'application_data' => [
                    'gender' => 'diverse',
                    'sepa_iban' => 'de12 5001 0517 0648 4898 90',
                    'sepa_bic' => 'ingddeffxxx',
                    'sepa_mandate_consent' => true,
                ],
                'preferred_payment_method' => 'sepa_debit',
                'club_membership_type_id' => $activeType->id,
            ])
            ->assertRedirect();

        $membershipRequest = ClubMembershipRequest::query()->where('user_id', $applicant->id)->firstOrFail();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$membershipRequest->id}/approve", [
            'review_note' => 'Verified in the mobile workspace.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $applicant->id,
            'membership_status' => 'active',
            'club_membership_type_id' => $activeType->id,
            'payment_method' => 'sepa_debit',
            'sepa_iban' => 'DE12500105170648489890',
            'sepa_bic' => 'INGDDEFFXXX',
            'sepa_mandate_active' => true,
        ]);
        $welcomeNotification = Notification::query()
            ->where('user_id', $applicant->id)
            ->where('type', 'club.membership_request_approved')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('Willkommen bei '.$club->name, $welcomeNotification->data['title']);
        $this->assertSame('organization.notifications.membership_welcome_title', $welcomeNotification->data['i18n']['title_key']);
        $this->assertSame('organization.notifications.membership_welcome_with_type_body', $welcomeNotification->data['i18n']['body_key']);
        $this->assertSame('membership_admission_confirmed', $welcomeNotification->data['lifecycle_event']);
        $this->assertSame($activeType->id, $welcomeNotification->data['club_membership_type_id']);
        $this->assertSame('Aktiv', $welcomeNotification->data['membership_type_name']);

        Sanctum::actingAs($declinedApplicant);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests", [
            'type' => 'membership',
            'application_data' => ['gender' => 'female'],
        ])->assertCreated();
        $declinedRequest = ClubMembershipRequest::query()->where('user_id', $declinedApplicant->id)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('auth.club-membership-requests.decline', $declinedRequest), [
                'review_note' => 'Kapazität erschöpft.',
            ])
            ->assertRedirect();
        $this->assertSame('declined', $declinedRequest->fresh()->status);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/pause-requests", [
            'requested_pause_from' => now()->addWeek()->toDateString(),
            'requested_pause_until' => now()->addMonth()->toDateString(),
            'message' => 'Recovery',
        ])->assertCreated();
        $pauseRequest = ClubMembershipRequest::query()
            ->where('user_id', $member->id)
            ->where('type', 'pause')
            ->firstOrFail();

        $this->actingAs($owner)
            ->post(route('auth.club-membership-requests.approve', $pauseRequest))
            ->assertRedirect();
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'membership_status' => 'active',
        ]);
        $this->artisan('airmius:process-scheduled-membership-transitions', [
            '--date' => $pauseRequest->requested_pause_from->toDateString(),
        ])->assertSuccessful();
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'membership_status' => 'paused',
        ]);
        $pauseNotification = Notification::query()
            ->where('user_id', $member->id)
            ->where('type', 'club.membership_request_approved')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('organization.notifications.pause_confirmed_title', $pauseNotification->data['i18n']['title_key']);
        $this->assertSame('organization.notifications.pause_confirmed_body', $pauseNotification->data['i18n']['body_key']);
        $this->assertSame('membership_pause_confirmed', $pauseNotification->data['lifecycle_event']);
        $this->assertSame($pauseRequest->requested_pause_from->toDateString(), $pauseNotification->data['requested_pause_from']);
        $this->assertSame($pauseRequest->requested_pause_until->toDateString(), $pauseNotification->data['requested_pause_until']);

        $this->assertSame(2, Activity::query()->where('type', 'club.membership_request.approved')->count());
        $this->assertDatabaseHas('activities', ['type' => 'club.membership_request.declined']);
        $this->assertDatabaseHas('activities', ['type' => 'club.membership_pause.requested']);
    }

    public function test_membership_type_changes_are_reviewed_requests_before_updating_the_membership(): void
    {
        $owner = User::factory()->create(['language' => 'de']);
        $member = User::factory()->create(['language' => 'en']);
        $approver = User::factory()->create();
        $club = $this->club($owner);
        $basicType = $this->membershipType($club, 'Basis');
        $premiumType = $this->membershipType($club, 'Premium');
        $familyType = $this->membershipType($club, 'Familie');
        $club->users()->attach([
            $member->id => [
                'role' => 'manager',
                'roles' => ['manager'],
                'membership_status' => 'active',
                'club_membership_type_id' => $basicType->id,
                'contribution_amount' => '15.00',
                'contribution_interval' => 'monthly',
                'permission_overrides' => [ClubPermissions::MEMBERS_APPROVE => true],
            ],
            $approver->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_APPROVE => true],
            ],
        ]);
        $this->contributionRule($club, $premiumType, '39.90', 'quarterly');
        $this->contributionRule($club, $familyType, '29.00', 'monthly');

        $this->actingAs($member)
            ->post(route('auth.club-membership-change-requests.store', $club), [
                'club_membership_type_id' => $premiumType->id,
                'message' => 'Bitte ab dem nächsten Zeitraum wechseln.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Your membership change was submitted for review.');

        $webRequest = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->where('user_id', $member->id)
            ->where('type', 'membership_change')
            ->firstOrFail();
        $this->assertSame('pending', $webRequest->status);
        $this->assertSame($premiumType->id, $webRequest->club_membership_type_id);
        $this->assertSame('39.90', $webRequest->preview_amount);
        $this->assertSame('quarterly', $webRequest->preview_interval);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $basicType->id,
            'contribution_amount' => '15.00',
            'contribution_interval' => 'monthly',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'club.membership_change_requested',
        ]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'type' => 'club.membership_change.requested',
        ]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-change-requests", [
            'club_membership_type_id' => $basicType->id,
        ])->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$webRequest->id}/approve")
            ->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$club->id}/membership-change-requests", [
            'club_membership_type_id' => $familyType->id,
            'message' => 'Familientarif statt Premium.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'membership_change')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.club_membership_type_id', $familyType->id)
            ->assertJsonPath('data.preview_amount', '29.00')
            ->assertJsonPath('data.preview_interval', 'monthly');

        $apiRequest = $webRequest->fresh();
        $this->assertSame($webRequest->id, $apiRequest->id);
        $this->assertSame($familyType->id, $apiRequest->club_membership_type_id);

        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$apiRequest->id}/approve", [
            'review_note' => 'Tarif plausibel.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.type', 'membership_change');

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $familyType->id,
            'contribution_amount' => '29.00',
            'contribution_interval' => 'monthly',
        ]);
        $changeNotification = Notification::query()
            ->where('user_id', $member->id)
            ->where('type', 'club.membership_request_approved')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('organization.notifications.membership_change_confirmed_title', $changeNotification->data['i18n']['title_key']);
        $this->assertSame('organization.notifications.membership_change_confirmed_body', $changeNotification->data['i18n']['body_key']);
        $this->assertSame('membership_change_confirmed', $changeNotification->data['lifecycle_event']);
        $this->assertSame($familyType->id, $changeNotification->data['club_membership_type_id']);
        $this->assertSame('Familie', $changeNotification->data['membership_type_name']);
    }

    public function test_web_club_profile_uses_reviewed_termination_instead_of_immediate_detachment(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = $this->club($owner);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $terminationDate = now()->addWeeks(2)->toDateString();

        $this->actingAs($member)
            ->post(route('auth.club-membership-termination-requests.store', $club), [
                'requested_termination_on' => $terminationDate,
                'termination_reason' => 'Vereinswechsel',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Dein Austrittsantrag wurde zur Prüfung eingereicht.');

        $this->assertDatabaseHas('club_membership_requests', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'type' => 'termination',
            'status' => 'pending',
            'requested_termination_on' => $terminationDate.' 00:00:00',
        ]);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'membership_status' => 'active',
        ]);

        $this->get(route('auth.clubs.show', $club))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Clubs/Profile')
                ->where('viewer.is_member', true)
                ->where('viewer.has_pending_termination_request', true)
                ->where('viewer.requested_termination_on', $terminationDate));

        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'type' => 'club.membership_termination.requested',
        ]);

        $source = File::get(resource_path('js/Pages/Auth/Dashboard/Clubs/Profile.vue'));
        $this->assertStringContainsString("route('auth.club-membership-termination-requests.store'", $source);
        $this->assertStringContainsString('viewer.has_pending_termination_request', $source);
        $this->assertStringNotContainsString("route('auth.club-memberships.leave'", $source);
    }

    public function test_membership_manager_cannot_approve_their_own_membership_change_request(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = $this->club($owner, ['member_pause_requests_enabled' => true]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/clubs/{$club->id}/pause-requests", [
            'requested_pause_from' => now()->addWeek()->toDateString(),
            'requested_pause_until' => now()->addMonth()->toDateString(),
        ])->assertCreated();
        $membershipRequest = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->where('user_id', $manager->id)
            ->where('type', 'pause')
            ->firstOrFail();

        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$membershipRequest->id}/approve")
            ->assertUnprocessable();
        $this->assertDatabaseHas('club_membership_requests', [
            'id' => $membershipRequest->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$membershipRequest->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_membership_request_notifications_follow_the_approval_permission(): void
    {
        $owner = User::factory()->create();
        $approver = User::factory()->create();
        $blockedManager = User::factory()->create();
        $applicant = User::factory()->create(['gender' => 'female']);
        $club = $this->club($owner);
        $club->users()->attach([
            $approver->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_APPROVE => true],
            ],
            $blockedManager->id => [
                'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_APPROVE => false],
            ],
        ]);

        Sanctum::actingAs($applicant);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests", [
            'type' => 'membership',
            'application_data' => ['gender' => 'female'],
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $approver->id,
            'type' => 'club.membership_request_created',
        ]);
        $this->assertFalse(Notification::query()
            ->where('user_id', $blockedManager->id)
            ->where('type', 'club.membership_request_created')
            ->exists());
    }

    public function test_member_exit_and_removal_objection_notifications_follow_separate_permissions(): void
    {
        $owner = User::factory()->create();
        $memberManager = User::factory()->create();
        $approver = User::factory()->create();
        $blockedManager = User::factory()->create();
        $member = User::factory()->create();
        $club = $this->club($owner);
        $club->users()->attach([
            $memberManager->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_MANAGE => true],
            ],
            $approver->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_APPROVE => true],
            ],
            $blockedManager->id => [
                'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
                'permission_overrides' => [
                    ClubPermissions::MEMBERS_MANAGE => false,
                    ClubPermissions::MEMBERS_APPROVE => false,
                ],
            ],
            $member->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ],
        ]);

        $this->actingAs($member)
            ->post(route('auth.club-memberships.removal-objection', $club), [
                'message' => 'Bitte erneut prüfen.',
            ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $approver->id,
            'type' => 'club.member_removal_objection',
        ]);
        $this->assertFalse(Notification::query()
            ->where('user_id', $blockedManager->id)
            ->where('type', 'club.member_removal_objection')
            ->exists());

        $this->actingAs($member)
            ->post(route('auth.club-memberships.leave', $club))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $memberManager->id,
            'type' => 'club.member_left',
        ]);
        $this->assertFalse(Notification::query()
            ->where('user_id', $blockedManager->id)
            ->where('type', 'club.member_left')
            ->exists());
    }

    public function test_membership_type_wizard_keeps_the_saved_type_selected_and_localizes_required_name(): void
    {
        $owner = User::factory()->create(['language' => 'de']);
        $club = $this->club($owner);

        $this->actingAs($owner)
            ->post(route('auth.club-memberships.types.store', $club), [
                'name' => '',
                'is_public' => true,
                'is_active' => true,
            ])
            ->assertSessionHasErrors([
                'name' => 'Bitte gib einen Namen für den Mitgliedschaftstyp ein.',
            ]);

        $source = File::get(resource_path('js/Pages/Auth/Dashboard/ClubMemberships/Index.vue'));
        $this->assertStringContainsString('const submittedTypeName = membershipTypeForm.name.trim()', $source);
        $this->assertStringContainsString('editMembershipType(savedType)', $source);
        $this->assertStringContainsString('membershipTypeForm.errors.name', $source);
    }

    private function club(User $owner, array $overrides = []): Club
    {
        return Club::factory()->create(array_merge([
            'owner_id' => $owner->id,
            'membership_requests_enabled' => true,
            'membership_application_fields' => ['gender' => 'required'],
            'membership_payment_methods' => ['cash'],
            'membership_application_documents' => [],
        ], $overrides));
    }

    private function membershipType(Club $club, string $name): ClubMembershipType
    {
        return ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => $name,
            'is_public' => true,
            'is_active' => true,
        ]);
    }

    private function contributionRule(
        Club $club,
        ClubMembershipType $membershipType,
        string $amount,
        string $interval,
    ): ClubContributionRule {
        return ClubContributionRule::query()->create([
            'club_id' => $club->id,
            'club_membership_type_id' => $membershipType->id,
            'name' => $membershipType->name.' Beitrag',
            'valid_from' => now()->subDay()->toDateString(),
            'billing_interval' => $interval,
            'amount' => $amount,
            'factor_key' => 'standard',
            'is_active' => true,
        ]);
    }

    private function document(string $id, int $membershipTypeId, string $title): array
    {
        return [
            'id' => $id,
            'membership_type_id' => $membershipTypeId,
            'type' => 'statutes',
            'title' => $title,
            'url' => 'https://example.test/'.$id,
            'is_visible' => true,
            'is_required' => true,
        ];
    }
}
