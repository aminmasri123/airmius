<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventAttendanceCorrection;
use App\Models\EventCheckInToken;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventControlledCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_issues_short_lived_device_bound_qr_token_and_member_checks_in_once(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'title' => 'QR Training',
            'type' => 'training',
            'visibility' => 'organization',
            'start_time' => now()->addHour(),
        ]);

        Sanctum::actingAs($owner);
        $issued = $this->postJson("/api/v1/events/{$event->id}/check-in-tokens", [
            'user_id' => $member->id,
            'device_id' => 'device-alpha',
            'ttl_seconds' => 120,
        ])
            ->assertCreated()
            ->assertJsonPath('data.device_bound', true);

        $plainToken = $issued->json('data.token');
        $this->assertDatabaseMissing('event_check_in_tokens', ['token_hash' => $plainToken]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/events/{$event->id}/check-in", [
            'token' => $plainToken,
            'device_id' => 'device-alpha',
        ])
            ->assertOk()
            ->assertJsonPath('data.checked_in', true)
            ->assertJsonPath('data.user_id', $member->id);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'yes',
            'rsvp_status' => 'yes',
            'attendance_status' => 'present',
            'response_mode' => 'qr_check_in',
            'check_in_method' => 'qr_token',
        ]);
        $this->assertNotNull(EventParticipant::query()->where('event_id', $event->id)->where('user_id', $member->id)->value('checked_in_at'));
        $this->assertNotNull(EventCheckInToken::query()->where('event_id', $event->id)->where('user_id', $member->id)->value('used_at'));

        $this->postJson("/api/v1/events/{$event->id}/check-in", [
            'token' => $plainToken,
            'device_id' => 'device-alpha',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('event_attendance_corrections', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'source' => 'qr_check_in',
        ]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.event.check_in_completed',
        ]);
    }

    public function test_check_in_rejects_wrong_device_future_window_expired_token_and_other_club_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $otherMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        $otherClub->users()->attach($otherMember->id, ['role' => 'member', 'membership_status' => 'active']);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'title' => 'Protected Training',
            'type' => 'training',
            'visibility' => 'organization',
            'start_time' => now()->addHour(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/events/{$event->id}/check-in-tokens", [
            'user_id' => $otherMember->id,
        ])->assertUnprocessable();

        $futureToken = $this->postJson("/api/v1/events/{$event->id}/check-in-tokens", [
            'user_id' => $member->id,
            'valid_from' => now()->addMinutes(5)->toJSON(),
            'ttl_seconds' => 60,
        ])->assertCreated()->json('data.token');

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/events/{$event->id}/check-in", [
            'token' => $futureToken,
        ])->assertUnprocessable();

        Sanctum::actingAs($owner);
        $deviceToken = $this->postJson("/api/v1/events/{$event->id}/check-in-tokens", [
            'user_id' => $member->id,
            'device_id' => 'expected-device',
            'ttl_seconds' => 30,
        ])->assertCreated()->json('data.token');

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/events/{$event->id}/check-in", [
            'token' => $deviceToken,
            'device_id' => 'wrong-device',
        ])->assertUnprocessable();

        $this->travel(31)->seconds();
        $this->postJson("/api/v1/events/{$event->id}/check-in", [
            'token' => $deviceToken,
            'device_id' => 'expected-device',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'check_in_method' => 'qr_token',
        ]);
    }

    public function test_manual_attendance_corrections_are_historized_without_private_reason_text_in_club_audit(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'title' => 'Correction Training',
            'type' => 'training',
            'visibility' => 'organization',
            'start_time' => now()->addHour(),
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/events/{$event->id}/attendance", [
            'reason_code' => 'after_event_fix',
            'attendance' => [[
                'user_id' => $member->id,
                'rsvp_status' => 'yes',
                'attendance_status' => 'excused',
                'absence_reason' => 'medical private detail',
            ]],
        ])->assertOk();

        $correction = EventAttendanceCorrection::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertSame('after_event_fix', $correction->reason_code);
        $this->assertTrue($correction->contains_private_note);
        $this->assertArrayNotHasKey('absence_reason', $correction->after_state);

        $activity = Activity::query()->where('type', 'club.event.attendance_corrected')->firstOrFail();
        $this->assertTrue($activity->data['contains_private_note']);
        $this->assertStringNotContainsString('medical private detail', json_encode($activity->data));

        $this->getJson("/api/v1/events/{$event->id}/attendance/corrections")
            ->assertOk()
            ->assertJsonPath('data.0.reason_code', 'after_event_fix')
            ->assertJsonPath('data.0.contains_private_note', true);
    }
}
