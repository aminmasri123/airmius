<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_sepa_fee_recharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->constrained('club_sepa_settlements')->restrictOnDelete();
            $table->foreignId('active_settlement_id')->nullable()->unique()->constrained('club_sepa_settlements')->restrictOnDelete();
            $table->foreignId('source_invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('fee_revision');
            $table->unsignedInteger('fee_amount_cents');
            $table->unsignedInteger('amount_cents');
            $table->date('due_date');
            $table->text('basis');
            $table->text('reason');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_sepa_fee_recharges');
    }
};
