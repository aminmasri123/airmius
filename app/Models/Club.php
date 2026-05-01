<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Club $club) {
            if (! $club->owner_id) {
                return;
            }

            $club->users()->syncWithoutDetaching([
                $club->owner_id => ['role' => 'owner'],
            ]);
        });
    }

    protected $fillable = [
        'name',
        'sport_type',
        'logo',
        'cover_image',
        'country',
        'street',
        'house_number',
        'postal_code',
        'city',
        'state',
        'owner_id',
    ];

    public function scopeVisibleTo($query, $user)
    {
        if (
            $user->hasAnyRole(Roles::FULL_ACCESS)
            || $user->can('clubs.view')
            || $user->can('teams.view')
        ) {
            return $query;
        }

        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function sponsors()
    {
        return $this->hasMany(Sponsor::class);
    }

    public function jobs()
    {
        return $this->hasMany(OrganizationJob::class);
    }

    public function admins()
    {
        return $this->users()->wherePivotIn('role', ['owner', 'admin']);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }
}
