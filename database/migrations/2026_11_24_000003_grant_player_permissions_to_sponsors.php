<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    private const PLAYER_PERMISSIONS = [
        'event.join',
        'post.create',
        'post.update',
        'post.delete',
        'file.view',
        'follow.user',
        'clubs.view',
        'teams.view',
        'training.view',
        'matches.view',
        'players.stats.view',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $sponsor = Role::query()
            ->where('name', 'sponsor')
            ->where('guard_name', 'web')
            ->first();

        if (! $sponsor) {
            return;
        }

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::PLAYER_PERMISSIONS)
            ->get();

        $sponsor->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $sponsor = Role::query()
            ->where('name', 'sponsor')
            ->where('guard_name', 'web')
            ->first();

        if (! $sponsor) {
            return;
        }

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            // file.view belonged to sponsors before this migration and must
            // remain intact when rolling back.
            ->whereIn('name', array_values(array_diff(self::PLAYER_PERMISSIONS, ['file.view'])))
            ->get();

        $sponsor->revokePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
