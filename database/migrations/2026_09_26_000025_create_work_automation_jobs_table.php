<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_automation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('retried_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 40);
            $table->string('status', 30)->default('queued');
            $table->string('idempotency_key', 160);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('recipient_roles')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('retry_queued_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->string('error_message', 255)->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'idempotency_key']);
            $table->index(['club_id', 'kind', 'status']);
            $table->index(['club_id', 'status', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_automation_jobs');
    }
};
