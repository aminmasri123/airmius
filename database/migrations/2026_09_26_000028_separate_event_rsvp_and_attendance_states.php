<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_participants')) {
            return;
        }

        Schema::table('event_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_participants', 'rsvp_status')) {
                $table->string('rsvp_status', 20)->nullable()->after('status');
            }
            if (! Schema::hasColumn('event_participants', 'attendance_status')) {
                $table->string('attendance_status', 20)->nullable()->after('rsvp_status');
            }
            if (! Schema::hasColumn('event_participants', 'absence_reason')) {
                $table->text('absence_reason')->nullable()->after('response_reason');
            }
        });

        DB::table('event_participants')
            ->whereNull('rsvp_status')
            ->whereIn('status', ['yes', 'maybe', 'no', 'waitlist'])
            ->update(['rsvp_status' => DB::raw('status')]);

        DB::table('event_participants')
            ->whereNull('attendance_status')
            ->whereIn('status', ['yes', 'late', 'no'])
            ->update([
                'attendance_status' => DB::raw("case status when 'yes' then 'present' when 'late' then 'late' when 'no' then 'absent' end"),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_participants')) {
            return;
        }

        Schema::table('event_participants', function (Blueprint $table): void {
            foreach (['absence_reason', 'attendance_status', 'rsvp_status'] as $column) {
                if (Schema::hasColumn('event_participants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
