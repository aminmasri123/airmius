<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('support.safety', 'web');
        Permission::findOrCreate('support.tickets', 'web');
        $role = Role::firstOrCreate(['name' => 'safety_support', 'guard_name' => 'web'], [
            'description' => 'Sicherheit & Schutz: vertrauliche Supportfälle bearbeiten',
        ]);
        $role->givePermissionTo(['support.tickets', 'support.safety']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep granted access definitions during rollback; do not silently
        // remove manually maintained production role assignments.
    }
};
