<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubCategoryAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_category_id', 'subject_type', 'subject_id', 'assigned_by',
    ];

    protected function casts(): array
    {
        return ['subject_id' => 'integer'];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function category()
    {
        return $this->belongsTo(ClubCategory::class, 'club_category_id');
    }
}
