<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->enum('type', ['training','match','meeting','public']);
            $table->enum('visibility', ['private','organization','public'])->default('private');
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->string('recurring')->nullable();
            $table->timestamp('recurrence_ends_at')->nullable();
            $table->timestamp('reminder_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
