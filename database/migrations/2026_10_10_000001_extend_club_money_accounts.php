<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_money_accounts', function (Blueprint $table) {
            $table->string('bank_name', 160)->nullable();
            $table->string('account_holder', 160)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('bic', 11)->nullable();
            $table->boolean('is_active')->default(true);
        });
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('club_money_account_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('club_money_account_id'));
        Schema::table('club_money_accounts', fn (Blueprint $table) => $table->dropColumn(['bank_name', 'account_holder', 'iban', 'bic', 'is_active']));
    }
};
