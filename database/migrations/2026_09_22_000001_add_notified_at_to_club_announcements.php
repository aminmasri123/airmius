<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_announcements', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('published_at');
            $table->index(['published_at', 'notified_at']);
        });
        DB::table('club_announcements')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->update(['notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('club_announcements', function (Blueprint $table) {
            $table->dropIndex(['published_at', 'notified_at']);
            $table->dropColumn('notified_at');
        });
    }
};
