<?php

namespace App\Support;

class MarketplaceProductInput
{
    public static function attributesFromText(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(function (string $line) {
                [$name, $value] = array_pad(preg_split('/[:=]/', $line, 2), 2, '');

                return [
                    'name' => trim($name),
                    'value' => trim($value),
                ];
            })
            ->filter(fn (array $attribute) => filled($attribute['name']) && filled($attribute['value']))
            ->take(20)
            ->values()
            ->all();
    }

    public static function linesFromText(string $text, int $limit): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    public static function normalizeAttributeOptions(array $options): array
    {
        return collect($options)
            ->map(function (array $option) {
                $values = collect($option['values'] ?? [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->take(30)
                    ->values()
                    ->all();

                return [
                    'name' => trim((string) ($option['name'] ?? '')),
                    'values' => $values,
                ];
            })
            ->filter(fn (array $option) => filled($option['name']) && $option['values'] !== [])
            ->take(20)
            ->values()
            ->all();
    }

    public static function normalizeVariants(array $variants, array $attributeOptions, int $fallbackPriceCents): array
    {
        $allowed = collect($attributeOptions)
            ->mapWithKeys(fn (array $option) => [$option['name'] => $option['values']])
            ->all();

        return collect($variants)
            ->map(function (array $variant) use ($allowed, $fallbackPriceCents) {
                $attributes = collect($variant['attributes'] ?? [])
                    ->map(fn (array $attribute) => [
                        'name' => trim((string) ($attribute['name'] ?? '')),
                        'value' => trim((string) ($attribute['value'] ?? '')),
                    ])
                    ->filter(fn (array $attribute) => filled($attribute['name'])
                        && filled($attribute['value'])
                        && in_array($attribute['value'], $allowed[$attribute['name']] ?? [], true))
                    ->values()
                    ->all();

                return [
                    'sku' => filled($variant['sku'] ?? null) ? trim((string) $variant['sku']) : null,
                    'price_cents' => (int) ($variant['price_cents'] ?? $fallbackPriceCents),
                    'stock_quantity' => ($variant['stock_quantity'] ?? null) === null || ($variant['stock_quantity'] ?? '') === ''
                        ? null
                        : max(0, (int) $variant['stock_quantity']),
                    'image_url' => filled($variant['image_url'] ?? null) ? trim((string) $variant['image_url']) : null,
                    'attributes' => $attributes,
                ];
            })
            ->filter(fn (array $variant) => $variant['attributes'] !== [])
            ->take(80)
            ->values()
            ->all();
    }

    public static function normalizeTeamwearPersonalizationRules(array $rules): array
    {
        $rawTypes = collect($rules['allowed_types'] ?? [])
            ->map(fn ($type) => trim((string) $type))
            ->filter(fn (string $type) => in_array($type, ['name', 'initials', 'number'], true))
            ->values();

        $types = $rawTypes
            ->unique()
            ->values()
            ->all();

        $blockedTerms = collect($rules['blocked_terms'] ?? [])
            ->map(fn ($term) => mb_strtolower(trim((string) $term)))
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();

        return [
            'allowed_types' => $types,
            'requires_team_member' => (bool) ($rules['requires_team_member'] ?? false),
            'privacy_acknowledgement_required' => (bool) ($rules['privacy_acknowledgement_required'] ?? true),
            'number_min' => isset($rules['number_min']) ? max(0, (int) $rules['number_min']) : null,
            'number_max' => isset($rules['number_max']) ? min(999, max(0, (int) $rules['number_max'])) : null,
            'blocked_terms' => $blockedTerms,
            'has_duplicate_types' => $rawTypes->count() !== $rawTypes->unique()->count(),
        ];
    }

    public static function teamwearPersonalizationConflicts(array $rules): array
    {
        $conflicts = [];
        $types = $rules['allowed_types'] ?? [];

        if ($types !== [] && ! ($rules['privacy_acknowledgement_required'] ?? false)) {
            $conflicts['privacy_acknowledgement_required'] = 'Personalisierte Vereinskleidung braucht eine Datenschutzbestätigung.';
        }

        if (in_array('number', $types, true)
            && $rules['number_min'] !== null
            && $rules['number_max'] !== null
            && $rules['number_min'] > $rules['number_max']) {
            $conflicts['number_range'] = 'Die kleinste Rückennummer darf nicht größer als die größte sein.';
        }

        if ($rules['has_duplicate_types'] ?? false) {
            $conflicts['allowed_types'] = 'Personalisierungsarten dürfen nicht doppelt vorkommen.';
        }

        return $conflicts;
    }
}
