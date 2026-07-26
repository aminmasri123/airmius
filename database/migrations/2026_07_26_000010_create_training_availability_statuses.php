<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_availability_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->string('visibility', 16)->default('private');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_on']);
            $table->index(['user_id', 'status', 'cleared_at']);
            $table->index(['visibility', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_availability_statuses');
    }
};
