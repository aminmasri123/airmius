<?php

namespace App\Services;

use App\Models\LearningCourse;
use App\Models\MarketplaceProduct;
use App\Models\OutfitSubscriptionPlan;
use App\Support\UploadStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CommerceCatalogService
{
    public const KINDS = ['product', 'course', 'outfit_subscription'];

    /**
     * Build a bounded, privacy-minimised public catalogue shared by web and API.
     * At most one query per requested kind is executed; totals intentionally
     * describe the returned window so discovery never needs COUNT queries.
     */
    public function discover(array $filters = []): array
    {
        $limit = min(12, max(1, (int) ($filters['limit'] ?? 6)));
        $country = strtoupper((string) ($filters['country'] ?? 'DE'));
        $search = trim((string) ($filters['q'] ?? ''));
        $sport = trim((string) ($filters['sport'] ?? ''));
        $kinds = $this->normaliseKinds($filters['kind'] ?? null);
        $sections = collect();

        if (in_array('product', $kinds, true)) {
            $sections->put('product', $this->products($limit, $country, $search, $sport, $filters));
        }

        if (in_array('course', $kinds, true)) {
            $sections->put('course', $this->courses($limit, $search, $sport));
        }

        if (in_array('outfit_subscription', $kinds, true)) {
            $sections->put('outfit_subscription', $this->outfitPlans($limit, $search, $sport));
        }

        $data = $sections->flatten(1)->values();

        return [
            'data' => $data->all(),
            'sections' => $sections->map(fn (Collection $items, string $kind): array => [
                'kind' => $kind,
                'label' => __("commerce.catalog.kinds.{$kind}"),
                'items' => $items->values()->all(),
            ])->values()->all(),
            'meta' => [
                'contract' => 'commerce-card.v1',
                'country' => $country,
                'limit_per_kind' => $limit,
                'returned' => $data->count(),
                'kinds' => $kinds,
                'labels' => [
                    'eyebrow' => __('commerce.catalog.eyebrow'),
                    'title' => __('commerce.catalog.title'),
                    'description' => __('commerce.catalog.description'),
                    'view' => __('commerce.catalog.view'),
                    'monthly' => __('commerce.catalog.monthly'),
                ],
            ],
        ];
    }

    private function products(int $limit, string $country, string $search, string $sport, array $filters): Collection
    {
        $query = MarketplaceProduct::query()
            ->select([
                'id',
                'title',
                'description',
                'image_url',
                'category',
                'offer_type',
                'product_type',
                'is_shippable',
                'manages_stock',
                'stock_quantity',
                'price_cents',
                'currency',
                'available_countries',
                'learning_course_id',
                'created_at',
            ])
            ->where('status', 'published')
            ->where('moderation_status', 'approved')
            ->whereNull('learning_course_id')
            ->where(function (Builder $query) use ($country): void {
                $query
                    ->whereIn('offer_type', ['online_course', 'training_plan', 'service'])
                    ->orWhere('product_type', 'digital')
                    ->orWhere(function (Builder $query) use ($country): void {
                        $query->where('manages_stock', true)
                            ->where(function (Builder $query) use ($country): void {
                                $query->whereHas('inventories', fn (Builder $inventory) => $inventory
                                    ->where('is_active', true)
                                    ->where('country_code', $country)
                                    ->whereColumn('stock_quantity', '>', 'reserved_quantity'))
                                    ->orWhere(function (Builder $legacy): void {
                                        $legacy->whereDoesntHave('inventories')
                                            ->whereNotNull('stock_quantity')
                                            ->where('stock_quantity', '>', 0);
                                    });
                            });
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('manages_stock', false)
                            ->whereIn('category', ['camp', 'service']);
                    });
            })
            ->where(function (Builder $query) use ($country): void {
                $query->whereIn('offer_type', ['online_course', 'training_plan', 'service'])
                    ->orWhere('product_type', 'digital')
                    ->orWhereNull('available_countries')
                    ->orWhereJsonLength('available_countries', 0)
                    ->orWhereJsonContains('available_countries', $country);
            });

        $marketplaceCategory = $filters['marketplace_category'] ?? null;
        if (in_array($marketplaceCategory, ['product', 'camp', 'service'], true)) {
            $query->where('category', $marketplaceCategory);
        }

        $this->applySearch($query, $search, ['title', 'description', 'category']);
        $this->applySearch($query, $sport, ['title', 'description']);

        return $query
            ->orderByRaw("CASE WHEN image_url IS NULL OR image_url = '' THEN 1 ELSE 0 END")
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (MarketplaceProduct $product): array => $this->productCard($product));
    }

    private function courses(int $limit, string $search, string $sport): Collection
    {
        $query = LearningCourse::query()
            ->select([
                'id',
                'title',
                'slug',
                'subtitle',
                'description',
                'category',
                'sport_type',
                'level',
                'language',
                'cover_image',
                'is_free',
                'price_cents',
                'currency',
                'estimated_minutes',
                'featured_at',
                'published_at',
            ])
            ->where('status', 'published')
            ->where('is_public', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));

        $this->applySearch($query, $search, ['title', 'subtitle', 'description', 'category', 'sport_type']);
        $this->applySearch($query, $sport, ['sport_type', 'title']);

        return $query
            ->orderByDesc('featured_at')
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (LearningCourse $course): array => $this->courseCard($course));
    }

    private function outfitPlans(int $limit, string $search, string $sport): Collection
    {
        $query = OutfitSubscriptionPlan::query()
            ->select([
                'id',
                'name',
                'slug',
                'description',
                'monthly_price_cents',
                'sponsor_discount_cents',
                'currency',
                'sports',
                'items_per_box',
                'sort_order',
            ])
            ->where('is_active', true)
            ->where('is_public', true);

        $this->applySearch($query, $search, ['name', 'description']);
        if ($sport !== '') {
            $query->where(fn (Builder $query) => $query
                ->whereJsonContains('sports', $sport)
                ->orWhere('name', 'like', $this->like($sport))
                ->orWhere('description', 'like', $this->like($sport)));
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('monthly_price_cents')
            ->limit($limit)
            ->get()
            ->map(fn (OutfitSubscriptionPlan $plan): array => $this->outfitCard($plan));
    }

    private function productCard(MarketplaceProduct $product): array
    {
        $deliveryType = match (true) {
            in_array($product->offer_type, ['online_course', 'training_plan'], true), $product->product_type === 'digital' => 'digital',
            $product->offer_type === 'service', $product->category === 'service' => 'service',
            (bool) $product->is_shippable => 'shipping',
            default => 'pickup',
        };

        return $this->card(
            kind: 'product',
            id: $product->id,
            title: $product->title,
            summary: $product->description,
            imageUrl: $product->image_url,
            amountCents: (int) $product->price_cents,
            currency: $product->currency,
            deliveryType: $deliveryType,
            targetUrl: route('guest.marketplace.products.show', $product),
            badge: __('commerce.catalog.badges.product'),
            metadata: [
                'category' => $product->category,
                'offer_type' => $product->offer_type,
                'stock_quantity' => $product->manages_stock ? max(0, (int) $product->stock_quantity) : null,
            ],
        );
    }

    private function courseCard(LearningCourse $course): array
    {
        return $this->card(
            kind: 'course',
            id: $course->id,
            title: $course->title,
            summary: $course->subtitle ?: $course->description,
            imageUrl: UploadStorage::url($course->cover_image),
            amountCents: $course->is_free ? 0 : (int) $course->price_cents,
            currency: $course->currency,
            deliveryType: 'enrollment',
            targetUrl: route('guest.learning.courses.show', $course),
            badge: $course->is_free ? __('commerce.catalog.badges.free') : __('commerce.catalog.badges.course'),
            metadata: [
                'category' => $course->category,
                'sport' => $course->sport_type,
                'level' => $course->level,
                'language' => $course->language,
                'estimated_minutes' => (int) $course->estimated_minutes,
                'is_free' => (bool) $course->is_free,
            ],
        );
    }

    private function outfitCard(OutfitSubscriptionPlan $plan): array
    {
        return $this->card(
            kind: 'outfit_subscription',
            id: $plan->id,
            title: $plan->name,
            summary: $plan->description,
            imageUrl: null,
            amountCents: $plan->effectiveMonthlyPriceCents(),
            currency: $plan->currency,
            deliveryType: 'subscription',
            targetUrl: route('auth.outfit-subscriptions.index', ['plan' => $plan->id]),
            badge: $plan->sponsor_discount_cents > 0
                ? __('commerce.catalog.badges.sponsored')
                : __('commerce.catalog.badges.subscription'),
            metadata: [
                'sports' => $plan->sports ?: [],
                'items_per_box' => (int) $plan->items_per_box,
                'original_amount_cents' => $plan->sponsor_discount_cents > 0 ? (int) $plan->monthly_price_cents : null,
            ],
            billingInterval: 'month',
            requiresAuth: true,
        );
    }

    private function card(
        string $kind,
        int $id,
        string $title,
        ?string $summary,
        ?string $imageUrl,
        int $amountCents,
        ?string $currency,
        string $deliveryType,
        string $targetUrl,
        string $badge,
        array $metadata,
        ?string $billingInterval = null,
        bool $requiresAuth = false,
    ): array {
        return [
            'key' => "{$kind}:{$id}",
            'id' => $id,
            'kind' => $kind,
            'kind_label' => __("commerce.catalog.kinds.{$kind}"),
            'title' => $title,
            'summary' => $this->summary($summary),
            'image_url' => $imageUrl,
            'price' => [
                'amount_cents' => max(0, $amountCents),
                'currency' => strtoupper((string) ($currency ?: 'EUR')),
                'billing_interval' => $billingInterval,
            ],
            'delivery_type' => $deliveryType,
            'target_url' => $targetUrl,
            'requires_auth' => $requiresAuth,
            'badge' => $badge,
            'metadata' => collect($metadata)->reject(fn ($value) => $value === null || $value === '')->all(),
        ];
    }

    private function normaliseKinds(mixed $requested): array
    {
        $requested = is_array($requested) ? $requested : ($requested ? [$requested] : self::KINDS);

        return collect($requested)
            ->map(fn ($kind) => (string) $kind)
            ->filter(fn (string $kind) => in_array($kind, self::KINDS, true))
            ->unique()
            ->values()
            ->all() ?: self::KINDS;
    }

    private function applySearch(Builder $query, string $search, array $columns): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($columns, $search): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($column, 'like', $this->like($search));
            }
        });
    }

    private function like(string $value): string
    {
        return '%'.addcslashes($value, '\\%_').'%';
    }

    private function summary(?string $value): ?string
    {
        $withoutExecutableContent = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', (string) $value) ?: '';
        $plain = Str::squish(strip_tags($withoutExecutableContent));

        return $plain === '' ? null : Str::limit($plain, 180);
    }
}
