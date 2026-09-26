<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_member_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_external_member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('title', 160);
            $table->string('issuer', 160)->nullable();
            $table->string('license_number', 120)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('proof_status', 40)->default('not_required');
            $table->timestamp('proof_checked_at')->nullable();
            $table->foreignId('proof_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('remind_on')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->string('visibility', 40)->default('membership_admins');
            $table->timestamps();

            $table->index(['club_id', 'user_id', 'valid_until']);
            $table->index(['club_id', 'club_external_member_id', 'valid_until'], 'club_member_qualifications_external_validity_index');
            $table->index(['club_id', 'remind_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_member_qualifications');
    }
};
