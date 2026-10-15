<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_procurement_requests', function (Blueprint $table): void {
            $table->foreignId('requested_by')->nullable()->change();
        });
        Schema::table('club_procurement_receipts', function (Blueprint $table): void {
            $table->foreignId('received_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('club_procurement_requests')->whereNull('requested_by')->exists()
            || DB::table('club_procurement_receipts')->whereNull('received_by')->exists()) {
            throw new RuntimeException('Cannot restore required procurement authors after account deletion.');
        }

        Schema::table('club_procurement_requests', function (Blueprint $table): void {
            $table->foreignId('requested_by')->nullable(false)->change();
        });
        Schema::table('club_procurement_receipts', function (Blueprint $table): void {
            $table->foreignId('received_by')->nullable(false)->change();
        });
    }
};
