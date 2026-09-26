<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubLocation extends Model
{
    use HasFactory;

    protected $fillable = ['club_id', 'name', 'street', 'house_number', 'postal_code', 'city', 'country', 'notes', 'is_public'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function trainingGroups()
    {
        return $this->hasMany(ClubTrainingGroup::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }
}
