<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('club_budgets')->cascadeOnDelete();
            $table->foreignId('club_year_period_id')->constrained('club_year_periods')->restrictOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_type', 40);
            $table->foreignId('club_department_id')->nullable()->constrained('club_departments')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project_name', 160)->nullable();
            $table->string('name', 160);
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('approval_status', 40)->default('draft');
            $table->unsignedInteger('planned_income_cents')->default(0);
            $table->unsignedInteger('planned_expense_cents')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'club_year_period_id', 'scope_type', 'name', 'version'], 'club_budgets_scope_version_index');
            $table->index(['club_id', 'club_year_period_id', 'scope_type']);
            $table->index(['club_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_budgets');
    }
};
