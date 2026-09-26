<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_access_handover_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departing_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('due_on');
            $table->string('status', 20)->default('pending');
            $table->string('decision', 30)->nullable();
            $table->foreignId('successor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('assignment_snapshot');
            $table->unsignedInteger('delegation_count')->default(0);
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at')->nullable();
            $table->text('proposal_note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'departing_user_id', 'due_on'], 'club_access_handover_review_unique');
            $table->index(['club_id', 'status', 'due_on'], 'club_access_handover_review_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_access_handover_reviews');
    }
};
