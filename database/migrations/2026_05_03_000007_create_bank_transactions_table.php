<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transaction_hash', 64);
            $table->date('booking_date')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('debtor_name')->nullable();
            $table->string('debtor_iban', 40)->nullable();
            $table->text('purpose')->nullable();
            $table->string('status', 30)->default('unmatched');
            $table->unsignedTinyInteger('match_confidence')->default(0);
            $table->string('match_reason')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'transaction_hash']);
            $table->index(['club_id', 'status']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
