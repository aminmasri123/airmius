<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            if (! Schema::hasColumn('club_user', 'contribution_next_invoice_on')) {
                $table->date('contribution_next_invoice_on')->nullable()->after('contribution_interval');
            }

            if (! Schema::hasColumn('club_user', 'contribution_last_invoice_at')) {
                $table->timestamp('contribution_last_invoice_at')->nullable()->after('contribution_next_invoice_on');
            }
        });

        Schema::table('club_external_members', function (Blueprint $table) {
            if (! Schema::hasColumn('club_external_members', 'contribution_next_invoice_on')) {
                $table->date('contribution_next_invoice_on')->nullable()->after('contribution_interval');
            }

            if (! Schema::hasColumn('club_external_members', 'contribution_last_invoice_at')) {
                $table->timestamp('contribution_last_invoice_at')->nullable()->after('contribution_next_invoice_on');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'source')) {
                $table->string('source', 60)->nullable()->after('status');
            }

            if (! Schema::hasColumn('invoices', 'billing_period_start')) {
                $table->date('billing_period_start')->nullable()->after('source');
            }

            if (! Schema::hasColumn('invoices', 'billing_period_end')) {
                $table->date('billing_period_end')->nullable()->after('billing_period_start');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach (['billing_period_end', 'billing_period_start', 'source'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('club_external_members', function (Blueprint $table) {
            foreach (['contribution_last_invoice_at', 'contribution_next_invoice_on'] as $column) {
                if (Schema::hasColumn('club_external_members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('club_user', function (Blueprint $table) {
            foreach (['contribution_last_invoice_at', 'contribution_next_invoice_on'] as $column) {
                if (Schema::hasColumn('club_user', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
