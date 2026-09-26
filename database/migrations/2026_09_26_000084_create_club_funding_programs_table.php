<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_funding_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_year_period_id')->nullable()->constrained('club_year_periods')->restrictOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('program_name');
            $table->string('provider_name');
            $table->string('status', 40)->default('draft');
            $table->date('deadline_on')->nullable();
            $table->date('submitted_on')->nullable();
            $table->date('approved_on')->nullable();
            $table->date('paid_out_on')->nullable();
            $table->unsignedInteger('requested_amount_cents')->default(0);
            $table->unsignedInteger('approved_amount_cents')->default(0);
            $table->unsignedInteger('own_contribution_cents')->default(0);
            $table->unsignedInteger('paid_out_amount_cents')->default(0);
            $table->json('contact_snapshot')->nullable();
            $table->json('application_snapshot')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status', 'deadline_on']);
            $table->index(['club_id', 'club_year_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_funding_programs');
    }
};
