<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_exercises', function (Blueprint $table) {
            $table->string('original_locale', 10)->nullable();
            $table->text('notes')->nullable();
            $table->json('todos')->nullable();
            $table->json('translations')->nullable();
            $table->json('explanation_images')->nullable();
            $table->string('video_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('training_exercises', fn (Blueprint $table) => $table->dropColumn([
            'original_locale', 'notes', 'todos', 'translations', 'explanation_images', 'video_url',
        ]));
    }
};
