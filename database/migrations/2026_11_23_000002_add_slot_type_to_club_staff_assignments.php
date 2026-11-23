<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_staff_assignments', function (Blueprint $table) {
            $table->string('slot_type', 20)->default('primary')->after('shift_key');
            $table->index(['club_id', 'shift_key', 'slot_type', 'status'], 'club_staff_shift_slot_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('club_staff_assignments', function (Blueprint $table) {
            $table->dropIndex('club_staff_shift_slot_status_index');
            $table->dropColumn('slot_type');
        });
    }
};
