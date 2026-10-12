<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_participants', function (Blueprint $table): void {
            $table->timestamp('eligible_at')->nullable()->after('attendance_status');
            $table->timestamp('attendance_verified_at')->nullable()->after('eligible_at');
            $table->foreignId('attendance_verified_by')->nullable()->after('attendance_verified_at')->constrained('users')->nullOnDelete();
            $table->index(['event_id', 'eligible_at']);
            $table->index(['event_id', 'attendance_verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table): void {
            $table->dropIndex(['event_id', 'eligible_at']);
            $table->dropIndex(['event_id', 'attendance_verified_at']);
            $table->dropConstrainedForeignId('attendance_verified_by');
            $table->dropColumn(['eligible_at', 'attendance_verified_at']);
        });
    }
};
