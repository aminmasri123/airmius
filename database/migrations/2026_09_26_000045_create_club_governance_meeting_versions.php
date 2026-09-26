<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_governance_meeting_versions')) {
            Schema::create('club_governance_meeting_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained(indexName: 'club_gov_meeting_versions_club_fk')->cascadeOnDelete();
                $table->foreignId('club_governance_meeting_id')->constrained('club_governance_meetings', indexName: 'club_gov_meeting_versions_meeting_fk')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->string('status', 30);
                $table->dateTime('scheduled_at')->nullable();
                $table->string('location_name', 180)->nullable();
                $table->date('motions_due_on')->nullable();
                $table->dateTime('invitation_sent_at')->nullable();
                $table->json('agenda_items')->nullable();
                $table->json('materials')->nullable();
                $table->json('decision_templates')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'club_gov_meeting_versions_creator_fk')->nullOnDelete();
                $table->timestamps();

                $table->unique(['club_governance_meeting_id', 'version'], 'governance_meeting_version_unique');
                $table->index(['club_id', 'club_governance_meeting_id'], 'club_gov_meeting_versions_club_meeting_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_governance_meeting_versions');
    }
};
