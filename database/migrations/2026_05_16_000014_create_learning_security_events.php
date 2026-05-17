<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('learning_security_events')) {
            return;
        }

        Schema::create('learning_security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('learning_lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('severity')->default('warning');
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['learning_course_id', 'created_at'], 'learning_security_course_created_index');
            $table->index(['type', 'created_at'], 'learning_security_type_created_index');
            $table->index(['severity', 'created_at'], 'learning_security_severity_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_security_events');
    }
};
