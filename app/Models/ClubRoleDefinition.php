<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubRoleDefinition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function assignments()
    {
        return $this->hasMany(ClubRoleAssignment::class);
    }
}
