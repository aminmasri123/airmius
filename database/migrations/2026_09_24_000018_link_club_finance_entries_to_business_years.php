<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_finance_entries', function (Blueprint $table) {
            $table->foreignId('business_year_period_id')
                ->nullable()
                ->after('club_id')
                ->constrained('club_year_periods')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('club_finance_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('business_year_period_id'));
    }
};
