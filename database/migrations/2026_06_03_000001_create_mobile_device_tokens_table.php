<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 128);
            $table->string('platform', 32)->default('unknown');
            $table->string('provider', 32)->default('fcm');
            $table->text('token');
            $table->string('token_hash', 64)->index();
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('build_number', 40)->nullable();
            $table->string('locale', 8)->default('de');
            $table->string('timezone', 80)->nullable();
            $table->json('channels')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_device_tokens');
    }
};
