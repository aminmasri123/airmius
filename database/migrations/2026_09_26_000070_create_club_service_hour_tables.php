<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_service_hour_requirements')) {
            Schema::create('club_service_hour_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'club_service_req_club_fk')->cascadeOnDelete();
            $table->foreignId('club_year_period_id')->constrained(indexName: 'club_service_req_period_fk')->restrictOnDelete();
            $table->foreignId('club_role_definition_id')->constrained(indexName: 'club_service_req_role_fk')->cascadeOnDelete();
            $table->unsignedInteger('required_minutes');
            $table->unsignedInteger('replacement_rate_cents')->nullable();
            $table->string('replacement_currency', 3)->default('EUR');
            $table->boolean('locked')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'club_service_req_creator_fk')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users', indexName: 'club_service_req_locker_fk')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['club_year_period_id', 'club_role_definition_id'], 'club_service_hour_requirement_period_role_unique');
            });
        }

        if (! Schema::hasTable('club_service_hour_records')) {
            Schema::create('club_service_hour_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'club_service_records_club_fk')->cascadeOnDelete();
            $table->foreignId('club_year_period_id')->constrained(indexName: 'club_service_records_period_fk')->restrictOnDelete();
            $table->foreignId('user_id')->constrained(indexName: 'club_service_records_user_fk')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users', indexName: 'club_service_records_recorder_fk')->cascadeOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users', indexName: 'club_service_records_confirmer_fk')->nullOnDelete();
            $table->foreignId('replacement_for_user_id')->nullable()->constrained('users', indexName: 'club_service_records_replacement_fk')->nullOnDelete();
            $table->string('kind', 30)->default('service');
            $table->string('status', 30)->default('pending');
            $table->date('served_on');
            $table->unsignedInteger('minutes');
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->json('confirmation_snapshot')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'club_year_period_id', 'user_id'], 'club_service_hour_records_member_index');
            });
        }

        if (! Schema::hasTable('club_service_hour_corrections')) {
            Schema::create('club_service_hour_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_service_hour_record_id')->constrained(indexName: 'club_service_corr_record_fk')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained(indexName: 'club_service_corr_club_fk')->cascadeOnDelete();
            $table->foreignId('club_year_period_id')->constrained(indexName: 'club_service_corr_period_fk')->restrictOnDelete();
            $table->foreignId('user_id')->constrained(indexName: 'club_service_corr_user_fk')->cascadeOnDelete();
            $table->foreignId('corrected_by')->constrained('users', indexName: 'club_service_corr_corrector_fk')->cascadeOnDelete();
            $table->integer('minutes_delta');
            $table->unsignedInteger('previous_total_minutes');
            $table->unsignedInteger('corrected_total_minutes');
            $table->string('reason', 240);
            $table->timestamps();

            $table->index(['club_id', 'club_year_period_id', 'user_id'], 'club_service_hour_corrections_member_index');
            });
        }

        if (! Schema::hasTable('club_service_hour_exemptions')) {
            Schema::create('club_service_hour_exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained(indexName: 'club_service_exempt_club_fk')->cascadeOnDelete();
            $table->foreignId('club_year_period_id')->constrained(indexName: 'club_service_exempt_period_fk')->restrictOnDelete();
            $table->foreignId('user_id')->constrained(indexName: 'club_service_exempt_user_fk')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users', indexName: 'club_service_exempt_approver_fk')->cascadeOnDelete();
            $table->unsignedInteger('minutes');
            $table->string('reason', 240);
            $table->timestamps();

            $table->index(['club_id', 'club_year_period_id', 'user_id'], 'club_service_hour_exemptions_member_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_service_hour_exemptions');
        Schema::dropIfExists('club_service_hour_corrections');
        Schema::dropIfExists('club_service_hour_records');
        Schema::dropIfExists('club_service_hour_requirements');
    }
};
