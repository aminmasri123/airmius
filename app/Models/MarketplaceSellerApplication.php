<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceSellerApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'applicant_type',
        'business_name',
        'notes',
        'accepted_rules',
        'verification_version',
        'verification_snapshot',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_rules' => 'array',
            'verification_snapshot' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
