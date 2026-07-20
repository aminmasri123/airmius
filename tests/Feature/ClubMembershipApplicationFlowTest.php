<?php

namespace Tests\Feature;

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
        $this->assertSame('monthly', $membershipRequest->preview_interval);
        $this->assertSame('privacy-doc', $membershipRequest->accepted_documents[0]['id']);

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
            'preferred_payment_method' => 'cash',
            'requested_billing_interval' => 'yearly',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'membership')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.accepted_documents.0.id', 'privacy-doc')
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
