<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_governance_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_governance_body_id')->nullable()->constrained('club_governance_bodies')->nullOnDelete();
            $table->foreignId('club_year_period_id')->nullable()->constrained('club_year_periods')->nullOnDelete();
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
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['club_id', 'type', 'status', 'scheduled_at']);
            $table->index(['club_id', 'club_governance_body_id']);
            $table->index(['club_id', 'club_year_period_id']);
        });

        Schema::create('club_governance_meeting_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_governance_meeting_id')->constrained('club_governance_meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('club_external_member_id')->nullable()->constrained('club_external_members')->cascadeOnDelete();
            $table->boolean('attendance_eligible')->default(true);
            $table->boolean('voting_eligible')->default(false);
            $table->string('delivery_status', 30)->default('pending');
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->string('response_status', 30)->nullable();
            $table->timestamps();
            $table->unique(['club_governance_meeting_id', 'user_id']);
            $table->unique(['club_governance_meeting_id', 'club_external_member_id'], 'meeting_external_recipient_unique');
            $table->index(['club_id', 'attendance_eligible', 'voting_eligible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_governance_meeting_recipients');
        Schema::dropIfExists('club_governance_meetings');
    }
};
