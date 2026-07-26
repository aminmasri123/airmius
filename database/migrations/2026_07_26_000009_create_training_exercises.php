<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('name', 160);
            $table->string('sport_type', 80)->nullable();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->json('equipment')->nullable();
            $table->json('muscle_groups')->nullable();
            $table->string('difficulty', 32)->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['club_id', 'is_active']);
            $table->index(['team_id', 'is_active']);
            $table->index(['created_by', 'is_active']);
        });

        if (! Schema::hasColumn('training_plan_items', 'source_exercise_id')) {
            Schema::table('training_plan_items', function (Blueprint $table) {
                $table->foreignId('source_exercise_id')
                    ->nullable()
                    ->after('title')
                    ->constrained('training_exercises')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('training_plan_items', 'source_exercise_id')) {
            Schema::table('training_plan_items', function (Blueprint $table) {
                $table->dropForeign(['source_exercise_id']);
                $table->dropColumn('source_exercise_id');
            });
        }

        Schema::dropIfExists('training_exercises');
    }
};
