<?php

namespace App\Support;

use App\Models\MarketplaceProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceTaxonomy
{
    private static function definitions(): array
    {
        static $definitions;

        return $definitions ??= json_decode(file_get_contents(resource_path('js/marketplace-taxonomy.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function productRules(Request $request): array
    {
        return [
            ...self::categoryRules($request, true),
            'sports' => ['nullable', 'array', 'list', 'max:'.count(self::definitions()['sports'])],
            'sports.*' => ['required', 'string', 'distinct', Rule::in(array_keys(self::definitions()['sports']))],
        ];
    }

    public static function filterRules(Request $request): array
    {
        return [
            ...self::categoryRules($request, false),
            'sport' => ['nullable', 'string', Rule::in(array_keys(self::definitions()['sports']))],
        ];
    }

    private static function categoryRules(Request $request, bool $requireSubcategory): array
    {
        $definitions = self::definitions()['groups'];
        $group = $request->input('catalog_group');
        $children = is_string($group) ? ($definitions[$group]['children'] ?? []) : [];

        return [
            'catalog_group' => ['required_with:catalog_subcategory', 'nullable', 'string', Rule::in(array_keys($definitions))],
            'catalog_subcategory' => [
                Rule::requiredIf($requireSubcategory && $request->filled('catalog_group')),
                'nullable', 'string', Rule::in(array_keys($children)),
            ],
        ];
    }

    public static function productFields(MarketplaceProduct $product): array
    {
        return [
            'catalog_group' => $product->catalog_group,
            'catalog_subcategory' => $product->catalog_subcategory,
            'sports' => $product->sports ?? [],
        ];
    }

    public static function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['catalog_group'] ?? null, fn (Builder $query, string $group) => $query->where('catalog_group', $group))
            ->when($filters['catalog_subcategory'] ?? null, fn (Builder $query, string $subcategory) => $query->where('catalog_subcategory', $subcategory))
            ->when($filters['sport'] ?? null, fn (Builder $query, string $sport) => $query->whereJsonContains('sports', $sport));
    }

    public static function facets(Builder $catalog): array
    {
        $definitions = self::definitions();
        $classified = (clone $catalog)->whereNotNull('catalog_group')
            ->toBase()->distinct()->get(['catalog_group', 'catalog_subcategory'])
            ->groupBy('catalog_group');

        $groups = [];
        foreach ($definitions['groups'] as $key => $group) {
            $children = $classified->get($key)?->pluck('catalog_subcategory')->flip()->all() ?? [];
            $available = array_intersect_key($group['children'], $children);
            if ($available !== []) {
                $groups[$key] = ['label' => $group['label'], 'children' => $available];
            }
        }

        return [
            'groups' => (object) $groups,
            'sports' => (object) $definitions['sports'],
        ];
    }
}
