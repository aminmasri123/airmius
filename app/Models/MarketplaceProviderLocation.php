<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProviderLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_provider_profile_id',
        'name',
        'type',
        'country',
        'state',
        'postal_code',
        'city',
        'street',
        'house_number',
        'opening_hours',
        'note',
        'phone',
        'email',
        'image_url',
        'latitude',
        'longitude',
        'pickup_enabled',
        'returns_enabled',
        'is_public',
        'sort_order',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'pickup_enabled' => 'boolean',
        'returns_enabled' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProviderProfile::class, 'marketplace_provider_profile_id');
    }

    public function addressSummary(): string
    {
        $line = trim(implode(' ', array_filter([
            $this->street,
            $this->house_number,
        ])));
        $city = trim(implode(' ', array_filter([
            $this->postal_code,
            $this->city,
        ])));

        return trim(implode(', ', array_filter([
            $line,
            $city,
            strtoupper((string) $this->country),
        ]))) ?: 'Adresse folgt';
    }
}
