<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_plan_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sport_type', 80)->nullable();
            $table->string('title');
            $table->string('status', 30)->default('completed');
            $table->dateTime('performed_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedInteger('calories')->nullable();
            $table->string('intensity', 30)->nullable();
            $table->text('notes')->nullable();
            $table->text('trainer_feedback')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'performed_at']);
            $table->index(['trainer_id', 'performed_at']);
            $table->index(['training_plan_item_id', 'user_id']);
        });

        Schema::create('training_log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_log_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('sets')->nullable();
            $table->unsignedSmallInteger('reps')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->string('intensity', 30)->nullable();
            $table->text('notes')->nullable();
            $table->json('metrics')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['training_log_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_log_entries');
        Schema::dropIfExists('training_logs');
    }
};
