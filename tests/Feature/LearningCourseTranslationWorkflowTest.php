<?php

namespace Tests\Feature;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Support\LocalizedPublicUrl;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningCourseTranslationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_courses_have_stable_translation_groups_and_one_variant_per_language(): void
    {
        $course = $this->publishedCourse();

        $this->assertSame('de', $course->language);
        $this->assertTrue(Str::isUuid($course->translation_group));

        $this->publishedCourse([
            'language' => 'en',
            'translation_group' => $course->translation_group,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->publishedCourse([
            'language' => 'en',
            'translation_group' => $course->translation_group,
        ]);
    }

    public function test_tutor_can_create_a_linked_translation_but_cannot_link_another_tutors_course(): void
    {
        $tutor = User::factory()->create();
        $otherTutor = User::factory()->create();
        $source = $this->publishedCourse(['user_id' => $tutor->id]);
        $foreignSource = $this->publishedCourse(['user_id' => $otherTutor->id]);

        $this->actingAs($tutor)
            ->post(route('auth.learning.studio.courses.store'), $this->studioPayload([
                'title' => 'English sprint course',
                'language' => 'en',
                'translation_of_id' => $source->id,
            ]))
            ->assertRedirect();

        $translation = LearningCourse::query()->where('title', 'English sprint course')->firstOrFail();
        $this->assertSame($source->translation_group, $translation->translation_group);
        $this->assertSame('en', $translation->language);
        $this->assertSame('Start', $translation->sections()->firstOrFail()->title);

        $this->actingAs($tutor)
            ->post(route('auth.learning.studio.courses.store'), $this->studioPayload([
                'title' => 'Forbidden link',
                'language' => 'fr',
                'translation_of_id' => $foreignSource->id,
            ]))
            ->assertSessionHasErrors('translation_of_id');

        $this->actingAs($tutor)
            ->post(route('auth.learning.studio.courses.store'), $this->studioPayload([
                'title' => 'Duplicate language',
                'language' => 'en',
                'translation_of_id' => $source->id,
            ]))
            ->assertSessionHasErrors('language');
    }

    public function test_guest_catalog_and_public_api_prefer_exact_translation_then_german_fallback(): void
    {
        [$german, $english] = $this->publishedPair();
        $fallback = $this->publishedCourse(['title' => 'Nur Deutsch']);

        $web = $this->get(route('guest.e-learning', ['locale' => 'en']))->assertOk();
        $cards = collect($web->viewData('page')['props']['learningCourses']['data']);
        $this->assertEqualsCanonicalizing([$english->id, $fallback->id], $cards->pluck('id')->all());
        $this->assertNotContains($german->id, $cards->pluck('id')->all());
        $this->assertFalse((bool) $cards->firstWhere('id', $english->id)['is_locale_fallback']);
        $this->assertTrue((bool) $cards->firstWhere('id', $fallback->id)['is_locale_fallback']);

        $api = $this->getJson('/api/v1/public/learning/courses?locale=en')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $apiCards = collect($api->json('data'));
        $this->assertEqualsCanonicalizing([$english->id, $fallback->id], $apiCards->pluck('id')->all());
        $this->assertArrayNotHasKey('translation_group', $apiCards->first());
    }

    public function test_guest_redirects_to_exact_variant_without_breaking_existing_enrollment(): void
    {
        [$german, $english] = $this->publishedPair();
        $englishUrl = LocalizedPublicUrl::forLocale(
            route('guest.learning.courses.show', $english),
            'en',
        );

        $this->get(route('guest.learning.courses.show', [
            'course' => $german,
            'locale' => 'en',
        ]))
            ->assertStatus(301)
            ->assertRedirect($englishUrl);

        $student = User::factory()->create();
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $german->id,
            'user_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('guest.learning.courses.show', [
                'course' => $german,
                'locale' => 'en',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('course.id', $german->id)
                ->where('course.language', 'de')
                ->where('course.is_locale_fallback', true)
                ->where('enrollment.id', $enrollment->id));
    }

    public function test_course_page_has_real_hreflang_rtl_schema_and_sitemap_urls(): void
    {
        [$german, $english] = $this->publishedPair();
        $arabic = $this->publishedCourse([
            'title' => 'التدريب الذكي',
            'language' => 'ar',
            'translation_group' => $german->translation_group,
            'user_id' => $german->user_id,
        ]);
        $canonical = LocalizedPublicUrl::forLocale(
            route('guest.learning.courses.show', $arabic),
            'ar',
        );

        $response = $this->get($canonical)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.e($canonical).'"', false)
            ->assertSee('hreflang="de"', false)
            ->assertSee('hreflang="en"', false)
            ->assertSee('hreflang="ar"', false)
            ->assertDontSee('hreflang="fr"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('course.content_direction', 'rtl')
                ->where('course.is_locale_fallback', false)
                ->has('translations', 3)
                ->has('seo.alternates', 4));

        $this->assertStringContainsString('"@type":"Course"', (string) $response->getContent());
        $this->assertStringContainsString('"inLanguage":"ar"', (string) $response->getContent());

        $englishUrl = LocalizedPublicUrl::forLocale(
            route('guest.learning.courses.show', $english),
            'en',
        );
        $sitemap = (string) $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($sitemap, '<loc>'.e(route('guest.learning.courses.show', $german)).'</loc>'));
        $this->assertSame(1, substr_count($sitemap, '<loc>'.e($englishUrl).'</loc>'));
        $this->assertStringNotContainsString(
            e(LocalizedPublicUrl::forLocale(route('guest.learning.courses.show', $german), 'fr')),
            $sitemap,
        );
    }

    public function test_mobile_catalog_and_detail_share_the_translation_contract(): void
    {
        [$german, $english] = $this->publishedPair();
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->withHeader('X-App-Locale', 'en')
            ->getJson('/api/v1/learning')
            ->assertOk()
            ->assertJsonCount(1, 'data.catalog')
            ->assertJsonPath('data.catalog.0.id', $english->id)
            ->assertJsonPath('data.catalog.0.language', 'en')
            ->assertJsonPath('data.catalog.0.is_locale_fallback', false);

        $this->withHeader('X-App-Locale', 'en')
            ->getJson('/api/v1/learning/courses/'.$english->id)
            ->assertOk()
            ->assertJsonCount(2, 'data.course.translations')
            ->assertJsonPath('data.course.translations.0.locale', 'de')
            ->assertJsonPath('data.course.translations.1.locale', 'en');

        $this->assertSame($german->translation_group, $english->translation_group);
    }

    public function test_localization_copy_has_key_parity_and_arabic_content(): void
    {
        $copy = json_decode(
            File::get(resource_path('js/i18n/learningContentLocalization.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $referenceKeys = array_keys($copy['de']);

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $this->assertSame($referenceKeys, array_keys($copy[$locale]));
        }

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $copy['ar']['fallback_notice']);
        $this->assertStringContainsString('language-specific', $copy['en']['translation_hint']);
    }

    /** @return array{LearningCourse, LearningCourse} */
    private function publishedPair(): array
    {
        $german = $this->publishedCourse(['title' => 'Deutscher Sprintkurs']);
        $english = $this->publishedCourse([
            'title' => 'English sprint course',
            'language' => 'en',
            'translation_group' => $german->translation_group,
            'user_id' => $german->user_id,
        ]);

        return [$german, $english];
    }

    private function publishedCourse(array $overrides = []): LearningCourse
    {
        static $sequence = 0;
        $sequence++;

        return LearningCourse::query()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Kurs '.$sequence,
            'slug' => 'kurs-'.$sequence.'-'.strtolower(Str::random(6)),
            'description' => 'Ein sicher veröffentlichter Kurs.',
            'category' => 'training',
            'level' => 'beginner',
            'language' => 'de',
            'status' => 'published',
            'is_public' => true,
            'is_free' => true,
            'published_at' => now()->subMinute(),
        ], $overrides));
    }

    private function studioPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Neuer Kurs',
            'subtitle' => null,
            'description' => 'Ein neuer Kurs.',
            'category' => 'training',
            'sport_type' => 'Running',
            'level' => 'beginner',
            'language' => 'de',
            'status' => 'draft',
            'is_public' => false,
            'is_free' => true,
            'price_cents' => 0,
        ], $overrides);
    }
}
