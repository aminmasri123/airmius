<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacyRightsProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_download_privacy_export_from_web_settings(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Amina',
            'last_name' => 'Runner',
            'name' => 'Amina Runner',
            'email' => 'amina@example.test',
            'ads_personalization_consent' => true,
            'ads_measurement_consent' => true,
            'city' => 'Berlin',
        ]);
        $owner = User::factory()->create();
        $club = Club::factory()->create(['name' => 'Airmius SC', 'owner_id' => $owner->id]);
        $team = Team::factory()->create(['name' => 'U18 Sprint', 'club_id' => $club->id]);

        $user->clubs()->attach($club->id, ['role' => 'player', 'membership_status' => 'active']);
        $user->teams()->attach($team->id, ['role' => 'athlete']);
        Post::factory()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'content' => 'Intervalltraining erfolgreich beendet.',
            'visibility' => 'team',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'type' => 'training.reminder',
            'data' => ['title' => 'Training'],
            'read' => false,
        ]);

        $response = $this->actingAs($user)->get(route('auth.settings.privacy.export'));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));

        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame('airmius.privacy-export.v1', $payload['schema']);
        $this->assertSame('amina@example.test', $payload['profile']['email']);
        $this->assertTrue($payload['privacy_settings']['ads_personalization_consent']);
        $this->assertSame('Airmius SC', $payload['clubs'][0]['name']);
        $this->assertSame('U18 Sprint', $payload['teams'][0]['name']);
        $this->assertSame('Intervalltraining erfolgreich beendet.', $payload['content']['posts'][0]['content']);
        $this->assertSame('auth.settings.privacy.export', $payload['rights']['export']['web_route']);
        $this->assertContains('current-user.destroy', $payload['rights']['deletion']['web_routes']);
    }

    public function test_user_can_correct_personal_data_from_web_privacy_process(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'name' => 'Old Name',
            'email' => 'old@example.test',
            'country' => 'DE',
            'city' => 'Bonn',
        ]);

        $this->actingAs($user)
            ->patch(route('auth.settings.privacy.correct'), [
                'first_name' => 'Neue',
                'last_name' => 'Person',
                'email' => 'neu@example.test',
                'country' => 'fr',
                'city' => 'Paris',
                'profile_visibility' => 'private',
            ])
            ->assertRedirect();

        $fresh = $user->fresh();

        $this->assertSame('Neue Person', $fresh->name);
        $this->assertSame('neu@example.test', $fresh->email);
        $this->assertSame('FR', $fresh->country);
        $this->assertSame('Paris', $fresh->city);
        $this->assertSame('private', $fresh->profile_visibility);
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'type' => 'privacy.profile_corrected',
        ]);
    }

    public function test_mobile_user_can_export_and_correct_privacy_data(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Mobile',
            'last_name' => 'Tester',
            'name' => 'Mobile Tester',
            'email' => 'mobile@example.test',
            'city' => 'Hamburg',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/privacy/export')
            ->assertOk()
            ->assertJsonPath('data.schema', 'airmius.privacy-export.v1')
            ->assertJsonPath('data.profile.email', 'mobile@example.test')
            ->assertJsonPath('data.rights.correction.api_route', 'api.v1.privacy.correct');

        $this->patchJson('/api/v1/privacy/correction', [
            'first_name' => 'Mobile',
            'last_name' => 'Korrigiert',
            'city' => 'Koeln',
            'direct_message_privacy' => 'friends',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Mobile Korrigiert')
            ->assertJsonPath('data.city', 'Koeln')
            ->assertJsonPath('data.direct_message_privacy', 'friends');
    }

    public function test_mobile_user_can_withdraw_specific_and_all_consents(): void
    {
        $user = User::factory()->create([
            'ads_personalization_consent' => true,
            'ads_measurement_consent' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/privacy/withdraw-consents', [
            'consents' => ['ads_personalization'],
        ])
            ->assertOk()
            ->assertJsonPath('data.withdrawn_consents.0', 'ads_personalization')
            ->assertJsonPath('data.privacy_settings.ads_personalization_consent', false)
            ->assertJsonPath('data.privacy_settings.ads_measurement_consent', true);

        $this->assertFalse($user->fresh()->ads_personalization_consent);
        $this->assertTrue($user->fresh()->ads_measurement_consent);

        $this->postJson('/api/v1/privacy/withdraw-consents', [
            'consents' => ['all'],
        ])
            ->assertOk()
            ->assertJsonPath('data.privacy_settings.ads_measurement_consent', false);

        $this->assertFalse($user->fresh()->ads_measurement_consent);
        $this->assertSame(2, Activity::where('user_id', $user->id)->where('type', 'privacy.consent_withdrawn')->count());
    }
}
