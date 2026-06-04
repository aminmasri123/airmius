<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question');
            $table->text('description')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
        });

        Schema::create('event_decision_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_decision_id')->constrained('event_decisions')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_decision_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_decision_id')->constrained('event_decisions')->cascadeOnDelete();
            $table->foreignId('event_decision_option_id')->constrained('event_decision_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_decision_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_decision_votes');
        Schema::dropIfExists('event_decision_options');
        Schema::dropIfExists('event_decisions');
    }
};

