<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('trust_score')->default(100)->after('profile_visibility');
            $table->unsignedSmallInteger('gamification_streak_days')->default(0)->after('trust_score');
            $table->date('gamification_last_active_on')->nullable()->after('gamification_streak_days');
        });

        Schema::table('gamification_xp_events', function (Blueprint $table) {
            $table->integer('base_amount')->nullable()->after('amount');
            $table->decimal('trust_multiplier', 3, 2)->default(1)->after('base_amount');
            $table->boolean('limited_by_daily_cap')->default(false)->after('trust_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('gamification_xp_events', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'trust_multiplier', 'limited_by_daily_cap']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['trust_score', 'gamification_streak_days', 'gamification_last_active_on']);
        });
    }
};
