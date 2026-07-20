<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'club_id',
        'learning_course_id',
        'title',
        'description',
        'features',
        'product_attributes',
        'attribute_options',
        'variants',
        'image_url',
        'gallery_images',
        'category',
        'offer_type',
        'product_type',
        'sku',
        'is_shippable',
        'manages_stock',
        'stock_quantity',
        'low_stock_threshold',
        'tax_class',
        'return_policy_type',
        'return_window_days',
        'digital_delivery_note',
        'course_outline',
        'learning_goals',
        'coaching_enabled',
        'coach_feedback_instructions',
        'price_cents',
        'currency',
        'available_countries',
        'status',
        'moderation_status',
        'rejection_reason',
        'commission_percent',
        'payout_status',
    ];

    protected function casts(): array
    {
        return [
            'is_shippable' => 'boolean',
            'features' => 'array',
            'product_attributes' => 'array',
            'attribute_options' => 'array',
            'variants' => 'array',
            'gallery_images' => 'array',
            'course_outline' => 'array',
            'learning_goals' => 'array',
            'learning_course_id' => 'integer',
            'coaching_enabled' => 'boolean',
            'manages_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'return_window_days' => 'integer',
            'price_cents' => 'integer',
            'available_countries' => 'array',
            'commission_percent' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function learningCourse()
    {
        return $this->belongsTo(LearningCourse::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(CommerceStockMovement::class);
    }

    public function inventories()
    {
        return $this->hasMany(MarketplaceProductInventory::class);
    }

    public function reviews()
    {
        return $this->hasMany(MarketplaceProductReview::class);
    }

    public function publishedReviews()
    {
        return $this->reviews()->where('status', 'published');
    }

    public function wishlists()
    {
        return $this->hasMany(MarketplaceProductWishlist::class);
    }

    public function isDigitalDelivery(): bool
    {
        return in_array($this->offer_type, ['online_course', 'training_plan'], true)
            || $this->product_type === 'digital';
    }

    public function sellableStockForCountry(?string $country = null): int
    {
        $country = strtoupper((string) $country);

        if (! (bool) $this->manages_stock) {
            return PHP_INT_MAX;
        }

        if ($this->relationLoaded('inventories')) {
            $inventories = $this->inventories
                ->where('is_active', true)
                ->when($country !== '', fn ($items) => $items->where('country_code', $country));

            if ($inventories->isNotEmpty()) {
                return $inventories->sum(fn (MarketplaceProductInventory $inventory) => $inventory->availableQuantity());
            }

            if ($this->inventories()->exists()) {
                return 0;
            }
        } elseif ($this->inventories()->exists()) {
            $query = $this->inventories()->where('is_active', true);

            if ($country !== '') {
                $query->where('country_code', $country);
            }

            return (int) $query->selectRaw('COALESCE(SUM(stock_quantity - reserved_quantity), 0) as available_quantity')->value('available_quantity');
        }

        return max(0, (int) $this->stock_quantity);
    }

    public function isAvailableForCountry(?string $country = null): bool
    {
        if ($this->isDigitalDelivery()) {
            return true;
        }

        $country = strtoupper((string) $country);
        $countries = collect($this->available_countries ?: [])
            ->map(fn ($value) => strtoupper((string) $value))
            ->filter()
            ->values();

        if ($country !== '' && $countries->isNotEmpty() && ! $countries->contains($country)) {
            return false;
        }

        return $this->sellableStockForCountry($country) > 0;
    }
}
