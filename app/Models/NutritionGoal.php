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
        'body_weight_kg',
        'water_target_mode',
        'water_reminders_per_day',
        'water_reminder_start_hour',
        'water_reminder_end_hour',
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
            'body_weight_kg' => 'float',
            'water_reminders_per_day' => 'integer',
            'water_reminder_start_hour' => 'integer',
            'water_reminder_end_hour' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
