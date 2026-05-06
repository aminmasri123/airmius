<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'event_radius_km')) {
                $table->unsignedSmallInteger('event_radius_km')->nullable()->after('state');
            }

            if (! Schema::hasColumn('users', 'event_default_sport_ids')) {
                $table->json('event_default_sport_ids')->nullable()->after('event_radius_km');
            }

            if (! Schema::hasColumn('users', 'event_default_filters')) {
                $table->json('event_default_filters')->nullable()->after('event_default_sport_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['event_default_filters', 'event_default_sport_ids', 'event_radius_km'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
