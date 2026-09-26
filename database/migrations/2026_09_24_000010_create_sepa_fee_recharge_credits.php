<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_sepa_fee_recharge_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('recharge_id')->constrained('club_sepa_fee_recharges')->restrictOnDelete();
            $table->foreignId('active_recharge_id')->nullable()->unique()->constrained('club_sepa_fee_recharges')->restrictOnDelete();
            $table->uuid('request_id')->unique();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->text('review_reason')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('refund_due_cents')->nullable();
            $table->string('credit_note_number')->nullable()->unique();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('refund_request_id')->nullable()->unique();
            $table->foreignId('refund_finance_entry_id')->nullable()->unique()->constrained('club_finance_entries')->restrictOnDelete();
            $table->foreignId('refund_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('refund_booked_on')->nullable();
            $table->string('refund_reference', 180)->nullable();
            $table->boolean('refund_created_entry')->default(false);
            $table->timestamp('refund_recorded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_sepa_fee_recharge_credits');
    }
};
