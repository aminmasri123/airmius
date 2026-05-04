<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutfitStyleProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sport_focus',
        'sizes',
        'fit_preference',
        'colors',
        'excluded_colors',
        'brand_style',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sizes' => 'array',
            'colors' => 'array',
            'excluded_colors' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
