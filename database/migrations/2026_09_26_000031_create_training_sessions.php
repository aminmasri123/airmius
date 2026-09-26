<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('goals')->nullable();
            $table->json('phases')->nullable();
            $table->json('exercises')->nullable();
            $table->json('materials')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index(['club_id', 'status']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('training_session_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('change_note', 500)->nullable();
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['training_session_id', 'revision']);
            $table->index(['training_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_versions');
        Schema::dropIfExists('training_sessions');
    }
};
