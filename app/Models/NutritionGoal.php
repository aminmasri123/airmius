<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NutritionGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'goal_type',
        'daily_calories_target',
        'protein_target_g',
        'carbs_target_g',
        'fat_target_g',
        'water_target_ml',
        'diet_style',
        'allergies',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allergies' => 'array',
            'daily_calories_target' => 'integer',
            'protein_target_g' => 'integer',
            'carbs_target_g' => 'integer',
            'fat_target_g' => 'integer',
            'water_target_ml' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
