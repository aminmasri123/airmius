<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\Club;
use App\Models\Folder;
use App\Models\Notification as StoredNotification;
use App\Models\Team;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['de', 'en', 'fr', 'ar'];

    public function test_organization_and_file_catalogs_have_matching_keys_and_placeholders(): void
    {
        foreach (['organization', 'file_manager'] as $catalogName) {
            $catalogs = collect(self::LOCALES)->mapWithKeys(
                fn (string $locale) => [$locale => collect(Arr::dot(require lang_path("{$locale}/{$catalogName}.php")))]
            );
            $reference = $catalogs->get('de');

            foreach ($catalogs as $locale => $catalog) {
                $this->assertSame(
                    $reference->keys()->sort()->values()->all(),
                    $catalog->keys()->sort()->values()->all(),
                    "{$catalogName} translation keys differ for {$locale}."
                );

                foreach ($reference as $key => $source) {
                    $this->assertSame(
                        $this->placeholders($source),
                        $this->placeholders($catalog->get($key)),
                        "{$catalogName} placeholders differ for {$locale}:{$key}."
                    );
                }
            }
        }
    }

    public function test_notifications_are_stored_in_each_recipient_language_with_semantic_metadata(): void
    {
        Event::fake([NotificationCreated::class]);

        foreach (self::LOCALES as $locale) {
            $recipient = User::factory()->create(['language' => $locale]);
            $notification = AppNotification::sendLocalized(
                $recipient->id,
                'team.join_request',
                'organization.notifications.team_join_request_title',
                'organization.notifications.team_join_request_body',
                ['user' => 'Nora', 'team' => 'Sprint'],
                ['title' => 'must not override', 'team_id' => 42],
            );

            $this->assertNotNull($notification);
            $this->assertSame(trans('organization.notifications.team_join_request_title', locale: $locale), $notification->data['title']);
            $this->assertSame(trans('organization.notifications.team_join_request_body', ['user' => 'Nora', 'team' => 'Sprint'], $locale), $notification->data['body']);
            $this->assertSame($locale, $notification->data['locale']);
            $this->assertSame('organization.notifications.team_join_request_title', $notification->data['i18n']['title_key']);
            $this->assertSame(42, $notification->data['team_id']);

            $roleData = AppNotification::localizedData(
                $recipient,
                'organization.notifications.team_invite_title',
                'organization.notifications.team_invite_body',
                [
                    'team' => 'Sprint',
                    'role' => AppNotification::translatedReplacement(
                        'organization.roles.team.player',
                        'Spieler',
                    ),
                ],
            );
            $this->assertSame(
                trans('organization.notifications.team_invite_body', [
                    'role' => trans('organization.roles.team.player', locale: $locale),
                ], $locale),
                $roleData['body'],
            );
            $this->assertSame(
                'organization.roles.team.player',
                $roleData['i18n']['replace']['role']['translation_key'],
            );
        }

        $this->assertSame(4, StoredNotification::query()->count());
    }

    public function test_club_team_and_file_api_errors_follow_the_negotiated_locale(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'membership_requests_enabled' => false,
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        foreach (self::LOCALES as $locale) {
            $user = User::factory()->create(['language' => 'de']);
            $target = User::factory()->create();
            $club->users()->attach($user->id, [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ]);
            $team->users()->attach($user->id, ['role' => 'Player']);
            $folder = Folder::query()->create(['user_id' => $user->id, 'name' => 'Plan']);

            Sanctum::actingAs($user);
            $headers = ['X-App-Locale' => $locale];

            $this->withHeaders($headers)
                ->postJson("/api/v1/clubs/{$club->id}/membership-requests", ['type' => 'membership'])
                ->assertForbidden()
                ->assertHeader('Content-Language', $locale)
                ->assertJsonPath('message', trans('organization.club.applications_closed', locale: $locale));

            $this->withHeaders($headers)
                ->postJson("/api/v1/teams/{$team->id}/join-requests")
                ->assertUnprocessable()
                ->assertJsonPath('errors.team.0', trans('organization.team.already_member', locale: $locale));

            $this->withHeaders($headers)
                ->postJson("/api/v1/files/folders/{$folder->id}/share", ['target_id' => $target->id])
                ->assertForbidden()
                ->assertJsonPath('message', trans('file_manager.folder_friends_only', locale: $locale));
        }
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);

        return collect($matches[1])->unique()->sort()->values()->all();
    }
}
