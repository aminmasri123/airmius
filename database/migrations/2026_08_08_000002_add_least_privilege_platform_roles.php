<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'logs.view' => 'Logs anzeigen',
            'api.manage' => 'API verwalten',
            'security.manage' => 'Sicherheit verwalten',
            'users.view' => 'Benutzer anzeigen',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description],
            );
        }

        Role::firstOrCreate(
            ['name' => 'platform_engineer', 'guard_name' => 'web'],
            ['description' => 'Technischer Betrieb ohne standardmäßigen Zugriff auf Produktiv-Personendaten'],
        )->syncPermissions(['logs.view', 'api.manage']);

        Role::firstOrCreate(
            ['name' => 'security_admin', 'guard_name' => 'web'],
            ['description' => 'Sicherheitsbetrieb und Incident Response ohne Commerce- oder Inhaltsrechte'],
        )->syncPermissions(['security.manage', 'logs.view', 'users.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()
            ->whereIn('name', ['platform_engineer', 'security_admin'])
            ->whereDoesntHave('users')
            ->get()
            ->each(fn (Role $role) => $role->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
