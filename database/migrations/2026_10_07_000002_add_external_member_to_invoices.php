<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'club_external_member_id')) {
                $table->foreignId('club_external_member_id')
                    ->nullable()
                    ->after('membership_user_id')
                    ->constrained('club_external_members')
                    ->nullOnDelete();
            }

            $table->index(
                ['club_id', 'club_external_member_id', 'source', 'billing_period_start'],
                'invoices_external_member_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_external_member_period_index');
            $table->dropConstrainedForeignId('club_external_member_id');
        });
    }
};
