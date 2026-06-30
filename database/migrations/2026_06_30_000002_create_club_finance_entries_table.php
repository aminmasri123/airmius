<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_finance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('receipt_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('type', 20);
            $table->string('account', 20);
            $table->string('category')->nullable();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('booked_on');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'booked_on']);
            $table->index(['club_id', 'type']);
            $table->index(['club_id', 'account']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_finance_entries');
    }
};
