<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_service_hour_records', function (Blueprint $table): void {
            $table->string('notification_channel', 20)->default('none')->after('notes');
            $table->timestamp('notified_at')->nullable()->after('notification_channel');
        });
    }

    public function down(): void
    {
        Schema::table('club_service_hour_records', function (Blueprint $table): void {
            $table->dropColumn(['notification_channel', 'notified_at']);
        });
    }
};
