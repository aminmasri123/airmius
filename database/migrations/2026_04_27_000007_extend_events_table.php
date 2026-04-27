<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'club_id')) {
                $table->foreignId('club_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('events', 'conversation_id')) {
                $table->foreignId('conversation_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('events', 'visibility')) {
                $table->string('visibility', 20)->default('private')->after('type');
            }

            if (! Schema::hasColumn('events', 'recurrence_ends_at')) {
                $table->timestamp('recurrence_ends_at')->nullable()->after('recurring');
            }

            if (! Schema::hasColumn('events', 'reminder_at')) {
                $table->timestamp('reminder_at')->nullable()->after('recurrence_ends_at');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('events')->where('type', 'game')->update(['type' => 'match']);
            DB::statement("ALTER TABLE events MODIFY team_id BIGINT UNSIGNED NULL");
            DB::statement("ALTER TABLE events MODIFY type ENUM('training','match','meeting','public') NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'conversation_id')) {
                $table->dropConstrainedForeignId('conversation_id');
            }

            if (Schema::hasColumn('events', 'club_id')) {
                $table->dropConstrainedForeignId('club_id');
            }

            $table->dropColumn([
                'visibility',
                'recurrence_ends_at',
                'reminder_at',
            ]);
        });
    }
};
