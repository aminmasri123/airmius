<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_member_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('related_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('related_email')->nullable();
            $table->string('related_name')->nullable();
            $table->string('relationship_type')->default('contact');
            $table->json('purposes');
            $table->json('contact_methods')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_primary')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('legacy_source')->nullable();
            $table->unsignedBigInteger('legacy_source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_source_id'], 'club_member_relationship_legacy_unique');
            $table->index(['club_id', 'member_user_id', 'status'], 'club_member_relationship_member_status_idx');
            $table->index(['club_id', 'related_user_id', 'status'], 'club_member_relationship_related_status_idx');
            $table->index(['club_id', 'related_email', 'status'], 'club_member_relationship_email_status_idx');
            $table->index(['club_id', 'member_user_id', 'is_primary'], 'club_member_relationship_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_member_relationships');
    }
};
