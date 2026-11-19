<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_enrollments', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('left_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learning_enrollments', fn (Blueprint $table) => $table->dropColumn(['hidden_at', 'left_at']));
    }
};
