<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('folders', 'club_id') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE folders MODIFY club_id BIGINT UNSIGNED NULL');
        }

        Schema::table('folders', function (Blueprint $table) {
            if (! Schema::hasColumn('folders', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('folders', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('club_id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('folders', 'event_id')) {
                $table->foreignId('event_id')->nullable()->after('team_id')->constrained()->cascadeOnDelete();
            }
        });

        Schema::table('files', function (Blueprint $table) {
            if (! Schema::hasColumn('files', 'event_id')) {
                $table->foreignId('event_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'event_id')) {
                $table->dropConstrainedForeignId('event_id');
            }
        });

        Schema::table('folders', function (Blueprint $table) {
            if (Schema::hasColumn('folders', 'event_id')) {
                $table->dropConstrainedForeignId('event_id');
            }

            if (Schema::hasColumn('folders', 'team_id')) {
                $table->dropConstrainedForeignId('team_id');
            }

            if (Schema::hasColumn('folders', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
