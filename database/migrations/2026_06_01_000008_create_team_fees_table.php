<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->default('payment');
            $table->decimal('amount', 12, 2);
            $table->enum('currency', ['EUR', 'USD'])->default('EUR');
            $table->enum('status', ['open', 'paid', 'cancelled'])->default('open');
            $table->text('note')->nullable();
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id']);
            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_fees');
    }
};

