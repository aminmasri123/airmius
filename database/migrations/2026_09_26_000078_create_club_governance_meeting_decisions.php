<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_governance_meeting_decisions')) {
            Schema::create('club_governance_meeting_decisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'gov_decisions_club_fk')->cascadeOnDelete();
                $table->foreignId('club_governance_meeting_id')->constrained('club_governance_meetings', indexName: 'gov_decisions_meeting_fk')->cascadeOnDelete();
                $table->unsignedInteger('meeting_version');
                $table->string('type', 30);
                $table->string('voting_mode', 30);
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('status', 30)->default('open');
                $table->string('majority_rule', 30)->default('simple');
                $table->unsignedTinyInteger('quorum')->nullable();
                $table->json('options')->nullable();
                $table->unsignedInteger('eligible_voters')->default(0);
                $table->json('recipient_snapshot')->nullable();
                $table->json('result_snapshot')->nullable();
                $table->string('outcome', 30)->nullable();
                $table->dateTime('correction_locked_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'gov_decisions_creator_fk')->nullOnDelete();
                $table->timestamps();

                $table->index(['club_id', 'club_governance_meeting_id', 'status'], 'governance_decision_status_idx');
            });
        }

        if (! Schema::hasTable('club_governance_meeting_decision_votes')) {
            Schema::create('club_governance_meeting_decision_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'gov_decision_votes_club_fk')->cascadeOnDelete();
                $table->foreignId('club_governance_meeting_decision_id')->constrained('club_governance_meeting_decisions', indexName: 'gov_decision_votes_decision_fk')->cascadeOnDelete();
                $table->foreignId('club_governance_meeting_recipient_id')->constrained('club_governance_meeting_recipients', indexName: 'gov_decision_votes_recipient_fk')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained(indexName: 'gov_decision_votes_user_fk')->cascadeOnDelete();
                $table->foreignId('club_external_member_id')->nullable()->constrained('club_external_members', indexName: 'gov_decision_votes_external_fk')->cascadeOnDelete();
                $table->string('choice', 80);
                $table->string('person_name', 180)->nullable();
                $table->json('recipient_snapshot')->nullable();
                $table->timestamps();

                $table->unique(['club_governance_meeting_decision_id', 'club_governance_meeting_recipient_id'], 'governance_decision_recipient_vote_unique');
                $table->index(['club_id', 'choice'], 'gov_decision_votes_choice_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_governance_meeting_decision_votes');
        Schema::dropIfExists('club_governance_meeting_decisions');
    }
};
