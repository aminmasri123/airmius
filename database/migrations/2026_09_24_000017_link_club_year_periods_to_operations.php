<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('business_year_period_id')->nullable()->after('club_id')->constrained('club_year_periods')->restrictOnDelete();
            $table->foreignId('contribution_year_period_id')->nullable()->after('business_year_period_id')->constrained('club_year_periods')->restrictOnDelete();
        });
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('business_year_period_id')->nullable()->after('club_id')->constrained('club_year_periods')->restrictOnDelete();
        });
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('sport_year_period_id')->nullable()->after('club_id')->constrained('club_year_periods')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropConstrainedForeignId('sport_year_period_id'));
        Schema::table('bank_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('business_year_period_id'));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribution_year_period_id');
            $table->dropConstrainedForeignId('business_year_period_id');
        });
    }
};
