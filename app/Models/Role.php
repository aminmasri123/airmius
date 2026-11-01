<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'description',
        'guard_name',
    ];

    public function users(): BelongsToMany
    {
        // Web sessions and Sanctum both authenticate the application's users.
        // Sanctum has no provider for Spatie to infer during withCount().
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }
}
