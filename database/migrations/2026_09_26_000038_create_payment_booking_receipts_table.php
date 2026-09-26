<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_booking_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('claim_status_before', 60)->nullable();
            $table->string('claim_status_after', 60);
            $table->string('payment_status', 40);
            $table->string('payment_method', 40)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('EUR');
            $table->string('receipt_number')->nullable();
            $table->string('reference')->nullable();
            $table->json('payload')->nullable();
            $table->string('previous_hash', 64)->nullable();
            $table->string('hash', 64)->unique();
            $table->timestamp('booked_at');
            $table->timestamps();

            $table->index(['club_id', 'invoice_id']);
            $table->index(['payment_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_booking_receipts');
    }
};
