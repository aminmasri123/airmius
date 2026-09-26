<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_and_withdraw_membership_application_from_club_profile(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create(['gender' => 'female']);
        $club = $this->clubWithApplicationForm($owner);
        $type = $this->membershipTypeWithRule($club);

        $this->actingAs($applicant)
            ->post(route('auth.club-membership-requests.store', $club), [
                'club_membership_type_id' => $type->id,
                'application_data' => ['gender' => 'female'],
                'accepted_documents' => ['privacy-doc' => true],
                'preferred_payment_method' => 'cash',
                'requested_billing_interval' => 'monthly',
                'message' => 'Ich moechte Mitglied werden.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Mitgliedschaftsanfrage wurde an den Verein gesendet.');

        $membershipRequest = ClubMembershipRequest::query()->firstOrFail();

        $this->assertSame('membership', $membershipRequest->type);
        $this->assertSame('pending', $membershipRequest->status);
        $this->assertSame(['gender' => 'female'], $membershipRequest->application_data);
        $this->assertSame('cash', $membershipRequest->preferred_payment_method);
        $this->assertSame('monthly', $membershipRequest->requested_billing_interval);
        $this->assertSame('12.50', $membershipRequest->preview_amount);
        $this->assertSame('12.50', $membershipRequest->preview_base_amount);
        $this->assertSame('0.00', $membershipRequest->preview_discount_amount);
        $this->assertSame('standard', $membershipRequest->preview_rule_type);
        $this->assertSame('monthly', $membershipRequest->preview_interval);
        $this->assertSame('privacy-doc', $membershipRequest->accepted_documents[0]['id']);
        $this->assertSame('membership-v1', $membershipRequest->consent_version);
        $this->assertSame('sha256:'.hash('sha256', json_encode([
            'id' => 'privacy-doc',
            'type' => 'privacy',
            'title' => 'Datenschutz',
            'url' => 'https://example.test/privacy',
            'file_id' => null,
            'file_name' => '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $membershipRequest->accepted_documents[0]['version']);

        $this->actingAs($applicant)
            ->delete(route('auth.club-membership-requests.destroy', $club))
            ->assertRedirect()
            ->assertSessionHas('success', 'Mitgliedschaftsanfrage wurde zurückgezogen.');

        $this->assertSame('withdrawn', $membershipRequest->fresh()->status);
    }

    public function test_manager_can_approve_and_decline_only_pending_membership_applications(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create();
        $declinedApplicant = User::factory()->create();
        $mobileApplicant = User::factory()->create();
        $club = $this->clubWithApplicationForm($owner);
        $type = $this->membershipTypeWithRule($club);
        $approvedRequest = ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => $applicant->id,
            'club_membership_type_id' => $type->id,
            'type' => 'membership',
            'status' => 'pending',
            'application_data' => ['gender' => 'male'],
            'accepted_documents' => [],
            'preferred_payment_method' => 'cash',
            'requested_billing_interval' => 'monthly',
            'preview_amount' => 12.50,
            'preview_interval' => 'monthly',
        ]);
        $declinedRequest = ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => $declinedApplicant->id,
            'type' => 'membership',
            'status' => 'pending',
            'application_data' => ['gender' => 'female'],
            'accepted_documents' => [],
        ]);
        $mobileRequest = ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => $mobileApplicant->id,
            'club_membership_type_id' => $type->id,
            'type' => 'membership',
            'status' => 'pending',
            'application_data' => [
                'first_name' => 'Mobile',
                'email' => $mobileApplicant->email,
                'gender' => 'male',
            ],
            'accepted_documents' => [],
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/membership-requests")
            ->assertOk()
            ->assertJsonPath('data.0.application_data', fn ($data) => is_array($data));

        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$mobileRequest->id}/approve", [
            'review_note' => 'Per App geprüft.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('management.members', fn (array $members) => collect($members)
                ->contains(fn (array $member) => $member['id'] === $mobileApplicant->id));

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $mobileApplicant->id,
            'membership_status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('auth.club-membership-requests.approve', $approvedRequest))
            ->assertRedirect()
            ->assertSessionHas('success', 'Anfrage wurde angenommen.');

        $membership = DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('user_id', $applicant->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame('member', $membership->role);
        $this->assertSame('active', $membership->membership_status);
        $this->assertSame($type->id, $membership->club_membership_type_id);
        $this->assertSame('approved', $approvedRequest->fresh()->status);

        $this->actingAs($owner)
            ->post(route('auth.club-membership-requests.decline', $approvedRequest))
            ->assertStatus(422);

        $this->actingAs($owner)
            ->post(route('auth.club-membership-requests.decline', $declinedRequest), [
                'review_note' => 'Aktuell kein Platz.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Anfrage wurde abgelehnt.');

        $declinedRequest->refresh();

        $this->assertSame('declined', $declinedRequest->status);
        $this->assertSame($owner->id, $declinedRequest->reviewed_by);
        $this->assertSame('Aktuell kein Platz.', $declinedRequest->review_note);
    }

    public function test_api_membership_application_validates_required_documents_and_blocks_existing_members(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create(['gender' => 'diverse']);
        $member = User::factory()->create();
        $club = $this->clubWithApplicationForm($owner);

        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($applicant);

        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests", [
            'type' => 'membership',
            'application_data' => ['gender' => 'diverse'],
            'preferred_payment_method' => 'cash',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['accepted_documents.privacy-doc']);

        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests", [
            'type' => 'membership',
            'application_data' => ['gender' => 'diverse'],
            'accepted_documents' => ['privacy-doc' => true],
            'consent_signature' => 'Ada Applicant',
            'preferred_payment_method' => 'cash',
            'requested_billing_interval' => 'yearly',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'membership')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.accepted_documents.0.id', 'privacy-doc')
            ->assertJsonPath('data.accepted_documents.0.version', fn ($value) => is_string($value) && str_starts_with($value, 'sha256:'))
            ->assertJsonPath('data.consent.version', 'membership-v1')
            ->assertJsonPath('data.consent.signature', 'Ada Applicant')
            ->assertJsonPath('data.consent.method', 'typed_signature')
            ->assertJsonPath('data.preferred_payment_method', 'cash')
            ->assertJsonPath('data.requested_billing_interval', 'yearly');

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests", [
            'type' => 'membership',
            'application_data' => ['gender' => 'male'],
            'accepted_documents' => ['privacy-doc' => true],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Du bist bereits Mitglied in diesem Verein.');
    }

    public function test_api_club_profile_exposes_only_public_application_configuration_to_applicants(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create();
        $club = $this->clubWithApplicationForm($owner);
        $club->update([
            'sepa_iban' => 'DE02120300000000202051',
            'sepa_bic' => 'BYLADEM1001',
            'verification_status' => 'verified',
            'is_listed' => true,
        ]);

        Sanctum::actingAs($applicant);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.profile.id', $club->id)
            ->assertJsonPath('data.profile.owner_id', $owner->id)
            ->assertJsonPath('data.viewer.social.profile_user_id', $owner->id)
            ->assertJsonPath('data.management.settings.membership_requests_enabled', true)
            ->assertJsonPath('data.management.settings.membership_application_documents.0.id', 'privacy-doc')
            ->assertJsonPath('data.management.settings.membership_payment_methods.0', 'cash')
            ->assertJsonMissingPath('data.management.settings.sepa_iban')
            ->assertJsonMissingPath('data.management.members')
            ->assertJsonMissingPath('data.management.invoices');
    }

    public function test_mobile_review_rejects_foreign_request_ids_and_repeated_decisions(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create();
        $club = $this->clubWithApplicationForm($owner);
        $foreignClub = $this->clubWithApplicationForm(User::factory()->create());
        $application = ClubMembershipRequest::query()->create([
            'club_id' => $foreignClub->id,
            'user_id' => $applicant->id,
            'type' => 'membership',
            'status' => 'pending',
            'application_data' => ['gender' => 'female'],
            'accepted_documents' => [],
        ]);

        Sanctum::actingAs($owner);
        foreach (['approve', 'decline'] as $decision) {
            $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$application->id}/{$decision}")
                ->assertNotFound();
            $this->postJson("/api/v1/clubs/{$foreignClub->id}/membership-requests/{$application->id}/{$decision}")
                ->assertForbidden();
            $this->assertSame('pending', $application->fresh()->status);
            $this->assertNull($application->fresh()->reviewed_by);
            $this->assertDatabaseMissing('club_user', ['club_id' => $foreignClub->id, 'user_id' => $applicant->id]);
        }

        Sanctum::actingAs($foreignClub->owner);
        $url = "/api/v1/clubs/{$foreignClub->id}/membership-requests/{$application->id}";
        $this->postJson("{$url}/approve", ['review_note' => 'QA original decision'])->assertOk();
        foreach (['approve', 'decline'] as $decision) {
            $this->postJson("{$url}/{$decision}", ['review_note' => 'QA duplicate decision'])
                ->assertUnprocessable();
        }
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame('QA original decision', $application->fresh()->review_note);
        $this->assertSame(1, DB::table('club_user')
            ->where('club_id', $foreignClub->id)->where('user_id', $applicant->id)->count());
    }

    public function test_manager_can_request_information_and_applicant_can_complete_the_request(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create(['gender' => 'female']);
        $otherUser = User::factory()->create();
        $club = $this->clubWithApplicationForm($owner);
        $application = ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => $applicant->id,
            'type' => 'membership',
            'status' => 'pending',
            'application_data' => ['gender' => 'female'],
            'accepted_documents' => [[
                'id' => 'privacy-doc',
                'type' => 'privacy',
                'title' => 'Datenschutz',
                'url' => 'https://example.test/privacy',
                'version' => 'v1',
                'accepted_at' => now()->toIso8601String(),
            ]],
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$application->id}/request-information", [
            'message' => 'Bitte bestätige die aktuelle Datenschutzerklärung.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'information_requested')
            ->assertJsonPath('data.information_request_message', 'Bitte bestätige die aktuelle Datenschutzerklärung.');

        $application->refresh();
        $this->assertSame($owner->id, $application->information_requested_by);
        $this->assertNotNull($application->information_requested_at);

        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$application->id}/approve")
            ->assertUnprocessable();

        Sanctum::actingAs($otherUser);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$application->id}/respond", [
            'message' => 'Fremde Antwort',
        ])->assertForbidden();

        Sanctum::actingAs($applicant);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$application->id}/respond", [
            'message' => 'Die Angaben sind aktuell.',
            'application_data' => ['gender' => 'female'],
            'accepted_documents' => ['privacy-doc' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.applicant_response_message', 'Die Angaben sind aktuell.')
            ->assertJsonPath('data.accepted_documents.0.id', 'privacy-doc');

        $this->assertNotNull($application->fresh()->applicant_responded_at);
        $this->assertDatabaseHas('activities', [
            'type' => 'club.membership_request.information_requested',
            'user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('activities', [
            'type' => 'club.membership_request.information_provided',
            'user_id' => $applicant->id,
        ]);
    }

    public function test_manager_can_waitlist_then_approve_or_decline_membership_requests(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create();
        $club = $this->clubWithApplicationForm($owner);
        $application = ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => $applicant->id,
            'type' => 'membership',
            'status' => 'pending',
            'application_data' => ['gender' => 'female'],
            'accepted_documents' => [],
        ]);

        Sanctum::actingAs($owner);
        $url = "/api/v1/clubs/{$club->id}/membership-requests/{$application->id}";
        $this->postJson("{$url}/waitlist", ['review_note' => 'Nächste freie Kapazität ab Oktober.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'waitlisted')
            ->assertJsonPath('data.review_note', 'Nächste freie Kapazität ab Oktober.');

        $application->refresh();
        $this->assertSame($owner->id, $application->waitlisted_by);
        $this->assertNotNull($application->waitlisted_at);

        $this->postJson("{$url}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $applicant->id,
            'membership_status' => 'active',
        ]);
        $this->assertSame('waitlisted', Activity::query()
            ->where('type', 'club.membership_request.approved')
            ->latest('id')
            ->firstOrFail()
            ->data['from_status']);
    }

    private function clubWithApplicationForm(User $owner): Club
    {
        return Club::factory()->create([
            'owner_id' => $owner->id,
            'membership_requests_enabled' => true,
            'membership_application_fields' => ['gender' => 'required'],
            'membership_payment_methods' => ['cash'],
            'membership_application_documents' => [
                [
                    'id' => 'privacy-doc',
                    'type' => 'privacy',
                    'title' => 'Datenschutz',
                    'url' => 'https://example.test/privacy',
                    'is_visible' => true,
                    'is_required' => true,
                ],
            ],
        ]);
    }

    private function membershipTypeWithRule(Club $club): ClubMembershipType
    {
        $type = ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => 'Aktive Mitgliedschaft',
            'is_public' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ClubContributionRule::query()->create([
            'club_id' => $club->id,
            'club_membership_type_id' => $type->id,
            'name' => 'Aktive monatlich',
            'valid_from' => now()->subDay()->toDateString(),
            'billing_interval' => 'monthly',
            'amount' => 12.50,
            'is_active' => true,
        ]);

        return $type;
    }
}
