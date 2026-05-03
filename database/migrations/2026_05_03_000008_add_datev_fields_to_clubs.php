<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'datev_consultant_number')) {
                $table->string('datev_consultant_number', 20)->nullable()->after('sepa_bic');
            }

            if (! Schema::hasColumn('clubs', 'datev_client_number')) {
                $table->string('datev_client_number', 20)->nullable()->after('datev_consultant_number');
            }

            if (! Schema::hasColumn('clubs', 'datev_revenue_account')) {
                $table->string('datev_revenue_account', 20)->nullable()->after('datev_client_number');
            }

            if (! Schema::hasColumn('clubs', 'datev_bank_account')) {
                $table->string('datev_bank_account', 20)->nullable()->after('datev_revenue_account');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            foreach (['datev_bank_account', 'datev_revenue_account', 'datev_client_number', 'datev_consultant_number'] as $column) {
                if (Schema::hasColumn('clubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
