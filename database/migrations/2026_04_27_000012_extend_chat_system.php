<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('club_id')->constrained()->nullOnDelete();
            }
        });

        if (Schema::hasTable('conversations') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE conversations MODIFY type ENUM('direct','group','team','event') NOT NULL");
        }

        if (Schema::hasTable('conversations') && DB::getDriverName() === 'mysql') {
            DB::table('conversations')
                ->join('events', 'events.conversation_id', '=', 'conversations.id')
                ->update([
                    'conversations.type' => 'event',
                    'conversations.team_id' => DB::raw('events.team_id'),
                ]);
        }

        if (Schema::hasColumn('messages', 'message') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE messages MODIFY message TEXT NULL');
        }

        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['message_id', 'file_id']);
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 32);
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_attachments');

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        if (Schema::hasTable('conversations') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE conversations MODIFY type ENUM('direct','group') NOT NULL");
        }

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'team_id')) {
                $table->dropConstrainedForeignId('team_id');
            }
        });
    }
};
