<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_user', 'permission_overrides')) {
                $table->json('permission_overrides')->nullable()->after('roles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_user', function (Blueprint $table): void {
            if (Schema::hasColumn('club_user', 'permission_overrides')) {
                $table->dropColumn('permission_overrides');
            }
        });
    }
};
