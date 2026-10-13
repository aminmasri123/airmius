<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_governance_bodies', function (Blueprint $table) {
            $table->string('template_key', 60)->nullable()->after('type');
        });

        Schema::create('club_governance_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_governance_body_id')->constrained('club_governance_bodies')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('responsibilities')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('allows_multiple')->default(false);
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['club_id', 'club_governance_body_id', 'is_active'], 'club_gov_positions_body_active_idx');
        });

        Schema::table('club_governance_assignments', function (Blueprint $table) {
            $table->foreignId('club_governance_position_id')
                ->nullable()
                ->after('club_governance_body_id')
                ->constrained('club_governance_positions')
                ->nullOnDelete();
        });

        $positions = [];
        $sortOrders = [];
        DB::table('club_governance_assignments')
            ->orderBy('club_governance_body_id')
            ->orderBy('position_title')
            ->orderBy('id')
            ->each(function ($assignment) use (&$positions, &$sortOrders): void {
                $key = $assignment->club_governance_body_id.'|'.mb_strtolower(trim($assignment->position_title));
                $positionId = $positions[$key] ?? null;
                if ($positionId === null) {
                    $sortOrder = $sortOrders[$assignment->club_governance_body_id] ?? 0;
                    $positionId = DB::table('club_governance_positions')->insertGetId([
                        'club_id' => $assignment->club_id,
                        'club_governance_body_id' => $assignment->club_governance_body_id,
                        'title' => trim($assignment->position_title),
                        'responsibilities' => $assignment->responsibilities,
                        'sort_order' => $sortOrder,
                        'is_required' => false,
                        'allows_multiple' => false,
                        'is_public' => (bool) $assignment->is_public,
                        'is_active' => true,
                        'created_at' => $assignment->created_at,
                        'updated_at' => $assignment->updated_at,
                    ]);
                    $positions[$key] = $positionId;
                    $sortOrders[$assignment->club_governance_body_id] = $sortOrder + 1;
                } else {
                    $changes = ['allows_multiple' => true];
                    if ($assignment->is_public) {
                        $changes['is_public'] = true;
                    }
                    DB::table('club_governance_positions')
                        ->where('id', $positionId)
                        ->update($changes);
                }

                DB::table('club_governance_assignments')
                    ->where('id', $assignment->id)
                    ->update(['club_governance_position_id' => $positionId]);
            });
    }

    public function down(): void
    {
        Schema::table('club_governance_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_governance_position_id');
        });

        Schema::dropIfExists('club_governance_positions');

        Schema::table('club_governance_bodies', function (Blueprint $table) {
            $table->dropColumn('template_key');
        });
    }
};
