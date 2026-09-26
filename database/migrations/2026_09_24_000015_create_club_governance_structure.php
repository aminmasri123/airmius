<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_governance_bodies')) {
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
                $table->unique(['club_id', 'type', 'name'], 'club_gov_bodies_club_type_name_unique');
                $table->index(['club_id', 'is_public', 'type'], 'club_gov_bodies_public_type_idx');
            });
        }

        if (! Schema::hasTable('club_governance_assignments')) {
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
                $table->index(['user_id', 'ends_on'], 'club_gov_assignments_user_ends_idx');
                $table->index(['club_external_member_id', 'ends_on'], 'club_gov_assignments_external_ends_idx');
            });

            return;
        }

        Schema::table('club_governance_assignments', function (Blueprint $table) {
            if (! $this->indexExists('club_governance_assignments', 'club_gov_assignments_club_body_idx')) {
                $table->index(['club_id', 'club_governance_body_id'], 'club_gov_assignments_club_body_idx');
            }

            if (! $this->indexExists('club_governance_assignments', 'club_gov_assignments_user_ends_idx')) {
                $table->index(['user_id', 'ends_on'], 'club_gov_assignments_user_ends_idx');
            }

            if (! $this->indexExists('club_governance_assignments', 'club_gov_assignments_external_ends_idx')) {
                $table->index(['club_external_member_id', 'ends_on'], 'club_gov_assignments_external_ends_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_governance_assignments');
        Schema::dropIfExists('club_governance_bodies');
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
