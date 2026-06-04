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
}
