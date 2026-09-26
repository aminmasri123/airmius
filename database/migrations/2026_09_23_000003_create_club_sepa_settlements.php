<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_sepa_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('club_sepa_batch_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->boolean('created_payment')->default(false);
            $table->string('status', 20);
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('settled_on')->nullable();
            $table->string('settlement_reference', 180)->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('returned_on')->nullable();
            $table->string('return_reference', 180)->nullable();
            $table->text('return_reason')->nullable();
            $table->unsignedInteger('return_fee_cents')->default(0);
            $table->foreignId('retry_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retry_authorized_at')->nullable();
            $table->text('retry_reason')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'settlement_reference'], 'sepa_settlement_reference_unique');
            $table->unique(['club_id', 'return_reference'], 'sepa_return_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_sepa_settlements');
    }
};
