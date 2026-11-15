<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plan_items', function (Blueprint $table) {
            $table->string('original_locale', 10)->nullable();
            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();
            $table->json('translations')->nullable();
            $table->json('explanation_images')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_items', fn (Blueprint $table) => $table->dropColumn([
            'original_locale', 'instructions', 'notes', 'translations', 'explanation_images',
        ]));
    }
};
