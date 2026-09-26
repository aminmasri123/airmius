<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_finance_entries', function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->after('receipt_file_id')
                ->constrained('club_finance_entries')
                ->restrictOnDelete();
            $table->json('correction_snapshot')->nullable()->after('description');

            $table->index(['club_id', 'reversal_of_id']);
        });
    }

    public function down(): void
    {
        Schema::table('club_finance_entries', function (Blueprint $table): void {
            $table->dropIndex(['club_id', 'reversal_of_id']);
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropColumn('correction_snapshot');
        });
    }
};
