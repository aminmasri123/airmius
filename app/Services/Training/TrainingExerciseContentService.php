<?php

namespace App\Services\Training;

use App\Models\TrainingExercise;
use App\Models\TrainingPlanItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrainingExerciseContentService
{
    public const LOCALES = ['de', 'en', 'fr', 'ar'];

    public const FIELDS = ['title', 'description', 'instructions', 'notes', 'todos'];

    public function prepare(Request $request): void
    {
        // Multipart has no representation for an empty array. Explicit markers distinguish clearing from omission.
        $emptyFields = $request->input('content_empty_arrays', []);
        if (is_array($emptyFields)) {
            foreach (array_intersect(array_filter($emptyFields, 'is_string'), ['explanation_images', 'translations', 'todos', 'equipment', 'muscle_groups', 'focus_areas', 'sport_types']) as $field) {
                $request->merge([$field => []]);
            }
        }
        if ($request->input('explanation_images_empty') === '1') {
            $request->merge(['explanation_images' => []]);
        }
    }

    public function releaseUnusedImages(array $images): void
    {
        $candidates = collect($images)->pluck('path')->filter()->unique();
        if ($candidates->isEmpty()) {
            return;
        }
        // Duplicated exercises share immutable image files. Remove only files no item references.
        foreach ([TrainingPlanItem::class, TrainingExercise::class] as $model) {
            foreach ($model::query()->whereNotNull('explanation_images')->select('explanation_images')->cursor() as $item) {
                $candidates = $candidates->diff(collect($item->explanation_images)->pluck('path'));
                if ($candidates->isEmpty()) {
                    return;
                }
            }
        }
        Storage::disk('public')->delete($candidates->values()->all());
    }

    public function rules(): array
    {
        $rules = [
            'original_locale' => ['sometimes', 'required', Rule::in(self::LOCALES)],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'translations' => ['nullable', 'array:de,en,fr,ar'],
            'explanation_images' => ['sometimes', 'array', 'max:12'],
            'explanation_images.*' => ['array:id,file,texts'],
            'explanation_images.*.id' => ['nullable', 'string', 'max:80', 'distinct'],
            'explanation_images.*.file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'explanation_images.*.texts' => ['nullable', 'array:de,en,fr,ar'],
            'explanation_images.*.texts.*' => ['array:caption,alt'],
            'explanation_images.*.texts.*.caption' => ['nullable', 'string', 'max:500'],
            'explanation_images.*.texts.*.alt' => ['nullable', 'string', 'max:500'],
        ];
        foreach (self::LOCALES as $locale) {
            $rules["translations.$locale"] = ['nullable', 'array:title,description,instructions,notes,todos'];
            foreach (self::FIELDS as $field) {
                $rules["translations.$locale.$field"] = ['nullable', 'string', 'max:'.($field === 'title' ? 160 : ($field === 'instructions' ? 10000 : 3000))];
            }
        }

        return $rules;
    }

    public function payload(Request $request, array $data, TrainingPlanItem|TrainingExercise|null $item = null): array
    {
        $payload = [];
        foreach (['original_locale', 'instructions', 'notes', 'translations'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        if (! $item && ! isset($payload['original_locale'])) {
            $payload['original_locale'] = in_array(app()->getLocale(), self::LOCALES, true) ? app()->getLocale() : 'en';
        }
        if (array_key_exists('explanation_images', $data)) {
            $existing = collect($item?->explanation_images ?? [])->keyBy('id');
            // Retained IDs must belong to this item. Never accept client-supplied paths.
            foreach ($data['explanation_images'] as $index => $image) {
                if (! $request->hasFile("explanation_images.$index.file") && ! $existing->has($image['id'] ?? '')) {
                    throw ValidationException::withMessages(["explanation_images.$index.id" => __('validation.exists', ['attribute' => 'image'])]);
                }
            }
            $images = [];
            foreach ($data['explanation_images'] as $index => $image) {
                $file = $request->file("explanation_images.$index.file");
                $images[] = [
                    'id' => $file ? (string) Str::uuid() : $image['id'],
                    'path' => $file ? $file->store('training-plans/explanations', 'public') : $existing[$image['id']]['path'],
                    'texts' => $image['texts'] ?? ($existing->get($image['id'] ?? '')['texts'] ?? []),
                ];
            }
            $payload['explanation_images'] = $images;
        }

        return $payload;
    }

    public function imageUrl(TrainingPlanItem|TrainingExercise $item, string $imageId): string
    {
        $prefix = request()->is('api/*') ? 'api.v1.' : 'auth.';
        $route = $item instanceof TrainingExercise ? 'training.exercises.images.show' : 'training.items.images.show';

        return route($prefix.$route, [$item->getKey(), $imageId]);
    }

    public function imageResponse(TrainingPlanItem|TrainingExercise $item, string $imageId)
    {
        $path = $imageId === 'cover' ? $item->image_path
            : collect($item->explanation_images ?? [])->firstWhere('id', $imageId)['path'] ?? null;
        abort_unless(is_string($path) && preg_match('#^training-plans/(?:explanations/)?[a-zA-Z0-9._-]+\.(?:jpe?g|png|webp)$#i', $path), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function present(TrainingPlanItem|TrainingExercise $item, ?string $locale = null): array
    {
        $locale = strtolower(explode('-', str_replace('_', '-', $locale ?? app()->getLocale()))[0]);
        $original = $item->original_locale;
        $translation = $locale !== $original ? ($item->translations[$locale] ?? []) : [];
        $coverPath = $item->image_path ?: data_get($item->explanation_images, '0.path');
        $result = [
            'original_locale' => $original, 'content_locale' => $locale, 'uses_original_fallback' => false,
            'image_url' => $coverPath ? $this->imageUrl($item, $item->image_path ? 'cover' : (string) data_get($item->explanation_images, '0.id')) : null,
            'original_content' => collect(self::FIELDS)->mapWithKeys(fn ($field) => [$field => $item->$field])->all(),
        ];
        foreach (self::FIELDS as $field) {
            $value = $translation[$field] ?? null;
            $hasTranslation = is_string($value) && trim($value) !== '';
            $result[$field] = $hasTranslation
                ? ($field === 'todos' ? preg_split('/\r\n|\r|\n/', $value) : $value)
                : $item->$field;
            if ($original && $locale !== $original && ! $hasTranslation && filled($item->$field)) {
                $result['uses_original_fallback'] = true;
            }
        }
        $result['explanation_images'] = collect($item->explanation_images ?? [])->map(function ($image) use ($item, $locale, $original, &$result) {
            $localized = ['id' => $image['id'], 'url' => $this->imageUrl($item, (string) $image['id']), 'texts' => $image['texts'] ?? []];
            foreach (['caption', 'alt'] as $field) {
                $translated = data_get($image, "texts.$locale.$field");
                $base = data_get($image, "texts.$original.$field", '');
                $localized[$field] = filled($translated) ? $translated : $base;
                if ($locale !== $original && ! filled($translated) && filled($base)) {
                    $result['uses_original_fallback'] = true;
                }
            }

            return $localized;
        })->all();

        return $result;
    }
}
