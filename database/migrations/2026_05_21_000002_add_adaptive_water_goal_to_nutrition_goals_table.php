<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_goals', function (Blueprint $table) {
            if (! Schema::hasColumn('nutrition_goals', 'body_weight_kg')) {
                $table->decimal('body_weight_kg', 5, 1)->nullable()->after('water_target_ml');
            }

            if (! Schema::hasColumn('nutrition_goals', 'water_target_mode')) {
                $table->string('water_target_mode', 20)->default('manual')->after('body_weight_kg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_goals', function (Blueprint $table) {
            if (Schema::hasColumn('nutrition_goals', 'water_target_mode')) {
                $table->dropColumn('water_target_mode');
            }

            if (Schema::hasColumn('nutrition_goals', 'body_weight_kg')) {
                $table->dropColumn('body_weight_kg');
            }
        });
    }
};
