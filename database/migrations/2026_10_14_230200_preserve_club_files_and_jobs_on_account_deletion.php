<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });
        Schema::table('organization_jobs', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('files')->whereNull('user_id')->exists()
            || DB::table('organization_jobs')->whereNull('created_by')->exists()) {
            throw new RuntimeException('Cannot restore required authors after account deletion.');
        }

        Schema::table('files', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });
        Schema::table('organization_jobs', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }
};
