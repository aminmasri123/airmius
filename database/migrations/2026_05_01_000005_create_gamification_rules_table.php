<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('actor_type')->default('sportler');
            $table->string('category')->default('activity');
            $table->string('label');
            $table->text('description')->nullable();
            $table->integer('xp_amount')->default(0);
            $table->unsignedSmallInteger('daily_limit')->nullable();
            $table->smallInteger('trust_delta')->default(0);
            $table->boolean('is_penalty')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['key', 'actor_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_rules');
    }
};
