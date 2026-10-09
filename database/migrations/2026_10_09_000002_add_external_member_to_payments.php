<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            if (! Schema::hasColumn('payments', 'club_external_member_id')) {
                $table->foreignId('club_external_member_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('club_external_members')
                    ->nullOnDelete();
            }
        });

        Schema::table('payment_booking_receipts', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_booking_receipts', 'club_external_member_id')) {
                $table->foreignId('club_external_member_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('club_external_members')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        DB::table('payments')->whereNull('user_id')->delete();

        Schema::table('payment_booking_receipts', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_booking_receipts', 'club_external_member_id')) {
                $table->dropConstrainedForeignId('club_external_member_id');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'club_external_member_id')) {
                $table->dropConstrainedForeignId('club_external_member_id');
            }
            $table->dropForeign(['user_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
