<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_external_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('role', 30)->default('member');
            $table->string('membership_status', 30)->default('active');
            $table->string('member_number', 80)->nullable();
            $table->string('athlete_license_number', 120)->nullable();
            $table->decimal('contribution_amount', 10, 2)->nullable();
            $table->string('contribution_interval', 30)->nullable();
            $table->date('joined_on')->nullable();
            $table->text('membership_notes')->nullable();
            $table->string('invitation_status', 30)->default('none');
            $table->string('invitation_token', 80)->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'email']);
            $table->index(['club_id', 'invitation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_external_members');
    }
};
