<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'team_id',
        'title',
        'kind',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function items()
    {
        return $this->hasMany(TrainingBlockItem::class)->orderBy('sort_order');
    }
}

