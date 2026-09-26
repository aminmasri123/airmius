<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_governance_meeting_decisions', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_governance_meeting_decisions', 'club_policy_document_id')) {
                $table->foreignId('club_policy_document_id')
                    ->nullable()
                    ->after('club_governance_meeting_id')
                    ->constrained('club_policy_documents')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('club_governance_meeting_decisions', 'contract_review_status')) {
                $table->string('contract_review_status', 40)->nullable()->after('club_policy_document_id');
            }
            if (! Schema::hasColumn('club_governance_meeting_decisions', 'external_review')) {
                $table->json('external_review')->nullable()->after('contract_review_status');
            }
            if (! Schema::hasColumn('club_governance_meeting_decisions', 'ballot_salt_hash')) {
                $table->string('ballot_salt_hash', 64)->nullable()->after('external_review');
            }
            $table->index(['club_id', 'club_policy_document_id'], 'governance_decision_policy_document_idx');
        });

        Schema::table('club_governance_meeting_decision_votes', function (Blueprint $table): void {
            $table->foreignId('club_governance_meeting_recipient_id')->nullable()->change();
            if (! Schema::hasColumn('club_governance_meeting_decision_votes', 'ballot_hash')) {
                $table->string('ballot_hash', 64)->nullable()->after('club_governance_meeting_recipient_id');
            }
            $table->unique(
                ['club_governance_meeting_decision_id', 'ballot_hash'],
                'governance_decision_secret_ballot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('club_governance_meeting_decision_votes', function (Blueprint $table): void {
            $table->dropUnique('governance_decision_secret_ballot_unique');
            $table->dropColumn('ballot_hash');
            $table->foreignId('club_governance_meeting_recipient_id')->nullable(false)->change();
        });

        Schema::table('club_governance_meeting_decisions', function (Blueprint $table): void {
            $table->dropIndex('governance_decision_policy_document_idx');
            $table->dropConstrainedForeignId('club_policy_document_id');
            $table->dropColumn([
                'contract_review_status',
                'external_review',
                'ballot_salt_hash',
            ]);
        });
    }
};
