<?php

namespace App\Services;

use App\Models\LearningCourse;
use App\Support\LocalizedPublicUrl;
use App\Support\SupportedLocale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LearningCourseTranslationService
{
    /** @return array<int, string> */
    public function locales(): array
    {
        return SupportedLocale::ALL;
    }

    public function requestedLocale(): string
    {
        return SupportedLocale::normalize(app()->getLocale()) ?? SupportedLocale::DEFAULT;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prepareForCreate(array $data, mixed $translationOfId, int $ownerId): array
    {
        $source = filled($translationOfId)
            ? LearningCourse::query()
                ->where('user_id', $ownerId)
                ->findOrFail((int) $translationOfId)
            : null;

        if ($source && ! $source->translation_group) {
            $source->forceFill(['translation_group' => (string) Str::uuid()])->save();
        }

        $data['language'] = $this->normalizeRequiredLocale(
            $data['language'] ?? $this->requestedLocale(),
        );
        $data['translation_group'] = $source?->translation_group ?: (string) Str::uuid();
        unset($data['translation_of_id']);
        $this->assertLocaleAvailable($data['translation_group'], $data['language']);

        return $data;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prepareForUpdate(LearningCourse $course, array $data): array
    {
        $data['language'] = $this->normalizeRequiredLocale($data['language'] ?? $course->language);
        $data['translation_group'] = $course->translation_group ?: (string) Str::uuid();
        $this->assertLocaleAvailable($data['translation_group'], $data['language'], $course->id);

        return $data;
    }

    /** @return Collection<int, array{locale: string, title: string, url: string, status: string}> */
    public function variants(LearningCourse $course, bool $publishedOnly = false): Collection
    {
        if (! $course->translation_group) {
            return collect();
        }

        $variants = $course->relationLoaded('translationVariants')
            ? $course->translationVariants
            : LearningCourse::query()
                ->where('translation_group', $course->translation_group)
                ->get(['id', 'user_id', 'title', 'language', 'status', 'is_public', 'published_at', 'translation_group']);

        return $variants
            ->where('user_id', $course->user_id)
            ->when($publishedOnly, fn (Collection $items) => $items->filter(
                fn (LearningCourse $variant): bool => $variant->status === 'published'
                    && $variant->is_public
                    && (! $variant->published_at || $variant->published_at->lte(now())),
            ))
            ->sortBy(fn (LearningCourse $variant): int => array_search(
                $variant->language,
                SupportedLocale::ALL,
                true,
            ) ?: 0)
            ->map(fn (LearningCourse $variant): array => [
                'locale' => $variant->language,
                'title' => $variant->title,
                'url' => $this->canonicalUrl($variant),
                'status' => $variant->status,
            ])
            ->values();
    }

    /** @return array<int, array{hreflang: string, href: string}> */
    public function alternates(LearningCourse $course): array
    {
        $variants = $this->variants($course, true);

        if ($variants->isEmpty()) {
            return [];
        }

        $alternates = $variants
            ->map(fn (array $variant): array => [
                'hreflang' => $variant['locale'],
                'href' => $variant['url'],
            ])
            ->values();
        $default = $variants->firstWhere('locale', SupportedLocale::DEFAULT) ?? $variants->first();
        $alternates->push([
            'hreflang' => 'x-default',
            'href' => $default['url'],
        ]);

        return $alternates->all();
    }

    public function canonicalUrl(LearningCourse $course): string
    {
        return LocalizedPublicUrl::forLocale(
            route('guest.learning.courses.show', $course),
            $course->language,
        );
    }

    public function publishedVariantForLocale(LearningCourse $course, mixed $locale): ?LearningCourse
    {
        $locale = SupportedLocale::normalize($locale);

        if (! $locale || ! $course->translation_group || $locale === $course->language) {
            return null;
        }

        return LearningCourse::query()
            ->publishedPublic()
            ->where('translation_group', $course->translation_group)
            ->where('user_id', $course->user_id)
            ->where('language', $locale)
            ->first();
    }

    public function decorate(LearningCourse $course, ?string $requestedLocale = null): LearningCourse
    {
        $requestedLocale = SupportedLocale::normalize($requestedLocale) ?? $this->requestedLocale();
        $course->setAttribute('is_locale_fallback', $course->language !== $requestedLocale);
        $course->setAttribute('content_direction', SupportedLocale::direction($course->language));

        return $course;
    }

    public function rethrowWriteConflict(UniqueConstraintViolationException $exception): never
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'learning_courses_translation_locale_unique')
            || (str_contains($message, 'translation_group') && str_contains($message, 'language'))) {
            throw ValidationException::withMessages([
                'language' => [__('learning.errors.translation_locale_exists')],
            ]);
        }

        throw $exception;
    }

    private function assertLocaleAvailable(string $group, string $locale, ?int $ignoreId = null): void
    {
        $exists = LearningCourse::query()
            ->where('translation_group', $group)
            ->where('language', $locale)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'language' => [__('learning.errors.translation_locale_exists')],
            ]);
        }
    }

    private function normalizeRequiredLocale(mixed $locale): string
    {
        $normalized = SupportedLocale::normalize($locale);

        if ($normalized !== null) {
            return $normalized;
        }

        throw ValidationException::withMessages([
            'language' => [__('learning.errors.content_locale_invalid')],
        ]);
    }
}
