<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->string('visibility')->default('club');
            $table->date('start_at')->nullable();
            $table->date('due_at')->nullable();
            $table->json('participant_ids')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['club_id', 'completed_at']);
            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'due_at']);
        });

        Schema::create('club_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('club_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['club_task_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_task_attachments');
        Schema::dropIfExists('club_task_comments');
        Schema::dropIfExists('club_tasks');
    }
};
