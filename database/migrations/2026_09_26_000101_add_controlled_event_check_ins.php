<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_check_in_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('device_hash', 64)->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'user_id', 'expires_at']);
            $table->index(['event_id', 'device_hash']);
        });

        Schema::create('event_attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 30);
            $table->json('before_state')->nullable();
            $table->json('after_state');
            $table->json('changed_fields');
            $table->string('reason_code', 60)->nullable();
            $table->boolean('contains_private_note')->default(false);
            $table->timestamps();

            $table->index(['event_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendance_corrections');
        Schema::dropIfExists('event_check_in_tokens');
    }
};
