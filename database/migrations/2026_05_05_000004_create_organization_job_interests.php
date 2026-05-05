<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_job_interests')) {
            return;
        }

        Schema::create('organization_job_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['organization_job_id', 'created_at'], 'oji_job_created_idx');
            $table->index(['email', 'organization_job_id'], 'oji_email_job_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_job_interests');
    }
};
