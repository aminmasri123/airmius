<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_sepa_settlements', function (Blueprint $table) {
            $table->foreignId('fee_finance_entry_id')->nullable()->unique()->constrained('club_finance_entries')->restrictOnDelete();
            $table->boolean('fee_created_entry')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('club_sepa_settlements', function (Blueprint $table) {
            $table->dropUnique(['fee_finance_entry_id']);
            $table->dropConstrainedForeignId('fee_finance_entry_id');
            $table->dropColumn('fee_created_entry');
        });
    }
};
