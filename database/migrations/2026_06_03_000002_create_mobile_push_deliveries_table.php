<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mobile_device_token_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 80);
            $table->string('provider', 32);
            $table->string('status', 32)->default('queued');
            $table->json('payload')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_push_deliveries');
    }
};
