<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionClass;
use App\Models\CompetitionRosterEntry;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Services\CompetitionRegistrationWorkflowService;
use App\Support\ClubPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompetitionRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomination_eligibility_fee_confirmation_and_notifications_follow_status_machine(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $manager = User::factory()->create();
        $finance = User::factory()->create();
        $athlete = User::factory()->create([
            'athlete_license_number' => 'LIC-2026',
            'athlete_license_valid_until' => '2026-12-31',
        ]);
        $club->users()->attach($manager->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::EVENTS_EDIT => true],
        ]);
        $club->users()->attach($finance->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::FINANCE_EDIT => true, ClubPermissions::FINANCE_APPROVE => true],
        ]);
        $club->users()->attach($athlete->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);

        $team = Team::factory()->create(['club_id' => $club->id]);
        $competition = Competition::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'name' => 'Cup',
            'status' => 'open',
            'starts_on' => '2026-10-10',
            'registration_deadline_at' => now()->addDay(),
        ]);
        $class = CompetitionClass::query()->create([
            'competition_id' => $competition->id,
            'name' => 'U18',
        ]);

        $workflow = app(CompetitionRegistrationWorkflowService::class);
        $registration = $workflow->nominate($competition, $manager, [
            'competition_class_id' => $class->id,
            'team_id' => $team->id,
            'start_fee_cents' => 2500,
        ]);
        CompetitionRosterEntry::query()->create([
            'competition_id' => $competition->id,
            'competition_registration_id' => $registration->id,
            'competition_class_id' => $class->id,
            'user_id' => $athlete->id,
        ]);

        $this->assertSame('nominated', $registration->status);
        $this->assertSame(1, Notification::query()->where('type', 'competition.registration.nominated')->where('user_id', $manager->id)->count());

        $checked = $workflow->checkEligibility($registration->fresh(), $manager);
        $this->assertSame('pending_confirmation', $checked->status);
        $this->assertSame('valid', $checked->license_status);

        $this->expectException(ValidationException::class);
        $workflow->confirm($checked, $finance);
    }

    public function test_start_fee_payment_and_finance_confirmation_complete_registration(): void
    {
        [$club, $manager, $finance, $athlete, $competition, $class] = $this->workflowFixture();
        $workflow = app(CompetitionRegistrationWorkflowService::class);
        $registration = $workflow->nominate($competition, $manager, ['competition_class_id' => $class->id, 'start_fee_cents' => 1500]);
        CompetitionRosterEntry::query()->create([
            'competition_id' => $competition->id,
            'competition_registration_id' => $registration->id,
            'competition_class_id' => $class->id,
            'user_id' => $athlete->id,
        ]);

        $checked = $workflow->checkEligibility($registration->fresh(), $manager);
        $paid = $workflow->markStartFeePaid($checked, $finance);
        $confirmed = $workflow->confirm($paid, $finance);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertSame(1, Notification::query()->where('type', 'competition.registration.confirmed')->where('user_id', $manager->id)->count());
    }

    public function test_roles_deadlines_licenses_and_club_boundaries_are_enforced(): void
    {
        [$club, $manager, $finance, $athlete, $competition, $class] = $this->workflowFixture([
            'athlete_license_valid_until' => '2026-01-01',
        ]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()]);
        $outsider = User::factory()->create();
        $otherTeam = Team::factory()->create(['club_id' => $otherClub->id]);
        $workflow = app(CompetitionRegistrationWorkflowService::class);

        try {
            $workflow->nominate($competition, $outsider, ['competition_class_id' => $class->id]);
            $this->fail('Expected outsider authorization to fail.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        try {
            $workflow->nominate($competition, $manager, ['competition_class_id' => $class->id, 'team_id' => $otherTeam->id]);
            $this->fail('Expected cross-club team validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_id', $exception->errors());
        }

        $registration = $workflow->nominate($competition, $manager, ['competition_class_id' => $class->id]);
        CompetitionRosterEntry::query()->create([
            'competition_id' => $competition->id,
            'competition_registration_id' => $registration->id,
            'competition_class_id' => $class->id,
            'user_id' => $athlete->id,
        ]);
        $rejected = $workflow->checkEligibility($registration->fresh(), $manager);
        $this->assertSame('rejected', $rejected->status);
        $this->assertSame([$athlete->id], $rejected->payload['eligibility']['missing_license_user_ids']);

        $competition->forceFill(['registration_deadline_at' => now()->subMinute()])->save();
        try {
            $workflow->nominate($competition->fresh(), $manager, ['competition_class_id' => $class->id]);
            $this->fail('Expected expired registration deadline to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration_deadline_at', $exception->errors());
        }
    }

    /** @param array<string, mixed> $athleteOverrides */
    private function workflowFixture(array $athleteOverrides = []): array
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $manager = User::factory()->create();
        $finance = User::factory()->create();
        $athlete = User::factory()->create(array_merge([
            'athlete_license_number' => 'LIC-2026',
            'athlete_license_valid_until' => '2026-12-31',
        ], $athleteOverrides));
        $club->users()->attach($manager->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::EVENTS_EDIT => true],
        ]);
        $club->users()->attach($finance->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::FINANCE_EDIT => true, ClubPermissions::FINANCE_APPROVE => true],
        ]);
        $club->users()->attach($athlete->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $competition = Competition::query()->create([
            'club_id' => $club->id,
            'name' => 'Cup',
            'status' => 'open',
            'starts_on' => '2026-10-10',
            'registration_deadline_at' => now()->addDay(),
        ]);
        $class = CompetitionClass::query()->create([
            'competition_id' => $competition->id,
            'name' => 'U18',
        ]);

        return [$club, $manager, $finance, $athlete, $competition, $class];
    }
}
