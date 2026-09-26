<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\GuardianChildRelationship;
use App\Models\User;
use App\Services\GuardianChildRelationshipService;
use App\Services\UserDataErasureService;
use App\Services\UserPrivacyExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuardianMultiRelationshipPrivacyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_export_contains_multiple_guardian_relationships(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'Airmius SC']);
        $child = User::factory()->create([
            'name' => 'Minor Member',
            'email' => 'minor@example.test',
            'birth_date' => now()->subYears(12)->toDateString(),
        ]);
        $guardian = User::factory()->create([
            'name' => 'Guardian One',
            'email' => 'guardian-one@example.test',
        ]);
        $club->users()->attach($child->id, ['role' => 'player']);
        $club->users()->attach($guardian->id, ['role' => 'member']);

        GuardianChildRelationship::query()->create([
            'club_id' => $club->id,
            'child_user_id' => $child->id,
            'guardian_user_id' => $guardian->id,
            'guardian_email' => 'guardian-one@example.test',
            'relationship_type' => 'mother',
            'status' => GuardianChildRelationship::STATUS_ACCEPTED,
            'is_primary' => true,
            'backfilled_from_legacy' => true,
            'valid_from' => now()->toDateString(),
            'accepted_at' => now(),
        ]);
        GuardianChildRelationship::query()->create([
            'club_id' => $club->id,
            'child_user_id' => $child->id,
            'guardian_email' => 'guardian-two@example.test',
            'relationship_type' => 'father',
            'status' => GuardianChildRelationship::STATUS_INVITED,
            'is_primary' => false,
            'invited_at' => now(),
        ]);

        $export = app(UserPrivacyExportService::class)->export($child);

        $this->assertSame('airmius.privacy-export.v1', $export['schema']);
        $this->assertCount(2, $export['guardian']['relationships']);
        $this->assertSame('mother', $export['guardian']['relationships'][0]['relationship_type']);
        $this->assertSame(GuardianChildRelationship::STATUS_ACCEPTED, $export['guardian']['relationships'][0]['status']);
        $this->assertTrue($export['guardian']['relationships'][0]['is_primary']);
        $this->assertTrue($export['guardian']['relationships'][0]['backfilled_from_legacy']);
        $this->assertSame('Guardian One', $export['guardian']['relationships'][0]['guardian']['name']);
        $this->assertSame('father', $export['guardian']['relationships'][1]['relationship_type']);
        $this->assertSame(GuardianChildRelationship::STATUS_INVITED, $export['guardian']['relationships'][1]['status']);
    }

    public function test_profile_erasure_minimizes_guardian_contact_data_without_dropping_context(): void
    {
        $guardian = User::factory()->create([
            'email' => 'guardian-delete@example.test',
            'password' => Hash::make('secure-current-password'),
        ]);
        $child = User::factory()->create([
            'guardian_user_id' => $guardian->id,
            'guardian_email' => 'guardian-delete@example.test',
            'birth_date' => now()->subYears(12)->toDateString(),
        ]);
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $secondClub = Club::factory()->create(['owner_id' => User::factory()]);
        $relationship = GuardianChildRelationship::query()->create([
            'club_id' => $secondClub->id,
            'child_user_id' => $child->id,
            'guardian_user_id' => $guardian->id,
            'guardian_email' => 'guardian-delete@example.test',
            'relationship_type' => 'guardian',
            'status' => GuardianChildRelationship::STATUS_ACCEPTED,
            'is_primary' => true,
            'accepted_at' => now(),
        ]);
        $invitation = GuardianChildRelationship::query()->create([
            'club_id' => $club->id,
            'child_user_id' => $child->id,
            'guardian_email' => 'guardian-delete@example.test',
            'relationship_type' => 'guardian',
            'status' => GuardianChildRelationship::STATUS_INVITED,
            'is_primary' => false,
            'invited_at' => now(),
        ]);

        app(UserDataErasureService::class)->erase($guardian, ['profile']);

        $this->assertDatabaseHas('guardian_child_relationships', [
            'id' => $relationship->id,
            'club_id' => $secondClub->id,
            'child_user_id' => $child->id,
            'guardian_user_id' => $guardian->id,
            'guardian_email' => null,
            'status' => GuardianChildRelationship::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('guardian_child_relationships', [
            'id' => $invitation->id,
            'club_id' => $club->id,
            'guardian_user_id' => null,
            'guardian_email' => null,
            'status' => GuardianChildRelationship::STATUS_INVITED,
        ]);
    }

    public function test_legacy_email_backfill_spans_multiple_clubs_as_invited_relationships(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $secondClub = Club::factory()->create(['owner_id' => User::factory()]);
        $child = User::factory()->create([
            'guardian_email' => 'legacy-parent@example.test',
            'birth_date' => now()->subYears(12)->toDateString(),
        ]);
        $club->users()->attach($child->id, ['role' => 'player']);
        $secondClub->users()->attach($child->id, ['role' => 'player']);

        $relationships = app(GuardianChildRelationshipService::class)->backfillLegacyForChild($child);

        $this->assertCount(2, $relationships);
        $this->assertSame(
            [$club->id, $secondClub->id],
            $relationships->pluck('club_id')->sort()->values()->all(),
        );
        $this->assertDatabaseHas('guardian_child_relationships', [
            'club_id' => $club->id,
            'child_user_id' => $child->id,
            'guardian_user_id' => null,
            'guardian_email' => 'legacy-parent@example.test',
            'status' => GuardianChildRelationship::STATUS_INVITED,
            'is_primary' => false,
            'backfilled_from_legacy' => true,
        ]);
    }

    public function test_only_accepted_relationships_can_become_primary_and_revocation_clears_role(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $child = User::factory()->create([
            'birth_date' => now()->subYears(12)->toDateString(),
        ]);
        $club->users()->attach($child->id, ['role' => 'player']);
        $service = app(GuardianChildRelationshipService::class);
        $invited = $service->invite($club, $child, null, 'open-parent@example.test');

        try {
            $service->setPrimary($invited);
            $this->fail('Invited guardian relationship was accepted as primary.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $guardian = User::factory()->create([
            'email' => 'accepted-parent@example.test',
            'birth_date' => now()->subYears(34)->toDateString(),
        ]);
        $club->users()->attach($guardian->id, ['role' => 'member']);

        $accepted = $service->invite($club, $child, $guardian, primary: true);
        $revoked = $service->revoke($accepted);

        $this->assertTrue($accepted->is_primary);
        $this->assertSame(GuardianChildRelationship::STATUS_REVOKED, $revoked->status);
        $this->assertFalse($revoked->is_primary);
    }
}
