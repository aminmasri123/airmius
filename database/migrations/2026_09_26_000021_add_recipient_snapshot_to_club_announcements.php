<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_announcements', function (Blueprint $table): void {
            $table->unsignedInteger('recipient_snapshot_count')->default(0)->after('audience_type');
            $table->char('recipient_snapshot_hash', 64)->nullable()->after('recipient_snapshot_count');
            $table->timestamp('recipient_snapshot_at')->nullable()->after('recipient_snapshot_hash');
        });
    }

    public function down(): void
    {
        Schema::table('club_announcements', function (Blueprint $table): void {
            $table->dropColumn([
                'recipient_snapshot_count',
                'recipient_snapshot_hash',
                'recipient_snapshot_at',
            ]);
        });
    }
};
