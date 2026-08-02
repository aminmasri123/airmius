<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'enabled_navigation_modules')) {
                $table->json('enabled_navigation_modules')->nullable()->after('dashboard_widget_keys');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'enabled_navigation_modules')) {
                $table->dropColumn('enabled_navigation_modules');
            }
        });
    }
};
