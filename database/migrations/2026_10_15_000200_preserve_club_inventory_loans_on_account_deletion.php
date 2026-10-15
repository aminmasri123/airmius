<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->string('borrower_name_snapshot')->nullable()->after('borrower_id');
            $table->dropForeign(['borrower_id']);
            $table->foreign('borrower_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('club_inventory_loans')->whereNotNull('borrower_name_snapshot')->exists()) {
            throw new RuntimeException('Cannot discard retained borrower names while club loans still need review.');
        }

        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->dropForeign(['borrower_id']);
            $table->foreign('borrower_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn('borrower_name_snapshot');
        });
    }
};
