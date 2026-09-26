<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_governance_meetings', function (Blueprint $table) {
            $table->date('eligibility_as_of')->nullable()->after('participant_scope');
            $table->index(['club_id', 'eligibility_as_of']);
        });

        Schema::table('club_governance_meeting_recipients', function (Blueprint $table) {
            $table->string('eligibility_source', 40)->nullable()->after('voting_eligible');
            $table->string('eligibility_role', 80)->nullable()->after('eligibility_source');
            $table->index(['club_id', 'eligibility_source', 'eligibility_role'], 'meeting_recipient_eligibility_source_idx');
        });

        Schema::table('club_governance_meeting_versions', function (Blueprint $table) {
            $table->date('eligibility_as_of')->nullable()->after('location_name');
            $table->json('recipient_snapshot')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('club_governance_meeting_versions', function (Blueprint $table) {
            $table->dropColumn(['eligibility_as_of', 'recipient_snapshot']);
        });

        Schema::table('club_governance_meeting_recipients', function (Blueprint $table) {
            $table->dropIndex('meeting_recipient_eligibility_source_idx');
            $table->dropColumn(['eligibility_source', 'eligibility_role']);
        });

        Schema::table('club_governance_meetings', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'eligibility_as_of']);
            $table->dropColumn('eligibility_as_of');
        });
    }
};
