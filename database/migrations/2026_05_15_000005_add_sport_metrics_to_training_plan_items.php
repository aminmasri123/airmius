<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plan_items', function (Blueprint $table) {
            $table->string('sport_type', 80)->nullable()->after('title');
            $table->json('metrics')->nullable()->after('todos');
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_items', function (Blueprint $table) {
            $table->dropColumn(['sport_type', 'metrics']);
        });
    }
};
