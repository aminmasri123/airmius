<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_sepa_fee_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->constrained('club_sepa_settlements')->restrictOnDelete();
            $table->foreignId('finance_entry_id')->unique()->constrained('club_finance_entries')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->unsignedInteger('revision');
            $table->unsignedInteger('previous_amount_cents');
            $table->unsignedInteger('amount_cents');
            $table->date('booked_on');
            $table->string('reference', 180);
            $table->text('reason');
            $table->timestamps();
            $table->unique(['settlement_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_sepa_fee_corrections');
    }
};
