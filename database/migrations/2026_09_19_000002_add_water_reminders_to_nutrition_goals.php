<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_goals', function (Blueprint $table) {
            $table->unsignedTinyInteger('water_reminders_per_day')->default(0);
            $table->unsignedTinyInteger('water_reminder_start_hour')->default(10);
            $table->unsignedTinyInteger('water_reminder_end_hour')->default(16);
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_goals', function (Blueprint $table) {
            $table->dropColumn([
                'water_reminders_per_day',
                'water_reminder_start_hour',
                'water_reminder_end_hour',
            ]);
        });
    }
};
