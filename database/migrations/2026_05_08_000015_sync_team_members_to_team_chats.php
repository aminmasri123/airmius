<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams') || ! Schema::hasTable('team_user') || ! Schema::hasTable('conversations') || ! Schema::hasTable('conversation_users')) {
            return;
        }

        $now = now();

        DB::table('teams')
            ->select(['id', 'club_id'])
            ->orderBy('id')
            ->chunkById(100, function ($teams) use ($now) {
                foreach ($teams as $team) {
                    $memberIds = DB::table('team_user')
                        ->where('team_id', $team->id)
                        ->pluck('user_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values();

                    if ($memberIds->isEmpty()) {
                        continue;
                    }

                    $conversation = DB::table('conversations')
                        ->where('type', 'team')
                        ->where('team_id', $team->id)
                        ->first();

                    if (! $conversation) {
                        $conversationId = DB::table('conversations')->insertGetId([
                            'club_id' => $team->club_id,
                            'team_id' => $team->id,
                            'type' => 'team',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    } else {
                        $conversationId = $conversation->id;

                        if (! $conversation->club_id && $team->club_id) {
                            DB::table('conversations')
                                ->where('id', $conversationId)
                                ->update([
                                    'club_id' => $team->club_id,
                                    'updated_at' => $now,
                                ]);
                        }
                    }

                    $existingIds = DB::table('conversation_users')
                        ->where('conversation_id', $conversationId)
                        ->whereIn('user_id', $memberIds)
                        ->pluck('user_id')
                        ->map(fn ($id) => (int) $id);

                    $memberIds
                        ->diff($existingIds)
                        ->each(fn (int $userId) => DB::table('conversation_users')->insert([
                            'conversation_id' => $conversationId,
                            'user_id' => $userId,
                            'joined_at' => $now,
                        ]));
                }
            });
    }

    public function down(): void
    {
        // Data sync only. Keep existing team chats and memberships intact.
    }
};
