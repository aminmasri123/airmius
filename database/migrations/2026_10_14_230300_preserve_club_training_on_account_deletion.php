<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['training_plans', 'training_sessions', 'training_session_versions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['training_plans', 'training_sessions', 'training_session_versions'] as $name) {
            if (DB::table($name)->whereNull('created_by')->exists()) {
                throw new RuntimeException('Cannot restore required training authors after account deletion.');
            }
        }

        foreach (['training_plans', 'training_sessions', 'training_session_versions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable(false)->change();
            });
        }
    }
};
