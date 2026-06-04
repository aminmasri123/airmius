<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_block_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_block_id')->constrained('training_blocks')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_block_items');
    }
};

