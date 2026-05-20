<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionMeal extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'eaten_on',
        'meal_type',
        'title',
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
        'fiber_g',
        'sugar_g',
        'water_ml',
        'source',
        'training_context',
        'items',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'eaten_on' => 'date',
            'calories' => 'integer',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
            'fiber_g' => 'float',
            'sugar_g' => 'float',
            'water_ml' => 'integer',
            'items' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
