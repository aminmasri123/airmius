<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubRoleAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scope_id' => 'integer'];
    }

    public function roleDefinition()
    {
        return $this->belongsTo(ClubRoleDefinition::class, 'club_role_definition_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
