<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->json('checkin_slots')->nullable()->after('frequency');
        });

        Schema::table('challenge_checkins', function (Blueprint $table) {
            $table->dropUnique(['challenge_id', 'user_id', 'checkin_date']);
            $table->string('slot', 16)->default('anytime')->after('checkin_date');
            $table->unique(['challenge_id', 'user_id', 'checkin_date', 'slot'], 'challenge_checkins_period_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('challenge_checkins', function (Blueprint $table) {
            $table->dropUnique('challenge_checkins_period_slot_unique');
            $table->dropColumn('slot');
            $table->unique(['challenge_id', 'user_id', 'checkin_date']);
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn('checkin_slots');
        });
    }
};
