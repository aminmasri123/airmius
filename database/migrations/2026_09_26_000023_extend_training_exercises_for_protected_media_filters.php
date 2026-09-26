<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_exercises', function (Blueprint $table) {
            if (! Schema::hasColumn('training_exercises', 'target_age_group')) {
                $table->string('target_age_group', 40)->nullable()->after('sport_type');
            }
            if (! Schema::hasColumn('training_exercises', 'target_level')) {
                $table->string('target_level', 40)->nullable()->after('target_age_group');
            }
            if (! Schema::hasColumn('training_exercises', 'focus_areas')) {
                $table->json('focus_areas')->nullable()->after('muscle_groups');
            }
            if (! Schema::hasColumn('training_exercises', 'protected_media')) {
                $table->json('protected_media')->nullable()->after('focus_areas');
            }
        });

        Schema::table('training_exercises', function (Blueprint $table) {
            $table->index(['sport_type', 'target_age_group'], 'training_exercises_sport_age_index');
            $table->index(['target_level', 'is_active'], 'training_exercises_level_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('training_exercises', function (Blueprint $table) {
            $table->dropIndex('training_exercises_sport_age_index');
            $table->dropIndex('training_exercises_level_active_index');
            $table->dropColumn(['target_age_group', 'target_level', 'focus_areas', 'protected_media']);
        });
    }
};
