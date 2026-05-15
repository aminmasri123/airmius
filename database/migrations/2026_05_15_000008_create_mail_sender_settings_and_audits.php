<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_sender_settings', function (Blueprint $table) {
            $table->id();
            $table->string('category')->unique();
            $table->string('mailer');
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('scheme')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('password_updated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mail_sender_audits', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index();
            $table->string('action')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_sender_audits');
        Schema::dropIfExists('mail_sender_settings');
    }
};
