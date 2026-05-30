<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceProviderProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'club_id',
        'display_name',
        'legal_name',
        'provider_type',
        'support_email',
        'phone',
        'website',
        'logo_url',
        'public_description',
        'legal_country',
        'legal_state',
        'legal_postal_code',
        'legal_city',
        'legal_street',
        'legal_house_number',
        'show_public_address',
        'show_support_email',
        'show_phone',
        'status',
    ];

    protected $casts = [
        'show_public_address' => 'boolean',
        'show_support_email' => 'boolean',
        'show_phone' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(MarketplaceProviderLocation::class);
    }

    public function publicName(): string
    {
        return trim((string) ($this->display_name ?: $this->club?->name ?: $this->user?->name ?: 'Airmius Anbieter'));
    }

    public function publicAddressSummary(): ?string
    {
        if (! $this->show_public_address) {
            return null;
        }

        $line = trim(implode(' ', array_filter([
            $this->legal_street,
            $this->legal_house_number,
        ])));
        $city = trim(implode(' ', array_filter([
            $this->legal_postal_code,
            $this->legal_city,
        ])));

        return trim(implode(', ', array_filter([
            $line,
            $city,
            strtoupper((string) $this->legal_country),
        ]))) ?: null;
    }
}
