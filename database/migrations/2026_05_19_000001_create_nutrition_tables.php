<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('goal_type', 40)->default('maintain');
            $table->unsignedInteger('daily_calories_target')->nullable();
            $table->unsignedInteger('protein_target_g')->nullable();
            $table->unsignedInteger('carbs_target_g')->nullable();
            $table->unsignedInteger('fat_target_g')->nullable();
            $table->unsignedInteger('water_target_ml')->nullable();
            $table->string('diet_style', 40)->default('balanced');
            $table->json('allergies')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['goal_type', 'diet_style']);
        });

        Schema::create('nutrition_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('eaten_on');
            $table->string('meal_type', 40)->default('snack');
            $table->string('title', 160);
            $table->unsignedInteger('calories')->default(0);
            $table->decimal('protein_g', 8, 1)->default(0);
            $table->decimal('carbs_g', 8, 1)->default(0);
            $table->decimal('fat_g', 8, 1)->default(0);
            $table->decimal('fiber_g', 8, 1)->nullable();
            $table->decimal('sugar_g', 8, 1)->nullable();
            $table->unsignedInteger('water_ml')->nullable();
            $table->string('source', 40)->default('manual');
            $table->string('training_context', 80)->nullable();
            $table->json('items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'eaten_on']);
            $table->index(['user_id', 'meal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_meals');
        Schema::dropIfExists('nutrition_goals');
    }
};
