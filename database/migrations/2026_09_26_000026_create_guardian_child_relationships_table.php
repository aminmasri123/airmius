<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_child_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_email')->nullable();
            $table->string('relationship_type')->default('guardian');
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('invited');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('backfilled_from_legacy')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'child_user_id', 'guardian_user_id'], 'guardian_child_unique_user');
            $table->unique(['club_id', 'child_user_id', 'guardian_email'], 'guardian_child_unique_email');
            $table->index(['club_id', 'child_user_id', 'status'], 'guardian_child_child_status_idx');
            $table->index(['club_id', 'guardian_user_id', 'status'], 'guardian_child_guardian_status_idx');
            $table->index(['club_id', 'guardian_email', 'status'], 'guardian_child_email_status_idx');
            $table->index(['child_user_id', 'backfilled_from_legacy'], 'guardian_child_legacy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_child_relationships');
    }
};
