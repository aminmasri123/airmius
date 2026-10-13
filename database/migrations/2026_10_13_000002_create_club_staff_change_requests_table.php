<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_staff_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained('club_staff_assignments')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('status', 24)->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['club_id', 'status'], 'staff_change_requests_club_status_idx');
            $table->index(['target_user_id', 'status'], 'staff_change_requests_target_status_idx');
            $table->index(['assignment_id', 'status'], 'staff_change_requests_assignment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_staff_change_requests');
    }
};
