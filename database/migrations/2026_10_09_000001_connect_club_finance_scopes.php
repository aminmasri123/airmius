<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['club_finance_entries', 'payments', 'invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('club_budget_id')->nullable()->constrained()->restrictOnDelete();
                if ($name !== 'club_finance_entries') {
                    $table->foreignId('club_department_id')->nullable()->constrained()->restrictOnDelete();
                    $table->foreignId('club_project_id')->nullable()->constrained()->restrictOnDelete();
                    $table->foreignId('club_cost_center_id')->nullable()->constrained()->restrictOnDelete();
                }
            });
        }
        Schema::table('club_budgets', function (Blueprint $table) {
            $table->foreignId('club_project_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('team_fees', function (Blueprint $table) {
            $table->foreignId('club_finance_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('team_fees', fn (Blueprint $table) => $table->dropConstrainedForeignId('club_finance_entry_id'));
        Schema::table('club_budgets', fn (Blueprint $table) => $table->dropConstrainedForeignId('club_project_id'));
        foreach (['club_finance_entries', 'payments', 'invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropConstrainedForeignId('team_id');
                $table->dropConstrainedForeignId('club_budget_id');
                if ($name !== 'club_finance_entries') {
                    $table->dropConstrainedForeignId('club_department_id');
                    $table->dropConstrainedForeignId('club_project_id');
                    $table->dropConstrainedForeignId('club_cost_center_id');
                }
            });
        }
    }
};
