<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'dashboard_quick_action_keys')) {
                $table->json('dashboard_quick_action_keys')->nullable()->after('dashboard_widget_keys');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'dashboard_quick_action_keys')) {
                $table->dropColumn('dashboard_quick_action_keys');
            }
        });
    }
};
