<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_governance_meeting_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_governance_meeting_id')->constrained('club_governance_meetings')->cascadeOnDelete();
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
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['club_governance_meeting_id', 'version'], 'governance_meeting_version_unique');
            $table->index(['club_id', 'club_governance_meeting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_governance_meeting_versions');
    }
};
