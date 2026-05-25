<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'blog.view' => 'Blogbeiträge anzeigen',
            'blog.create' => 'Blogbeiträge erstellen',
            'blog.update' => 'Blogbeiträge bearbeiten',
            'blog.delete' => 'Blogbeiträge löschen',
            'blog.publish' => 'Blogbeiträge veröffentlichen',
            'blog.manage' => 'Blog-CMS verwalten',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        $redaktor = Role::firstOrCreate(
            ['name' => 'redaktor'],
            ['description' => 'Website-Redaktion für Blog und Inhalte']
        );

        $redaktor->syncPermissions([
            'blog.view',
            'blog.create',
            'blog.update',
        ]);

        foreach (['admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                $role->givePermissionTo(array_keys($permissions));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $redaktor = Role::where('name', 'redaktor')->first();

        if ($redaktor) {
            $redaktor->delete();
        }

        Permission::whereIn('name', [
            'blog.view',
            'blog.create',
            'blog.update',
            'blog.delete',
            'blog.publish',
            'blog.manage',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
