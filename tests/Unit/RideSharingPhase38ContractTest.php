<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RideSharingPhase38ContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_every_phase_38_case_is_documented(): void
    {
        $checklist = $this->source('docs/Airmius_Testcheckliste_Web_App_2026-09-29.md');
        $report = $this->source('docs/PHASE_38_RIDE_SHARING_2026-10-15.md');

        foreach (range(1, 7) as $number) {
            $id = sprintf('T38-%02d', $number);
            self::assertStringContainsString($id, $checklist);
            self::assertStringContainsString($id, $report);
        }
    }

    public function test_web_and_app_expose_the_complete_ride_lifecycle(): void
    {
        $web = $this->source('routes/auth.php');
        $api = $this->source('routes/api.php');

        foreach (['store', 'update', 'join', 'leave', 'requests.approve', 'requests.reject', 'members.destroy', 'destroy'] as $action) {
            self::assertStringContainsString("rides.$action", $web);
            self::assertStringContainsString("rides.$action", $api);
        }

        self::assertFileExists(dirname(__DIR__, 2).'/tests/Feature/RideFeatureTest.php');
        self::assertFileExists(dirname(__DIR__, 2).'/mobile/airmius_mobile/test/widget_test.dart');
    }

    public function test_last_seat_is_decided_from_the_locked_current_ride(): void
    {
        $service = $this->source('app/Services/RideService.php');
        $migration = $this->source('database/migrations/2026_05_16_000001_optimize_ride_users_membership_index.php');

        self::assertGreaterThanOrEqual(2, substr_count($service, 'lockForUpdate()->firstOrFail()'));
        self::assertGreaterThanOrEqual(2, substr_count($service, '(int) $lockedRide->seats'));
        self::assertStringContainsString("unique(['ride_id', 'user_id']", $migration);
        self::assertStringContainsString("where('status', Ride::MEMBER_STATUS_ACCEPTED)", $service);
    }

    public function test_event_context_is_available_and_tenant_checked_on_web_and_app(): void
    {
        $model = $this->source('app/Models/Ride.php');
        $webController = $this->source('app/Http/Controllers/RideController.php');
        $apiController = $this->source('app/Http/Controllers/Api/V1/RideController.php');
        $webUi = $this->source('resources/js/Pages/Auth/Dashboard/Rides/Index.vue');
        $appUi = $this->source('mobile/airmius_mobile/lib/screens/carpool_center_screen.dart');

        self::assertStringContainsString('return $this->belongsTo(Event::class);', $model);
        foreach ([$webController, $apiController] as $controller) {
            self::assertStringContainsString("'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')]", $controller);
            self::assertStringContainsString('Event::query()->visibleTo($request->user())', $controller);
            self::assertStringContainsString("'events' => \$this->visibleEvents(\$user)", $controller);
            self::assertStringContainsString("'available_seats' => max(0", $controller);
        }
        self::assertStringContainsString('v-model="eventFilter"', $webUi);
        self::assertStringContainsString('v-model="form.event_id"', $webUi);
        self::assertStringContainsString("'event_id': _eventId", $appUi);
        self::assertStringContainsString('_eventFilterId', $appUi);
    }

    public function test_private_details_require_an_accepted_membership_and_notifications_cover_state_changes(): void
    {
        foreach ([
            $this->source('app/Http/Controllers/RideController.php'),
            $this->source('app/Http/Controllers/Api/V1/RideController.php'),
        ] as $controller) {
            self::assertStringContainsString('MEMBER_STATUS_ACCEPTED', $controller);
            self::assertStringContainsString("'contact_details' => \$canSeePrivate", $controller);
            self::assertStringContainsString("'pickup_private_label' => \$canSeePrivate", $controller);
            foreach (['ride.updated', 'ride.requested', 'ride.request_approved', 'ride.request_rejected', 'ride.left', 'ride.member_removed', 'ride.deleted'] as $type) {
                self::assertStringContainsString("'$type'", $controller);
            }
        }
    }
}
