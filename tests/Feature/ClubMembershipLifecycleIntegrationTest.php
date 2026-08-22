<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

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
        $applicant = User::factory()->create(['gender' => 'diverse']);
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
            'payment_method' => 'sepa_debit',
            'sepa_iban' => 'DE12500105170648489890',
            'sepa_bic' => 'INGDDEFFXXX',
            'sepa_mandate_active' => true,
        ]);

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
            'membership_status' => 'paused',
        ]);

        $this->assertSame(2, Activity::query()->where('type', 'club.membership_request.approved')->count());
        $this->assertDatabaseHas('activities', ['type' => 'club.membership_request.declined']);
        $this->assertDatabaseHas('activities', ['type' => 'club.membership_pause.requested']);
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
