<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_contribution_rules', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')->default(100)->after('factor_value');
            $table->string('tax_account', 40)->nullable()->after('priority');
            $table->string('accounting_account', 40)->nullable()->after('tax_account');
            $table->json('snapshot')->nullable()->after('accounting_account');

            $table->index(['club_id', 'factor_key', 'priority'], 'club_contribution_rules_component_priority_index');
        });
    }

    public function down(): void
    {
        Schema::table('club_contribution_rules', function (Blueprint $table) {
            $table->dropIndex('club_contribution_rules_component_priority_index');
            $table->dropColumn(['priority', 'tax_account', 'accounting_account', 'snapshot']);
        });
    }
};
