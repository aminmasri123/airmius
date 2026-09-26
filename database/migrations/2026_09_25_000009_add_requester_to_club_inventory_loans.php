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
            $table->foreignId('requested_by')
                ->nullable()
                ->after('borrower_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['club_id', 'requested_by', 'status'], 'club_inventory_loans_requester_index');
        });

        DB::table('club_inventory_loans')->whereNull('requested_by')->update([
            'requested_by' => DB::raw('borrower_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->dropIndex('club_inventory_loans_requester_index');
            $table->dropConstrainedForeignId('requested_by');
        });
    }
};
