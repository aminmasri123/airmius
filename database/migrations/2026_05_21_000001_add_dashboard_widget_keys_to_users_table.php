<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'dashboard_widget_keys')) {
                $table->json('dashboard_widget_keys')->nullable()->after('event_default_filters');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'dashboard_widget_keys')) {
                $table->dropColumn('dashboard_widget_keys');
            }
        });
    }
};
