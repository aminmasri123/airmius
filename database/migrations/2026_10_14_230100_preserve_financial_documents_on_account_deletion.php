<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->json('recipient_snapshot')->nullable();
            $table->dropForeign(['user_id']);
        });
        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->json('payer_snapshot')->nullable();
            $table->dropForeign(['user_id']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('subscription_invoices')->whereNull('user_id')->exists()
            || DB::table('payments')->whereNotNull('payer_snapshot')->exists()) {
            throw new RuntimeException('Cannot roll back retained billing documents after account deletion.');
        }

        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn('recipient_snapshot');
        });
        Schema::table('subscription_invoices', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn('payer_snapshot');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
