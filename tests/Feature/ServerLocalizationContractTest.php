<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Team;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServerLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['de', 'en', 'fr', 'ar'];

    public function test_server_catalogs_have_matching_keys_and_placeholders(): void
    {
        $catalogs = collect(self::LOCALES)->mapWithKeys(
            fn (string $locale) => [$locale => collect(Arr::dot(require lang_path("{$locale}/server.php")))]
        );
        $reference = $catalogs->get('de');

        foreach ($catalogs as $locale => $catalog) {
            $this->assertSame(
                $reference->keys()->sort()->values()->all(),
                $catalog->keys()->sort()->values()->all(),
                "Server translation keys differ for {$locale}."
            );

            foreach ($reference as $key => $source) {
                $this->assertSame(
                    $this->placeholders($source),
                    $this->placeholders($catalog->get($key)),
                    "Translation placeholders differ for {$locale}:{$key}."
                );
                $this->assertNotSame($key, $catalog->get($key));
            }
        }
    }

    public function test_public_api_responses_follow_each_supported_app_locale(): void
    {
        $user = User::factory()->create(['email' => 'localized@example.test']);

        foreach (self::LOCALES as $locale) {
            $response = $this->withHeaders(['X-App-Locale' => $locale])
                ->getJson('/api/v1/auth/register/email?email='.rawurlencode($user->email));

            $response
                ->assertOk()
                ->assertHeader('Content-Language', $locale)
                ->assertHeader('X-Airmius-Text-Direction', $locale === 'ar' ? 'rtl' : 'ltr')
                ->assertJsonPath('data.message', $this->translation($locale, 'auth.account_exists'));
        }
    }

    public function test_accept_language_is_used_and_explicit_app_locale_overrides_user_preference(): void
    {
        $user = User::factory()->create([
            'email' => 'language-priority@example.test',
            'language' => 'de',
        ]);

        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8'])
            ->getJson('/api/v1/auth/register/email?email='.rawurlencode($user->email))
            ->assertOk()
            ->assertHeader('Content-Language', 'fr')
            ->assertJsonPath('data.message', $this->translation('fr', 'auth.account_exists'));

        Sanctum::actingAs($user);

        $this->withHeaders(['X-App-Locale' => 'ar'])
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertHeader('X-Airmius-Text-Direction', 'rtl')
            ->assertJsonPath('data.message', $this->translation('ar', 'auth.logged_out'));
    }

    public function test_training_event_and_chat_mobile_responses_use_negotiated_locale(): void
    {
        $coach = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $coach->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $plan = TrainingPlan::query()->create([
            'created_by' => $coach->id,
            'team_id' => $team->id,
            'title' => 'Localization plan',
            'cadence' => 'weekly',
            'status' => 'draft',
            'share_permission' => 'write',
        ]);

        Sanctum::actingAs($coach);

        $this->withHeaders(['X-App-Locale' => 'fr'])
            ->deleteJson("/api/v1/training/plans/{$plan->id}")
            ->assertOk()
            ->assertHeader('Content-Language', 'fr')
            ->assertJsonPath('message', $this->translation('fr', 'training.plan_deleted'));

        $eventOwner = User::factory()->create();
        $accepted = User::factory()->create();
        $candidate = User::factory()->create();
        $event = Event::query()->create([
            'user_id' => $eventOwner->id,
            'title' => 'Full event',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'max_participants' => 1,
        ]);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'user_id' => $accepted->id,
            'status' => 'yes',
        ]);

        Sanctum::actingAs($candidate);

        $this->withHeaders(['X-App-Locale' => 'ar'])
            ->postJson("/api/v1/events/{$event->id}/participation", ['status' => 'yes'])
            ->assertUnprocessable()
            ->assertHeader('Content-Language', 'ar')
            ->assertHeader('X-Airmius-Text-Direction', 'rtl')
            ->assertJsonPath('message', $this->translation('ar', 'events.full'));

        $groupOwner = User::factory()->create();
        $member = User::factory()->create();
        $conversation = Conversation::query()->create([
            'type' => 'group',
            'owner_id' => $groupOwner->id,
            'name' => 'Localization group',
        ]);
        $conversation->users()->attach([$groupOwner->id, $member->id], ['joined_at' => now()]);

        Sanctum::actingAs($member);

        $this->withHeaders(['X-App-Locale' => 'en'])
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/leave")
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', $this->translation('en', 'chat.left'));
    }

    public function test_web_validation_errors_use_the_negotiated_locale(): void
    {
        $owner = User::factory()->create();
        $accepted = User::factory()->create();
        $candidate = User::factory()->create();
        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Web event',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'max_participants' => 1,
        ]);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'user_id' => $accepted->id,
            'status' => 'yes',
        ]);

        $this->actingAs($candidate)
            ->withHeaders(['X-App-Locale' => 'fr'])
            ->post(route('auth.events.join', $event), ['status' => 'yes'])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'status' => $this->translation('fr', 'events.full'),
            ]);
    }

    private function translation(string $locale, string $key): string
    {
        return data_get(require lang_path("{$locale}/server.php"), $key);
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);

        return collect($matches[1])->unique()->sort()->values()->all();
    }
}
