<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['payment_id', 'released_at']);
            $table->index(['invoice_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_payment_allocations');
    }
};
