<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_procurement_requests', function (Blueprint $table): void {
            $table->foreignId('team_id')
                ->nullable()
                ->after('club_budget_id')
                ->constrained()
                ->restrictOnDelete();
            $table->index(['club_id', 'team_id', 'status'], 'proc_requests_club_team_status_idx');
        });

        DB::table('club_procurement_requests')
            ->whereNull('team_id')
            ->whereNotNull('club_budget_id')
            ->orderBy('id')
            ->chunkById(200, function ($requests): void {
                $teamIds = DB::table('club_budgets')
                    ->whereIn('id', $requests->pluck('club_budget_id')->filter()->unique())
                    ->pluck('team_id', 'id');

                foreach ($requests as $request) {
                    $teamId = $teamIds[$request->club_budget_id] ?? null;
                    if ($teamId) {
                        DB::table('club_procurement_requests')
                            ->where('id', $request->id)
                            ->update(['team_id' => $teamId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('club_procurement_requests', function (Blueprint $table): void {
            $table->dropIndex('proc_requests_club_team_status_idx');
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
