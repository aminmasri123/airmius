<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_contact_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('subject', 160)->nullable();
            $table->string('category', 80)->nullable()->index();
            $table->string('priority', 40)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('platform', 40)->nullable();
            $table->text('message');
            $table->timestamp('privacy_consent_at')->nullable();
            $table->string('status', 40)->default('new')->index();
            $table->string('email_delivery_status', 40)->default('pending')->index();
            $table->text('email_delivery_error')->nullable();
            $table->timestamp('retention_expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_contact_requests');
    }
};
