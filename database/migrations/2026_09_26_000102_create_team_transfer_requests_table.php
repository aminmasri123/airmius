<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('target_team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('role', 40)->default('Player');
            $table->string('status', 20)->default('pending');
            $table->string('origin', 20)->default('member_request');
            $table->date('effective_on');
            $table->text('message')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status', 'effective_on'], 'team_transfer_requests_club_status_effective_index');
            $table->index(['user_id', 'status']);
            $table->index(['source_team_id', 'target_team_id'], 'team_transfer_requests_source_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_transfer_requests');
    }
};
