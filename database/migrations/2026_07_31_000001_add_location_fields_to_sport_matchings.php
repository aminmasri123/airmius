<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sport_matchings', 'location_name')) {
            Schema::table('sport_matchings', function (Blueprint $table) {
                $table->string('location_name', 160)->nullable()->after('city');
            });
        }

        if (! Schema::hasColumn('sport_matchings', 'address')) {
            Schema::table('sport_matchings', function (Blueprint $table) {
                $table->string('address', 240)->nullable()->after('location_name');
            });
        }

        Schema::table('sport_matchings', function (Blueprint $table) {
            $table->index(['location_name', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sport_matchings', function (Blueprint $table) {
            $table->dropIndex('sport_matchings_location_name_status_starts_at_index');
            $table->dropColumn(['location_name', 'address']);
        });
    }
};
