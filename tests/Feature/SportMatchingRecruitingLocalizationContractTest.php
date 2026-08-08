<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\Club;
use App\Models\Notification as StoredNotification;
use App\Models\OrganizationJob;
use App\Models\OrganizationJobInterest;
use App\Models\Sport;
use App\Models\SportMatching;
use App\Models\SportMatchingAttendance;
use App\Models\User;
use App\Notifications\OrganizationJobInterestReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportMatchingRecruitingLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['de', 'en', 'fr', 'ar'];

    public function test_matching_and_recruiting_catalogs_have_key_and_placeholder_parity(): void
    {
        foreach (['sport_matching', 'recruiting'] as $catalogName) {
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

    public function test_matching_application_notifications_use_each_recipient_language(): void
    {
        Event::fake([NotificationCreated::class]);
        $sport = Sport::query()->create([
            'name' => 'Padel',
            'slug' => 'padel-localized-contract',
            'category' => 'racket',
            'is_active' => true,
        ]);

        foreach (self::LOCALES as $locale) {
            $owner = User::factory()->create(['language' => $locale]);
            $applicant = User::factory()->create(['language' => 'de']);
            $matching = SportMatching::query()->create([
                'user_id' => $owner->id,
                'sport_id' => $sport->id,
                'mode' => 'partner',
                'title' => 'Morning Padel',
                'city' => 'Berlin',
                'country_code' => 'DE',
                'radius_km' => 25,
                'starts_at' => now()->addDay(),
                'participants_needed' => 1,
                'skill_level' => 'all',
                'status' => 'open',
            ]);

            Sanctum::actingAs($applicant);
            $this->postJson("/api/v1/sport-matching/{$matching->id}/apply")
                ->assertOk();

            $notification = StoredNotification::query()
                ->where('user_id', $owner->id)
                ->where('type', 'sport_matching.application')
                ->latest('id')
                ->firstOrFail();

            $this->assertSame($locale, $notification->data['locale']);
            $this->assertSame(
                trans('sport_matching.notifications.application_title', locale: $locale),
                $notification->data['title'],
            );
            $this->assertSame(
                trans('sport_matching.notifications.application_body', [
                    'user' => $applicant->name,
                    'matching' => $matching->title,
                ], $locale),
                $notification->data['body'],
            );
            $this->assertSame(
                'sport_matching.notifications.application_title',
                $notification->data['i18n']['title_key'],
            );

            Sanctum::actingAs($owner);
            $this->withHeader('X-App-Locale', $locale)
                ->postJson("/api/v1/sport-matching/{$matching->id}/apply")
                ->assertUnprocessable()
                ->assertHeader('Content-Language', $locale)
                ->assertJsonPath('message', trans('sport_matching.errors.not_open_or_own', locale: $locale));
        }
    }

    public function test_matching_reminders_are_localized_for_each_participant(): void
    {
        Event::fake([NotificationCreated::class]);
        $sport = Sport::query()->create([
            'name' => 'Running',
            'slug' => 'running-reminder-contract',
            'category' => 'endurance',
            'is_active' => true,
        ]);

        foreach (self::LOCALES as $locale) {
            $participant = User::factory()->create(['language' => $locale]);
            $matching = SportMatching::query()->create([
                'user_id' => $participant->id,
                'sport_id' => $sport->id,
                'mode' => 'partner',
                'title' => 'Track session',
                'city' => 'Paris',
                'country_code' => 'FR',
                'radius_km' => 10,
                'starts_at' => now()->addHour(),
                'participants_needed' => 1,
                'skill_level' => 'recreational',
                'status' => 'matched',
            ]);
            SportMatchingAttendance::query()->create([
                'sport_matching_id' => $matching->id,
                'user_id' => $participant->id,
                'status' => 'pending',
            ]);
        }

        $this->artisan('airmius:send-sport-matching-reminders')
            ->assertSuccessful();

        foreach (self::LOCALES as $locale) {
            $participant = User::query()->where('language', $locale)->firstOrFail();
            $notification = StoredNotification::query()
                ->where('user_id', $participant->id)
                ->where('type', 'sport_matching.reminder')
                ->firstOrFail();

            $this->assertSame($locale, $notification->data['locale']);
            $this->assertSame(
                trans('sport_matching.notifications.reminder_confirm_title', locale: $locale),
                $notification->data['title'],
            );
        }
    }

    public function test_recruiting_mail_uses_recipient_or_request_locale(): void
    {
        $creator = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $creator->id, 'name' => 'Airmius Runners']);
        $job = OrganizationJob::query()->create([
            'club_id' => $club->id,
            'created_by' => $creator->id,
            'title' => 'Youth Coach',
            'type' => 'professional',
            'description' => 'Support the youth team.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $interest = OrganizationJobInterest::query()->create([
            'organization_job_id' => $job->id,
            'name' => 'Nora Athlete',
            'email' => 'nora@example.test',
            'phone' => '+49 123 456',
            'message' => 'I would like to help.',
        ])->load('job.club');

        $this->assertInstanceOf(ShouldQueue::class, new OrganizationJobInterestReceived($interest, 'de'));

        foreach (self::LOCALES as $locale) {
            $recipient = User::factory()->create(['language' => $locale]);
            $mail = (new OrganizationJobInterestReceived($interest, 'de'))->toMail($recipient);

            $this->assertSame(
                trans('recruiting.mail.subject', ['title' => $job->title], $locale),
                $mail->subject,
            );
            $this->assertSame(trans('recruiting.mail.greeting', locale: $locale), $mail->greeting);
            $this->assertSame(trans('recruiting.mail.action', locale: $locale), $mail->actionText);
        }

        $anonymous = (new AnonymousNotifiable)->route('mail', 'jobs@example.test');
        $mail = (new OrganizationJobInterestReceived($interest, 'fr'))->toMail($anonymous);

        $this->assertSame(
            trans('recruiting.mail.subject', ['title' => $job->title], 'fr'),
            $mail->subject,
        );
        $this->assertSame(trans('recruiting.mail.action', locale: 'fr'), $mail->actionText);
        $this->assertStringNotContainsString('?ffnen', $mail->actionText);
    }

    public function test_public_recruiting_api_exposes_live_safe_jobs_and_accepts_localized_interest(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['language' => 'ar']);
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Padel',
            'sport_type' => 'padel',
            'city' => 'Berlin',
        ]);
        $published = OrganizationJob::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Padel Coach',
            'type' => 'professional',
            'description' => 'Coach our youth team.',
            'contact_email' => 'jobs@example.test',
            'application_url' => 'javascript:alert(1)',
            'is_published' => true,
            'published_at' => now(),
        ]);
        OrganizationJob::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Private draft',
            'type' => 'volunteer',
            'description' => 'Must remain private.',
            'is_published' => false,
        ]);

        $this->withHeader('X-App-Locale', 'fr')
            ->getJson('/api/v1/public/recruiting/jobs?type=professional&sport_type=padel&q=Coach')
            ->assertOk()
            ->assertHeader('Content-Language', 'fr')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.club.name', 'Airmius Padel')
            ->assertJsonPath('data.0.contact_available', true)
            ->assertJsonPath('data.0.application_url', null)
            ->assertJsonMissingPath('data.0.contact_email')
            ->assertJsonPath('meta.total', 1);

        $interestHeaders = [
            'X-App-Locale' => 'fr',
            'Idempotency-Key' => 'public-recruiting-interest-17',
        ];
        $interestPayload = [
            'name' => 'Nora Athlete',
            'email' => 'nora@example.test',
            'phone' => '+33 123 456',
            'message' => 'Je suis intéressée.',
            'accepted_privacy' => true,
        ];

        $this->withHeaders($interestHeaders)
            ->postJson("/api/v1/public/recruiting/jobs/{$published->id}/interest", [
                ...$interestPayload,
            ])
            ->assertCreated()
            ->assertHeader('Content-Language', 'fr')
            ->assertJsonPath('message', trans('recruiting.flash.interest_sent', locale: 'fr'))
            ->assertJsonPath('data.job_id', $published->id);

        $this->withHeaders($interestHeaders)
            ->postJson("/api/v1/public/recruiting/jobs/{$published->id}/interest", $interestPayload)
            ->assertCreated()
            ->assertHeader('X-Idempotent-Replay', 'true');

        $this->assertDatabaseHas('organization_job_interests', [
            'organization_job_id' => $published->id,
            'email' => 'nora@example.test',
        ]);
        $this->assertDatabaseCount('organization_job_interests', 1);
        Notification::assertSentTo($owner, OrganizationJobInterestReceived::class);
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);

        return collect($matches[1])->unique()->sort()->values()->all();
    }
}
