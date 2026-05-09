<?php

use App\Support\Roles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'outfit-subscriptions.view' => 'Sportkleidung-Abos verwenden',
            'outfit-subscriptions.manage' => 'Sportkleidung-Abo-Modul verwalten',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description],
            );
        }

        Role::query()
            ->whereIn('name', Roles::FULL_ACCESS)
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo(array_keys($permissions)));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
