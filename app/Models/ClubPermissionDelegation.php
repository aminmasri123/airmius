<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClubPermissionDelegation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'scope_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>=', now());
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function grantor()
    {
        return $this->belongsTo(User::class, 'grantor_user_id');
    }

    public function grantee()
    {
        return $this->belongsTo(User::class, 'grantee_user_id');
    }
}
