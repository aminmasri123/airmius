<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NutritionFoodLookupService
{
    public function search(string $query, int $limit = 8): array
    {
        $response = $this->client()->get($this->baseUrl().'/cgi/search.pl', [
            'search_terms' => $query,
            'search_simple' => 1,
            'action' => 'process',
            'json' => 1,
            'page_size' => max(1, min($limit, 20)),
            'fields' => implode(',', $this->fields()),
        ]);

        if (! $response->ok()) {
            return [];
        }

        return collect($response->json('products', []))
            ->map(fn (array $product) => $this->normalizeProduct($product))
            ->filter(fn (?array $product) => $product !== null)
            ->values()
            ->all();
    }

    public function barcode(string $barcode): ?array
    {
        $cleanBarcode = preg_replace('/\D+/', '', $barcode);

        if (! $cleanBarcode) {
            return null;
        }

        $response = $this->client()->get($this->baseUrl()."/api/v2/product/{$cleanBarcode}.json", [
            'fields' => implode(',', $this->fields()),
        ]);

        if (! $response->ok() || (int) $response->json('status', 0) !== 1) {
            return null;
        }

        return $this->normalizeProduct((array) $response->json('product', []));
    }

    private function normalizeProduct(array $product): ?array
    {
        $nutriments = (array) ($product['nutriments'] ?? []);
        $title = trim((string) ($product['product_name'] ?? $product['generic_name'] ?? ''));

        if ($title === '') {
            return null;
        }

        return [
            'source' => 'open_food_facts',
            'code' => (string) ($product['code'] ?? ''),
            'title' => $title,
            'brand' => trim((string) ($product['brands'] ?? '')),
            'image_url' => $product['image_front_small_url'] ?? null,
            'serving_size' => $product['serving_size'] ?? null,
            'nutriscore_grade' => isset($product['nutriscore_grade']) ? strtoupper((string) $product['nutriscore_grade']) : null,
            'nova_group' => $product['nova_group'] ?? null,
            'calories' => $this->nutrimentValue($nutriments, 'energy-kcal'),
            'protein_g' => $this->nutrimentValue($nutriments, 'proteins'),
            'carbs_g' => $this->nutrimentValue($nutriments, 'carbohydrates'),
            'fat_g' => $this->nutrimentValue($nutriments, 'fat'),
            'fiber_g' => $this->nutrimentValue($nutriments, 'fiber'),
            'sugar_g' => $this->nutrimentValue($nutriments, 'sugars'),
            'quantity_label' => $this->quantityLabel($product),
            'attribution' => 'Open Food Facts',
        ];
    }

    private function nutrimentValue(array $nutriments, string $key): int|float
    {
        $value = $nutriments[$key.'_serving'] ?? $nutriments[$key.'_100g'] ?? 0;
        $number = is_numeric($value) ? (float) $value : 0;

        return Str::contains($key, 'energy') ? (int) round($number) : round($number, 1);
    }

    private function quantityLabel(array $product): string
    {
        $serving = trim((string) ($product['serving_size'] ?? ''));

        if ($serving !== '') {
            return $serving;
        }

        $quantity = trim((string) ($product['quantity'] ?? ''));

        return $quantity !== '' ? $quantity : '1 Portion / 100 g';
    }

    private function client()
    {
        return Http::acceptJson()
            ->withUserAgent((string) config('nutrition.open_food_facts.user_agent'))
            ->timeout((int) config('nutrition.open_food_facts.timeout_seconds', 5));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('nutrition.open_food_facts.base_url'), '/');
    }

    private function fields(): array
    {
        return [
            'code',
            'product_name',
            'generic_name',
            'brands',
            'quantity',
            'serving_size',
            'nutriments',
            'image_front_small_url',
            'nutriscore_grade',
            'nova_group',
        ];
    }
}
