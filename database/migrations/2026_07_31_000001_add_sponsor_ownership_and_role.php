<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sponsors', 'owner_user_id')) {
            Schema::table('sponsors', function (Blueprint $table) {
                $table->foreignId('owner_user_id')
                    ->nullable()
                    ->after('club_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        DB::table('sponsors')
            ->whereNull('owner_user_id')
            ->whereNotNull('email')
            ->orderBy('id')
            ->eachById(function (object $sponsor): void {
                $userId = DB::table('users')
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $sponsor->email)])
                    ->value('id');

                if ($userId) {
                    DB::table('sponsors')->where('id', $sponsor->id)->update([
                        'owner_user_id' => $userId,
                    ]);
                }
            });

        $permissions = [
            'sponsor.workspace.view' => 'Eigenen Sponsor-Arbeitsbereich anzeigen',
            'sponsor.profile.edit' => 'Eigenes Sponsorprofil bearbeiten',
        ];
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description],
            );
        }

        Role::firstOrCreate(
            ['name' => 'sponsor', 'guard_name' => 'web'],
            ['description' => 'Eigene Sponsorprofile, Partnerschaften und Kampagnen verwalten'],
        )->givePermissionTo(array_keys($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if (Schema::hasColumn('sponsors', 'owner_user_id')) {
            Schema::table('sponsors', function (Blueprint $table) {
                $table->dropConstrainedForeignId('owner_user_id');
            });
        }
    }
};
