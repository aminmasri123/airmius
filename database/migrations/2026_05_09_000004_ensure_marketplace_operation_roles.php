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
            'marketplace.manage' => 'Marketplace verwalten',
            'commerce.orders.manage' => 'Marketplace-Bestellungen verwalten',
            'outfit-subscriptions.view' => 'Sportkleidung-Abos verwenden',
            'outfit-subscriptions.manage' => 'Sportkleidung-Abo-Modul verwalten',
            'subscriptions.manage' => 'Abonnements verwalten',
            'finance.view' => 'Finanzen anzeigen',
            'finance.edit' => 'Finanzen bearbeiten',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description],
            );
        }

        $rolePermissions = [
            'marketplace_manager' => [
                'marketplace.manage',
                'commerce.orders.manage',
                'subscriptions.manage',
                'finance.view',
                'outfit-subscriptions.view',
                'outfit-subscriptions.manage',
            ],
            'outfit_subscription_manager' => [
                'outfit-subscriptions.view',
                'outfit-subscriptions.manage',
                'subscriptions.manage',
            ],
            'sponsor_manager' => [
                'finance.view',
                'finance.edit',
                'outfit-subscriptions.view',
                'outfit-subscriptions.manage',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['description' => match ($roleName) {
                    'marketplace_manager' => 'Marketplace, Bestellungen und Commerce-Prozesse verwalten',
                    'outfit_subscription_manager' => 'Sportkleidung-Abos und Outfit-Prozesse verwalten',
                    'sponsor_manager' => 'Sponsoren und Sponsor-Subventionen verwalten',
                }],
            )->givePermissionTo($permissionNames);
        }

        Role::query()
            ->whereIn('name', array_unique(array_merge(Roles::FULL_ACCESS, Roles::MARKETPLACE_OPERATIONS)))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo(['outfit-subscriptions.view']));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
