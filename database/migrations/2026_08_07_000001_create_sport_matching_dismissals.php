<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sport_matching_dismissals')) {
            return;
        }

        Schema::create('sport_matching_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_matching_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['sport_matching_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_matching_dismissals');
    }
};
