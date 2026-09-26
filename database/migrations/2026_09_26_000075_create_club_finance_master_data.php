<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_business_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
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

            $table->unique(['club_id', 'number']);
            $table->index(['club_id', 'type', 'is_active']);
        });

        Schema::create('club_accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('type', 30);
            $table->string('datev_code', 40)->nullable();
            $table->boolean('is_cash_account')->default(false);
            $table->boolean('is_bank_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['club_id', 'code']);
            $table->index(['club_id', 'type', 'is_active']);
        });

        Schema::create('club_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('club_cost_centers')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['club_id', 'code']);
            $table->index(['club_id', 'parent_id']);
        });

        Schema::create('club_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_department_id')->nullable()->constrained('club_departments')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 30)->default('planned');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'code']);
            $table->index(['club_id', 'club_department_id', 'status']);
        });

        Schema::create('club_finance_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('assignable_type', 120);
            $table->unsignedBigInteger('assignable_id');
            $table->foreignId('club_business_partner_id')->nullable()->constrained('club_business_partners')->restrictOnDelete();
            $table->foreignId('club_accounting_account_id')->nullable()->constrained('club_accounting_accounts')->restrictOnDelete();
            $table->foreignId('club_cost_center_id')->nullable()->constrained('club_cost_centers')->restrictOnDelete();
            $table->foreignId('club_project_id')->nullable()->constrained('club_projects')->restrictOnDelete();
            $table->foreignId('club_department_id')->nullable()->constrained('club_departments')->restrictOnDelete();
            $table->foreignId('club_year_period_id')->nullable()->constrained('club_year_periods')->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'assignable_type', 'assignable_id', 'valid_from']);
            $table->index(['club_id', 'valid_from', 'valid_until']);
        });

        Schema::table('club_finance_entries', function (Blueprint $table) {
            $table->foreignId('club_business_partner_id')->nullable()->after('business_year_period_id')->constrained('club_business_partners')->restrictOnDelete();
            $table->foreignId('club_accounting_account_id')->nullable()->after('club_business_partner_id')->constrained('club_accounting_accounts')->restrictOnDelete();
            $table->foreignId('club_cost_center_id')->nullable()->after('club_accounting_account_id')->constrained('club_cost_centers')->restrictOnDelete();
            $table->foreignId('club_project_id')->nullable()->after('club_cost_center_id')->constrained('club_projects')->restrictOnDelete();
            $table->foreignId('club_department_id')->nullable()->after('club_project_id')->constrained('club_departments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('club_finance_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_department_id');
            $table->dropConstrainedForeignId('club_project_id');
            $table->dropConstrainedForeignId('club_cost_center_id');
            $table->dropConstrainedForeignId('club_accounting_account_id');
            $table->dropConstrainedForeignId('club_business_partner_id');
        });

        Schema::dropIfExists('club_finance_assignments');
        Schema::dropIfExists('club_projects');
        Schema::dropIfExists('club_cost_centers');
        Schema::dropIfExists('club_accounting_accounts');
        Schema::dropIfExists('club_business_partners');
    }
};
