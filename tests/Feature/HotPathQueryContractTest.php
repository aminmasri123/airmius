<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatLatestMessageLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HotPathQueryContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_chat_previews_have_a_constant_query_budget(): void
    {
        $viewer = User::factory()->create();
        $sender = User::factory()->create();
        $conversations = collect();
        $expectedMessageIds = [];

        foreach (range(1, 25) as $position) {
            $conversation = Conversation::query()->create(['type' => 'direct']);
            DB::table('conversation_users')->insert([
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $viewer->id,
                    'joined_at' => now()->subDay(),
                ],
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $sender->id,
                    'joined_at' => now()->subDay(),
                ],
            ]);

            $first = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'message' => "first {$position}",
            ]);
            $latest = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'message' => "latest {$position}",
            ]);

            if ($position === 1) {
                DB::table('message_hides')->insert([
                    'message_id' => $latest->id,
                    'user_id' => $viewer->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $expectedMessageIds[$conversation->id] = $first->id;
            } else {
                $expectedMessageIds[$conversation->id] = $latest->id;
            }

            $conversations->push($conversation);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            app(ChatLatestMessageLoader::class)->attach(
                $conversations,
                $viewer->id,
                ['sender:id,name', 'attachments.file:id,display_name,path,thumbnail_path,type,size'],
            );

            $selectQueries = collect(DB::getQueryLog())
                ->filter(fn (array $query) => str_starts_with(strtolower(ltrim($query['query'])), 'select'));
        } finally {
            DB::disableQueryLog();
        }

        $this->assertLessThanOrEqual(5, $selectQueries->count());

        foreach ($conversations as $conversation) {
            $this->assertTrue($conversation->relationLoaded('latestVisibleMessage'));
            $this->assertSame(
                $expectedMessageIds[$conversation->id],
                $conversation->latestVisibleMessage?->id,
            );
        }
    }

    public function test_scheduler_and_workspace_hot_paths_have_composite_indexes(): void
    {
        $this->assertIndex('notifications', 'notifications_user_created_idx', ['user_id', 'created_at']);
        $this->assertIndex('notifications', 'notifications_user_unread_type_created_idx', ['user_id', 'read', 'type', 'created_at']);
        $this->assertIndex('conversation_users', 'conversation_users_user_conversation_idx', ['user_id', 'conversation_id']);
        $this->assertIndex('events', 'events_start_status_idx', ['start_time', 'status']);
        $this->assertIndex('events', 'events_team_start_idx', ['team_id', 'start_time']);
        $this->assertIndex('events', 'events_club_start_idx', ['club_id', 'start_time']);
        $this->assertIndex('events', 'events_reminder_due_idx', ['status', 'reminder_sent_at', 'reminder_at', 'start_time']);
        $this->assertIndex('mobile_push_deliveries', 'mobile_push_status_retry_queued_idx', ['status', 'next_attempt_at', 'queued_at']);
        $this->assertIndex('domain_outbox_events', 'domain_outbox_dispatch_idx', ['published_at', 'available_at', 'processing_at', 'occurred_at']);
        $this->assertNotContains('domain_outbox_pending_index', Schema::getIndexListing('domain_outbox_events'));
    }

    /** @param array<int, string> $columns */
    private function assertIndex(string $table, string $name, array $columns): void
    {
        $index = collect(Schema::getIndexes($table))->firstWhere('name', $name);

        $this->assertNotNull($index, "Missing index {$name} on {$table}.");
        $this->assertSame($columns, $index['columns']);
    }
}
