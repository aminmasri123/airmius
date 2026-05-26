<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sports', function (Blueprint $table) {
            $table->json('performance_metrics')->nullable()->after('visibility');
            $table->json('performance_visibility')->nullable()->after('performance_metrics');
            $table->timestamp('training_profile_completed_at')->nullable()->after('performance_visibility');
        });
    }

    public function down(): void
    {
        Schema::table('user_sports', function (Blueprint $table) {
            $table->dropColumn([
                'performance_metrics',
                'performance_visibility',
                'training_profile_completed_at',
            ]);
        });
    }
};
