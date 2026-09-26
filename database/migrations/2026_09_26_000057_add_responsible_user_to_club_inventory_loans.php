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
            $table->foreignId('responsible_user_id')
                ->nullable()
                ->after('borrower_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['club_id', 'responsible_user_id', 'status'], 'club_inventory_loans_responsible_index');
        });

        DB::table('club_inventory_loans')
            ->whereIn('status', ['pending', 'active', 'responsibility_transferred'])
            ->whereNull('responsible_user_id')
            ->update(['responsible_user_id' => DB::raw('borrower_id')]);
    }

    public function down(): void
    {
        Schema::table('club_inventory_loans', function (Blueprint $table): void {
            $table->dropIndex('club_inventory_loans_responsible_index');
            $table->dropConstrainedForeignId('responsible_user_id');
        });
    }
};
