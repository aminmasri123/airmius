<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_money_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('type', 10);
            $table->timestamps();
        });
        Schema::table('club_finance_entries', function (Blueprint $table) {
            $table->foreignId('club_money_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('entry_kind', 20)->default('operating')->index();
            $table->uuid('transfer_key')->nullable()->index();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('club_money_account_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('club_money_account_id'));
        Schema::table('club_finance_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_money_account_id');
            $table->dropColumn(['entry_kind', 'transfer_key']);
        });
        Schema::dropIfExists('club_money_accounts');
    }
};
