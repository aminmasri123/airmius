<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Support\LocalizedPublicUrl;
use App\Support\SupportedLocale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogTranslationService
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
    public function prepareForCreate(array $data, mixed $translationOfId = null): array
    {
        $source = filled($translationOfId)
            ? BlogPost::query()->findOrFail((int) $translationOfId)
            : null;

        if ($source && ! $source->translation_group) {
            $source->forceFill(['translation_group' => (string) Str::uuid()])->save();
        }

        $data['content_locale'] = $this->normalizeRequiredLocale(
            $data['content_locale'] ?? $this->requestedLocale(),
        );
        $data['translation_group'] = $source?->translation_group ?: (string) Str::uuid();
        unset($data['translation_of_id']);
        $this->assertLocaleAvailable($data['translation_group'], $data['content_locale']);

        return $data;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prepareForUpdate(BlogPost $post, array $data): array
    {
        $data['content_locale'] = $this->normalizeRequiredLocale(
            $data['content_locale'] ?? $post->content_locale,
        );
        $data['translation_group'] = $post->translation_group ?: (string) Str::uuid();
        $this->assertLocaleAvailable($data['translation_group'], $data['content_locale'], $post->id);

        return $data;
    }

    /** @return Collection<int, array{locale: string, title: string, slug: string, url: string, status: string}> */
    public function variants(BlogPost $post, bool $publishedOnly = false): Collection
    {
        if (! $post->translation_group) {
            return collect();
        }

        $variants = $post->relationLoaded('translationVariants')
            ? $post->translationVariants
            : BlogPost::query()
                ->where('translation_group', $post->translation_group)
                ->get(['id', 'title', 'slug', 'content_locale', 'status', 'published_at']);

        return $variants
            ->when($publishedOnly, fn (Collection $items) => $items->filter(
                fn (BlogPost $variant): bool => $variant->status === 'published'
                    && $variant->published_at?->lte(now()),
            ))
            ->sortBy(fn (BlogPost $variant): int => array_search(
                $variant->content_locale,
                SupportedLocale::ALL,
                true,
            ) ?: 0)
            ->map(fn (BlogPost $variant): array => [
                'locale' => $variant->content_locale,
                'title' => $variant->title,
                'slug' => $variant->slug,
                'url' => $this->canonicalUrl($variant),
                'status' => $variant->status,
            ])
            ->values();
    }

    /** @return array<int, array{hreflang: string, href: string}> */
    public function alternates(BlogPost $post): array
    {
        $variants = $this->variants($post, true);

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

    public function canonicalUrl(BlogPost $post): string
    {
        return LocalizedPublicUrl::forLocale(
            route('guest.blog.show', $post->slug),
            $post->content_locale,
        );
    }

    public function publishedVariantForLocale(BlogPost $post, mixed $locale): ?BlogPost
    {
        $locale = SupportedLocale::normalize($locale);

        if (! $locale || ! $post->translation_group || $locale === $post->content_locale) {
            return null;
        }

        return BlogPost::query()
            ->published()
            ->where('translation_group', $post->translation_group)
            ->where('content_locale', $locale)
            ->first();
    }

    public function decorate(BlogPost $post, ?string $requestedLocale = null): BlogPost
    {
        $requestedLocale = SupportedLocale::normalize($requestedLocale) ?? $this->requestedLocale();
        $post->setAttribute('is_locale_fallback', $post->content_locale !== $requestedLocale);
        $post->setAttribute('content_direction', SupportedLocale::direction($post->content_locale));

        return $post;
    }

    public function rethrowWriteConflict(UniqueConstraintViolationException $exception): never
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'blog_posts_translation_locale_unique')
            || (str_contains($message, 'translation_group') && str_contains($message, 'content_locale'))) {
            throw ValidationException::withMessages([
                'content_locale' => [__('editorial.errors.translation_locale_exists')],
            ]);
        }

        throw $exception;
    }

    private function assertLocaleAvailable(string $group, string $locale, ?int $ignoreId = null): void
    {
        $exists = BlogPost::query()
            ->where('translation_group', $group)
            ->where('content_locale', $locale)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'content_locale' => [__('editorial.errors.translation_locale_exists')],
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
            'content_locale' => [__('editorial.errors.content_locale_invalid')],
        ]);
    }
}
