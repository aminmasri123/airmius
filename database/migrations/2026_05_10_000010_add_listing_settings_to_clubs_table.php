<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'is_listed')) {
                $table->boolean('is_listed')->default(true)->after('member_pause_requests_enabled');
            }

            if (! Schema::hasColumn('clubs', 'teams_are_listed')) {
                $table->boolean('teams_are_listed')->default(true)->after('is_listed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            foreach (['teams_are_listed', 'is_listed'] as $column) {
                if (Schema::hasColumn('clubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
