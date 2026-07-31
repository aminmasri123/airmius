<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sport_matchings', 'postal_code')) {
            Schema::table('sport_matchings', function (Blueprint $table) {
                $table->string('postal_code', 20)->nullable()->after('city');
            });
        }

        Schema::table('sport_matchings', function (Blueprint $table) {
            $table->index(['postal_code', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sport_matchings', function (Blueprint $table) {
            $table->dropIndex('sport_matchings_postal_code_status_starts_at_index');
            $table->dropColumn('postal_code');
        });
    }
};
