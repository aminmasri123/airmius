<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_participants')->where('status', 'accepted')->update(['status' => 'yes']);
        DB::table('event_participants')->where('status', 'declined')->update(['status' => 'no']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE event_participants MODIFY status ENUM('yes','no','maybe') NOT NULL");
        }

        Schema::table('event_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('event_participants', 'created_at')) {
                $table->timestamps();
            }

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'user_id']);
        });
    }
};
