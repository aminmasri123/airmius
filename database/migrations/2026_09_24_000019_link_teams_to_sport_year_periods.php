<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('sport_year_period_id')
                ->nullable()
                ->after('club_id')
                ->constrained('club_year_periods')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', fn (Blueprint $table) => $table->dropConstrainedForeignId('sport_year_period_id'));
    }
};
