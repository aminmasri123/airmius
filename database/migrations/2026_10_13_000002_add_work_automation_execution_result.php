<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_automation_jobs', function (Blueprint $table) {
            $table->uuid('execution_token')->nullable();
            $table->json('result')->nullable();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('dispatched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_automation_jobs', function (Blueprint $table) {
            $table->dropIndex(['scheduled_at']);
            $table->dropColumn(['execution_token', 'result', 'scheduled_at', 'dispatched_at']);
        });
    }
};
