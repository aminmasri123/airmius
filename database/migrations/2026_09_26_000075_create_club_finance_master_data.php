<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_business_partners')) {
            Schema::create('club_business_partners', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_business_partners_club_fk')->cascadeOnDelete();
                $table->string('type', 30)->default('organization');
                $table->string('name');
                $table->string('number', 80)->nullable();
                $table->string('tax_number', 120)->nullable();
                $table->string('vat_id', 120)->nullable();
                $table->string('iban', 80)->nullable();
                $table->string('bic', 40)->nullable();
                $table->json('contact')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['club_id', 'number'], 'club_business_partners_number_uq');
                $table->index(['club_id', 'type', 'is_active'], 'club_business_partners_type_idx');
            });
        }

        if (! Schema::hasTable('club_accounting_accounts')) {
            Schema::create('club_accounting_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_accounting_accounts_club_fk')->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('name');
                $table->string('type', 30);
                $table->string('datev_code', 40)->nullable();
                $table->boolean('is_cash_account')->default(false);
                $table->boolean('is_bank_account')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['club_id', 'code'], 'club_accounting_accounts_code_uq');
                $table->index(['club_id', 'type', 'is_active'], 'club_accounting_accounts_type_idx');
            });
        }

        if (! Schema::hasTable('club_cost_centers')) {
            Schema::create('club_cost_centers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_cost_centers_club_fk')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('club_cost_centers', indexName: 'club_cost_centers_parent_fk')->nullOnDelete();
                $table->string('code', 40);
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['club_id', 'code'], 'club_cost_centers_code_uq');
                $table->index(['club_id', 'parent_id'], 'club_cost_centers_parent_idx');
            });
        }

        if (! Schema::hasTable('club_projects')) {
            Schema::create('club_projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_projects_club_fk')->cascadeOnDelete();
                $table->foreignId('club_department_id')->nullable()->constrained('club_departments', indexName: 'club_projects_department_fk')->nullOnDelete();
                $table->string('code', 40);
                $table->string('name');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->string('status', 30)->default('planned');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['club_id', 'code'], 'club_projects_code_uq');
                $table->index(['club_id', 'club_department_id', 'status'], 'club_projects_department_status_idx');
            });
        }

        if (! Schema::hasTable('club_finance_assignments')) {
            Schema::create('club_finance_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_finance_assignments_club_fk')->cascadeOnDelete();
                $table->string('assignable_type', 120);
                $table->unsignedBigInteger('assignable_id');
                $table->foreignId('club_business_partner_id')->nullable()->constrained('club_business_partners', indexName: 'club_fin_assign_partner_fk')->restrictOnDelete();
                $table->foreignId('club_accounting_account_id')->nullable()->constrained('club_accounting_accounts', indexName: 'club_fin_assign_account_fk')->restrictOnDelete();
                $table->foreignId('club_cost_center_id')->nullable()->constrained('club_cost_centers', indexName: 'club_fin_assign_cost_center_fk')->restrictOnDelete();
                $table->foreignId('club_project_id')->nullable()->constrained('club_projects', indexName: 'club_fin_assign_project_fk')->restrictOnDelete();
                $table->foreignId('club_department_id')->nullable()->constrained('club_departments', indexName: 'club_fin_assign_department_fk')->restrictOnDelete();
                $table->foreignId('club_year_period_id')->nullable()->constrained('club_year_periods', indexName: 'club_fin_assign_period_fk')->restrictOnDelete();
                $table->date('valid_from');
                $table->date('valid_until')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();

                $table->index(['club_id', 'assignable_type', 'assignable_id', 'valid_from'], 'club_fin_assign_subject_idx');
                $table->index(['club_id', 'valid_from', 'valid_until'], 'club_fin_assign_validity_idx');
            });
        }

        $entryRelations = [
            ['club_business_partner_id', 'business_year_period_id', 'club_business_partners', 'club_fin_entries_partner_fk'],
            ['club_accounting_account_id', 'club_business_partner_id', 'club_accounting_accounts', 'club_fin_entries_account_fk'],
            ['club_cost_center_id', 'club_accounting_account_id', 'club_cost_centers', 'club_fin_entries_cost_center_fk'],
            ['club_project_id', 'club_cost_center_id', 'club_projects', 'club_fin_entries_project_fk'],
            ['club_department_id', 'club_project_id', 'club_departments', 'club_fin_entries_department_fk'],
        ];

        foreach ($entryRelations as [$column, $after, $tableName, $foreignKey]) {
            if (! Schema::hasColumn('club_finance_entries', $column)) {
                Schema::table('club_finance_entries', function (Blueprint $table) use ($column, $after, $tableName, $foreignKey) {
                    $table->foreignId($column)->nullable()->after($after)->constrained($tableName, indexName: $foreignKey)->restrictOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('club_finance_entries', function (Blueprint $table) {
            $table->dropForeign('club_fin_entries_department_fk');
            $table->dropForeign('club_fin_entries_project_fk');
            $table->dropForeign('club_fin_entries_cost_center_fk');
            $table->dropForeign('club_fin_entries_account_fk');
            $table->dropForeign('club_fin_entries_partner_fk');
            $table->dropColumn([
                'club_department_id',
                'club_project_id',
                'club_cost_center_id',
                'club_accounting_account_id',
                'club_business_partner_id',
            ]);
        });

        Schema::dropIfExists('club_finance_assignments');
        Schema::dropIfExists('club_projects');
        Schema::dropIfExists('club_cost_centers');
        Schema::dropIfExists('club_accounting_accounts');
        Schema::dropIfExists('club_business_partners');
    }
};
