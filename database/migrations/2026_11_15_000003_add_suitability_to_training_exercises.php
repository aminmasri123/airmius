<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_exercises', function (Blueprint $table) {
            // NULL preserves the legacy sport_type; [] explicitly means all sports.
            $table->json('sport_types')->nullable();
            $table->string('category', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('training_exercises', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['sport_types', 'category']);
        });
    }
};
