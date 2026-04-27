<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'logo', 'owner_id'];

    public function scopeVisibleTo($query, $user)
    {
        if ($user->hasAnyRole(Roles::SYSTEM)) {
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
