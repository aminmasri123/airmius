<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_games', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('opponent', 120);
            $table->dateTime('scheduled_at')->nullable();
            $table->string('sport_type', 80);
            $table->timestamps();
            $table->index(['competition_id', 'scheduled_at']);
        });

        Schema::table('competition_lineups', function (Blueprint $table): void {
            $table->foreignId('competition_game_id')->nullable()->after('event_id')->constrained('competition_games')->cascadeOnDelete();
            $table->unique('competition_game_id');
        });

        Schema::table('competition_lineup_entries', function (Blueprint $table): void {
            $table->string('shirt_number', 8)->nullable()->after('position');
            $table->unsignedTinyInteger('board_x')->nullable()->after('shirt_number');
            $table->unsignedTinyInteger('board_y')->nullable()->after('board_x');
            $table->unique(['competition_lineup_id', 'shirt_number'], 'lineup_shirt_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('competition_lineup_entries', function (Blueprint $table): void {
            $table->dropUnique('lineup_shirt_number_unique');
            $table->dropColumn(['shirt_number', 'board_x', 'board_y']);
        });
        Schema::table('competition_lineups', function (Blueprint $table): void {
            $table->dropUnique(['competition_game_id']);
            $table->dropConstrainedForeignId('competition_game_id');
        });
        Schema::dropIfExists('competition_games');
    }
};
