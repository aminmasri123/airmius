<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'owner_user_id',
        'scope',
        'name',
        'legal_name',
        'country_code',
        'registration_number',
        'vat_id',
        'accepted_rules',
        'verification_version',
        'verification_status',
        'verification_note',
        'verification_requested_at',
        'verified_by',
        'verified_at',
        'contact_name',
        'email',
        'website',
        'logo',
        'logo_light',
        'logo_dark',
        'amount',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'accepted_rules' => 'array',
            'verification_requested_at' => 'datetime',
            'verified_at' => 'datetime',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function scopePubliclyVerified(Builder $query): Builder
    {
        return $query->where('verification_status', 'verified');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function outfitSubscriptionPlans()
    {
        return $this->hasMany(OutfitSubscriptionPlan::class);
    }

    public function outfitSubscriptions()
    {
        return $this->hasMany(OutfitSubscription::class);
    }
}
