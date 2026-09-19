<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_matchings', function (Blueprint $table) {
            $table->unsignedSmallInteger('own_team_size')->nullable()->after('team_size');
            $table->string('opponent_size_type', 16)->default('exact')->after('own_team_size');
        });
        Schema::table('sport_matching_applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('team_size')->nullable()->after('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('sport_matching_applications', fn (Blueprint $table) => $table->dropColumn('team_size'));
        Schema::table('sport_matchings', fn (Blueprint $table) => $table->dropColumn(['own_team_size', 'opponent_size_type']));
    }
};
