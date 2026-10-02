<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('posting_policy', 24)->default('all')->after('description');
        });

        Schema::table('conversation_users', function (Blueprint $table): void {
            $table->string('role', 24)->default('member')->after('user_id');
            $table->index(['conversation_id', 'role'], 'conversation_users_conversation_role_idx');
        });

        DB::table('conversations')
            ->where('type', 'group')
            ->whereNotNull('owner_id')
            ->orderBy('id')
            ->each(function (object $conversation): void {
                DB::table('conversation_users')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $conversation->owner_id)
                    ->update(['role' => 'owner']);
            });
    }

    public function down(): void
    {
        Schema::table('conversation_users', function (Blueprint $table): void {
            $table->dropIndex('conversation_users_conversation_role_idx');
            $table->dropColumn('role');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('posting_policy');
        });
    }
};
