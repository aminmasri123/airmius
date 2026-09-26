<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sports', function (Blueprint $table): void {
            $table->json('sport_participation')->nullable()->after('performance_visibility');
            $table->json('development_goals')->nullable()->after('sport_participation');
            $table->json('sport_results')->nullable()->after('development_goals');
            $table->json('personal_bests')->nullable()->after('sport_results');
        });
    }

    public function down(): void
    {
        Schema::table('user_sports', function (Blueprint $table): void {
            $table->dropColumn([
                'sport_participation',
                'development_goals',
                'sport_results',
                'personal_bests',
            ]);
        });
    }
};
