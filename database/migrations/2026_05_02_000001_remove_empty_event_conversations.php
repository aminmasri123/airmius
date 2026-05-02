<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $emptyEventConversationIds = DB::table('conversations')
            ->leftJoin('messages', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.type', 'event')
            ->groupBy('conversations.id')
            ->havingRaw('COUNT(messages.id) = 0')
            ->pluck('conversations.id');

        if ($emptyEventConversationIds->isEmpty()) {
            return;
        }

        DB::table('events')
            ->whereIn('conversation_id', $emptyEventConversationIds)
            ->update(['conversation_id' => null]);

        DB::table('conversation_users')
            ->whereIn('conversation_id', $emptyEventConversationIds)
            ->delete();

        DB::table('conversations')
            ->whereIn('id', $emptyEventConversationIds)
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
