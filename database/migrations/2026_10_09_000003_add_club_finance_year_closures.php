<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_year_periods', function (Blueprint $table) {
            $table->timestamp('finance_closed_at')->nullable();
            $table->foreignId('finance_closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finance_next_period_id')->nullable()->constrained('club_year_periods')->restrictOnDelete();
            $table->json('finance_closing_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('club_year_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finance_closed_by');
            $table->dropConstrainedForeignId('finance_next_period_id');
            $table->dropColumn(['finance_closed_at', 'finance_closing_snapshot']);
        });
    }
};
