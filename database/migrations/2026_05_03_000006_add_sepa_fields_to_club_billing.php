<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'sepa_creditor_id')) {
                $table->string('sepa_creditor_id')->nullable()->after('official_club_number');
            }

            if (! Schema::hasColumn('clubs', 'sepa_iban')) {
                $table->string('sepa_iban', 40)->nullable()->after('sepa_creditor_id');
            }

            if (! Schema::hasColumn('clubs', 'sepa_bic')) {
                $table->string('sepa_bic', 20)->nullable()->after('sepa_iban');
            }
        });

        Schema::table('club_user', function (Blueprint $table) {
            if (! Schema::hasColumn('club_user', 'sepa_iban')) {
                $table->string('sepa_iban', 40)->nullable()->after('contribution_last_invoice_at');
            }

            if (! Schema::hasColumn('club_user', 'sepa_bic')) {
                $table->string('sepa_bic', 20)->nullable()->after('sepa_iban');
            }

            if (! Schema::hasColumn('club_user', 'sepa_mandate_reference')) {
                $table->string('sepa_mandate_reference')->nullable()->after('sepa_bic');
            }

            if (! Schema::hasColumn('club_user', 'sepa_mandate_signed_on')) {
                $table->date('sepa_mandate_signed_on')->nullable()->after('sepa_mandate_reference');
            }

            if (! Schema::hasColumn('club_user', 'sepa_mandate_active')) {
                $table->boolean('sepa_mandate_active')->default(false)->after('sepa_mandate_signed_on');
            }
        });

        Schema::table('club_external_members', function (Blueprint $table) {
            if (! Schema::hasColumn('club_external_members', 'sepa_iban')) {
                $table->string('sepa_iban', 40)->nullable()->after('contribution_last_invoice_at');
            }

            if (! Schema::hasColumn('club_external_members', 'sepa_bic')) {
                $table->string('sepa_bic', 20)->nullable()->after('sepa_iban');
            }

            if (! Schema::hasColumn('club_external_members', 'sepa_mandate_reference')) {
                $table->string('sepa_mandate_reference')->nullable()->after('sepa_bic');
            }

            if (! Schema::hasColumn('club_external_members', 'sepa_mandate_signed_on')) {
                $table->date('sepa_mandate_signed_on')->nullable()->after('sepa_mandate_reference');
            }

            if (! Schema::hasColumn('club_external_members', 'sepa_mandate_active')) {
                $table->boolean('sepa_mandate_active')->default(false)->after('sepa_mandate_signed_on');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'sepa_exported_at')) {
                $table->timestamp('sepa_exported_at')->nullable()->after('due_soon_notified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'sepa_exported_at')) {
                $table->dropColumn('sepa_exported_at');
            }
        });

        Schema::table('club_external_members', function (Blueprint $table) {
            foreach (['sepa_mandate_active', 'sepa_mandate_signed_on', 'sepa_mandate_reference', 'sepa_bic', 'sepa_iban'] as $column) {
                if (Schema::hasColumn('club_external_members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('club_user', function (Blueprint $table) {
            foreach (['sepa_mandate_active', 'sepa_mandate_signed_on', 'sepa_mandate_reference', 'sepa_bic', 'sepa_iban'] as $column) {
                if (Schema::hasColumn('club_user', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            foreach (['sepa_bic', 'sepa_iban', 'sepa_creditor_id'] as $column) {
                if (Schema::hasColumn('clubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
