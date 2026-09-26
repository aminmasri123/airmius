<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            $table->foreignId('contribution_payer_user_id')
                ->nullable()
                ->after('family_group_key')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('club_external_members', function (Blueprint $table) {
            $table->foreignId('contribution_payer_user_id')
                ->nullable()
                ->after('family_group_key')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('membership_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(
                ['club_id', 'membership_user_id', 'source', 'billing_period_start'],
                'invoices_membership_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_membership_period_index');
            $table->dropConstrainedForeignId('membership_user_id');
        });

        Schema::table('club_external_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribution_payer_user_id');
        });

        Schema::table('club_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribution_payer_user_id');
        });
    }
};
