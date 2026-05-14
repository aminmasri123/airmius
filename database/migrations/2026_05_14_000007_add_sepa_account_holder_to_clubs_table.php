<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'sepa_account_holder')) {
                $table->string('sepa_account_holder', 120)->nullable()->after('sepa_creditor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (Schema::hasColumn('clubs', 'sepa_account_holder')) {
                $table->dropColumn('sepa_account_holder');
            }
        });
    }
};
