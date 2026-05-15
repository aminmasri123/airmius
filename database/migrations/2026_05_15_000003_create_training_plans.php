<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cadence', 30)->default('single');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('share_permission', 30)->default('read');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'status']);
            $table->index(['team_id', 'starts_on']);
        });

        Schema::create('training_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedInteger('calories')->nullable();
            $table->string('intensity', 30)->nullable();
            $table->string('image_path')->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->json('todos')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['training_plan_id', 'scheduled_at']);
        });

        Schema::create('training_plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('permission', 30)->default('read');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['training_plan_id', 'user_id']);
            $table->unique(['training_plan_id', 'team_id']);
            $table->index(['user_id', 'permission']);
            $table->index(['team_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plan_assignments');
        Schema::dropIfExists('training_plan_items');
        Schema::dropIfExists('training_plans');
    }
};
