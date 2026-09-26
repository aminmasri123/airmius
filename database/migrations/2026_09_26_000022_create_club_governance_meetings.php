<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_governance_meetings')) {
            Schema::create('club_governance_meetings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id');
                $table->foreignId('club_governance_body_id')->nullable();
                $table->foreignId('club_year_period_id')->nullable();
                $table->string('type', 40);
                $table->string('title', 180);
                $table->string('status', 30)->default('draft');
                $table->dateTime('scheduled_at')->nullable();
                $table->string('location_name', 180)->nullable();
                $table->string('participant_scope', 40);
                $table->date('motions_due_on')->nullable();
                $table->dateTime('invitation_sent_at')->nullable();
                $table->json('agenda_items')->nullable();
                $table->json('materials')->nullable();
                $table->json('decision_templates')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();

                $table->foreign('club_id', 'club_gov_meetings_club_fk')->references('id')->on('clubs')->cascadeOnDelete();
                $table->foreign('club_governance_body_id', 'club_gov_meetings_body_fk')->references('id')->on('club_governance_bodies')->nullOnDelete();
                $table->foreign('club_year_period_id', 'club_gov_meetings_period_fk')->references('id')->on('club_year_periods')->nullOnDelete();
                $table->foreign('created_by', 'club_gov_meetings_created_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['club_id', 'type', 'status', 'scheduled_at'], 'club_gov_meetings_status_schedule_idx');
                $table->index(['club_id', 'club_governance_body_id'], 'club_gov_meetings_body_idx');
                $table->index(['club_id', 'club_year_period_id'], 'club_gov_meetings_period_idx');
            });
        }

        if (! Schema::hasTable('club_governance_meeting_recipients')) {
            Schema::create('club_governance_meeting_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id');
                $table->foreignId('club_governance_meeting_id');
                $table->foreignId('user_id')->nullable();
                $table->foreignId('club_external_member_id')->nullable();
                $table->boolean('attendance_eligible')->default(true);
                $table->boolean('voting_eligible')->default(false);
                $table->string('delivery_status', 30)->default('pending');
                $table->dateTime('delivered_at')->nullable();
                $table->dateTime('responded_at')->nullable();
                $table->string('response_status', 30)->nullable();
                $table->timestamps();

                $table->foreign('club_id', 'club_gov_recipients_club_fk')->references('id')->on('clubs')->cascadeOnDelete();
                $table->foreign('club_governance_meeting_id', 'club_gov_recipients_meeting_fk')->references('id')->on('club_governance_meetings')->cascadeOnDelete();
                $table->foreign('user_id', 'club_gov_recipients_user_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('club_external_member_id', 'club_gov_recipients_external_fk')->references('id')->on('club_external_members')->cascadeOnDelete();
                $table->unique(['club_governance_meeting_id', 'user_id'], 'club_gov_recipients_meeting_user_unique');
                $table->unique(['club_governance_meeting_id', 'club_external_member_id'], 'club_gov_recipients_meeting_external_unique');
                $table->index(['club_id', 'attendance_eligible', 'voting_eligible'], 'club_gov_recipients_eligibility_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_governance_meeting_recipients');
        Schema::dropIfExists('club_governance_meetings');
    }
};
