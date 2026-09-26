<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\GuardianChildRelationship;
use App\Models\Notification;
use App\Models\User;
use App\Services\GuardianChildRelationshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuardianChildRelationshipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_skips_children_without_legacy_guardian_data(): void
    {
        [$club, $child] = $this->clubChild();

        $relationships = app(GuardianChildRelationshipService::class)->backfillLegacyForChild($child);

        $this->assertCount(0, $relationships);
        $this->assertDatabaseMissing('guardian_child_relationships', [
            'club_id' => $club->id,
            'child_user_id' => $child->id,
        ]);
    }

    public function test_backfill_creates_primary_accepted_relationship_from_unique_legacy_guardian(): void
    {
        [$club, $child] = $this->clubChild();
        $guardian = User::factory()->create([
            'email' => 'parent@example.test',
            'birth_date' => now()->subYears(36)->toDateString(),
        ]);
        $club->users()->attach($guardian->id, ['role' => 'member']);
        $child->forceFill([
            'guardian_user_id' => $guardian->id,
            'guardian_email' => 'parent@example.test',
        ])->save();

        $relationships = app(GuardianChildRelationshipService::class)->backfillLegacyForChild($child);

        $this->assertCount(1, $relationships);
        $this->assertDatabaseHas('guardian_child_relationships', [
            'club_id' => $club->id,
            'child_user_id' => $child->id,
            'guardian_user_id' => $guardian->id,
            'guardian_email' => 'parent@example.test',
            'status' => GuardianChildRelationship::STATUS_ACCEPTED,
            'is_primary' => true,
            'backfilled_from_legacy' => true,
        ]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.guardian_child.invited',
            'subject_type' => GuardianChildRelationship::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $guardian->id,
            'type' => 'guardian.relationship_invited',
        ]);
    }

    public function test_backfill_marks_conflicting_legacy_user_and_email_as_ambiguous(): void
    {
        [$club, $child] = $this->clubChild();
        $guardian = User::factory()->create([
            'email' => 'linked-parent@example.test',
            'birth_date' => now()->subYears(36)->toDateString(),
        ]);
        $club->users()->attach($guardian->id, ['role' => 'member']);
        $child->forceFill([
            'guardian_user_id' => $guardian->id,
            'guardian_email' => 'different-parent@example.test',
        ])->save();

        app(GuardianChildRelationshipService::class)->backfillLegacyForChild($child);

        $relationship = GuardianChildRelationship::query()->sole();
        $this->assertSame(GuardianChildRelationship::STATUS_AMBIGUOUS, $relationship->status);
        $this->assertFalse($relationship->is_primary);
        $this->assertSame('different-parent@example.test', $relationship->metadata['legacy_guardian_email']);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.guardian_child.legacy_ambiguous',
        ]);
    }

    public function test_rollback_removes_only_legacy_backfilled_relationships(): void
    {
        [$club, $child] = $this->clubChild();
        $guardian = User::factory()->create([
            'email' => 'parent@example.test',
            'birth_date' => now()->subYears(36)->toDateString(),
        ]);
        $club->users()->attach($guardian->id, ['role' => 'member']);
        $child->forceFill([
            'guardian_user_id' => $guardian->id,
            'guardian_email' => 'parent@example.test',
        ])->save();

        $service = app(GuardianChildRelationshipService::class);
        $service->backfillLegacyForChild($child);
        $manual = $service->invite($club, $child, null, 'second-parent@example.test');

        $this->assertSame(1, $service->rollbackBackfillForChild($child));

        $this->assertDatabaseMissing('guardian_child_relationships', [
            'guardian_user_id' => $guardian->id,
            'backfilled_from_legacy' => true,
        ]);
        $this->assertDatabaseHas('guardian_child_relationships', [
            'id' => $manual->id,
            'backfilled_from_legacy' => false,
        ]);
    }

    public function test_invite_rejects_self_and_cross_club_account_links(): void
    {
        [$club, $child] = $this->clubChild();
        $otherClubGuardian = User::factory()->create([
            'birth_date' => now()->subYears(34)->toDateString(),
        ]);

        $service = app(GuardianChildRelationshipService::class);

        try {
            $service->invite($club, $child, $child);
            $this->fail('Self guardian link was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('guardian_user_id', $exception->errors());
        }

        try {
            $service->invite($club, $child, $otherClubGuardian);
            $this->fail('Cross-club guardian link was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('guardian_user_id', $exception->errors());
        }
    }

    public function test_accept_and_primary_change_sync_legacy_fields_and_audit(): void
    {
        [$club, $child] = $this->clubChild();
        $guardian = User::factory()->create([
            'email' => 'primary@example.test',
            'birth_date' => now()->subYears(34)->toDateString(),
        ]);
        $club->users()->attach($guardian->id, ['role' => 'member']);

        $service = app(GuardianChildRelationshipService::class);
        $relationship = $service->invite($club, $child, $guardian, primary: true);
        $accepted = $service->accept($relationship, $guardian);
        $service->setPrimary($accepted);

        $child->refresh();
        $this->assertSame($guardian->id, $child->guardian_user_id);
        $this->assertSame('primary@example.test', $child->guardian_email);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.guardian_child.primary_changed',
        ]);
    }

    /** @return array{Club, User} */
    private function clubChild(): array
    {
        $owner = User::factory()->create([
            'birth_date' => now()->subYears(36)->toDateString(),
        ]);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $child = User::factory()->create([
            'birth_date' => now()->subYears(12)->toDateString(),
        ]);
        $club->users()->attach($child->id, ['role' => 'player']);

        return [$club, $child];
    }
}
