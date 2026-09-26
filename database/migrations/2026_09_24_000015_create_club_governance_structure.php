<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_governance_bodies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->unique(['club_id', 'type', 'name']);
            $table->index(['club_id', 'is_public', 'type']);
        });

        Schema::create('club_governance_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_governance_body_id')->constrained('club_governance_bodies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('club_external_member_id')->nullable()->constrained('club_external_members')->cascadeOnDelete();
            $table->string('position_title', 160);
            $table->text('responsibilities')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->index(['club_id', 'club_governance_body_id'], 'club_gov_assignments_club_body_idx');
            $table->index(['user_id', 'ends_on']);
            $table->index(['club_external_member_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_governance_assignments');
        Schema::dropIfExists('club_governance_bodies');
    }
};
