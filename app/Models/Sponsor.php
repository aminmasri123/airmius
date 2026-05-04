<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'contact_name',
        'email',
        'website',
        'logo',
        'amount',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
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
