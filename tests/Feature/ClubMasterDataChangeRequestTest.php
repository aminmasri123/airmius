<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubMasterDataChangeRequest;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMasterDataChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_editor_change_is_applied_immediately_and_audited_without_values(): void
    {
        [$club, $editor] = $this->clubAndEditor(ClubPermissions::CLUB_CONTACT_EDIT, [
            'contact_email' => 'alt@example.test',
        ]);
        Sanctum::actingAs($editor);

        $this->postJson("/api/v1/clubs/{$club->id}/master-data-change-requests", [
            'contact_email' => ' NEU@EXAMPLE.TEST ',
            'contact_phone' => '+49 221 123',
        ])->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.fields.0', 'contact_email');

        $club->refresh();
        $this->assertSame('neu@example.test', $club->contact_email);
        $this->assertSame('+49 221 123', $club->contact_phone);

        $activity = Activity::query()
            ->where('club_id', $club->id)
            ->where('type', 'club.master_data_change.applied')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(['contact_email', 'contact_phone'], $activity->data['fields']);
        $this->assertStringNotContainsString('neu@example.test', json_encode($activity->data));
    }

    public function test_sensitive_master_data_requires_four_eyes_approval(): void
    {
        [$club, $requester] = $this->clubAndEditor(ClubPermissions::CLUB_LEGAL_EDIT, [
            'tax_number' => 'ALT',
        ]);
        $reviewer = User::factory()->create();
        $club->users()->attach($reviewer->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::CLUB_LEGAL_EDIT => true],
        ]);
        Sanctum::actingAs($requester);

        $response = $this->postJson("/api/v1/clubs/{$club->id}/master-data-change-requests", [
            'tax_number' => 'NEU-123',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.sensitive_fields.0', 'tax_number');

        $changeId = $response->json('data.id');
        $this->assertSame('ALT', $club->fresh()->tax_number);

        $this->postJson("/api/v1/clubs/{$club->id}/master-data-change-requests/{$changeId}/approve")
            ->assertUnprocessable();

        Sanctum::actingAs($reviewer);
        $this->postJson("/api/v1/clubs/{$club->id}/master-data-change-requests/{$changeId}/approve", [
            'review_note' => 'Geprüft.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('NEU-123', $club->fresh()->tax_number);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.master_data_change.approved',
        ]);
    }

    public function test_open_overlapping_request_is_rejected(): void
    {
        [$club, $editor] = $this->clubAndEditor(ClubPermissions::CLUB_LEGAL_EDIT);
        Sanctum::actingAs($editor);

        $this->postJson("/api/v1/clubs/{$club->id}/master-data-change-requests", [
            'tax_authority' => 'Finanzamt Altstadt',
        ])->assertCreated();

        $this->postJson("/api/v1/clubs/{$club->id}/master-data-change-requests", [
            'tax_authority' => 'Finanzamt Neustadt',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('fields');
    }

    public function test_approval_detects_base_value_conflict_before_applying(): void
    {
        [$club, $requester] = $this->clubAndEditor(ClubPermissions::CLUB_LEGAL_EDIT, [
            'tax_status' => 'unknown',
        ]);
        $reviewer = User::factory()->create();
        $club->users()->attach($reviewer->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::CLUB_LEGAL_EDIT => true],
        ]);
        Sanctum::actingAs($requester);

        $changeId = $this->postJson("/api/v1/clubs/{$club->id}/master-data-change-requests", [
            'tax_status' => 'nonprofit',
        ])->assertCreated()->json('data.id');

        $club->forceFill(['tax_status' => 'taxable'])->save();

        Sanctum::actingAs($reviewer);
        $this->postJson("/api/v1/clubs/{$club->id}/master-data-change-requests/{$changeId}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request');

        $change = ClubMasterDataChangeRequest::findOrFail($changeId);
        $this->assertSame('conflict', $change->status);
        $this->assertSame('taxable', $club->fresh()->tax_status);
    }

    private function clubAndEditor(string $permission, array $clubAttributes = []): array
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'country' => 'DE',
            ...$clubAttributes,
        ]);
        $club->users()->attach($editor->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'permission_overrides' => [$permission => true],
        ]);

        return [$club, $editor];
    }
}
