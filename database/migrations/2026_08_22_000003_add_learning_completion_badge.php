<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('badges')->updateOrInsert(
            ['key' => 'player_course_completed'],
            [
                'name' => 'Kurs erfolgreich abgeschlossen',
                'description' => 'Einen vollständigen Airmius-Kurs mit allen Pflichtbestandteilen abgeschlossen.',
                'icon' => 'las la-graduation-cap',
                'actor_type' => 'sportler',
                'trigger' => 'reason',
                'threshold' => 1,
                'meta' => json_encode(['reason' => 'course_completed'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $badgeId = DB::table('badges')
            ->where('key', 'player_course_completed')
            ->value('id');

        if ($badgeId && ! DB::table('user_badges')->where('badge_id', $badgeId)->exists()) {
            DB::table('badges')->where('id', $badgeId)->delete();
        }
    }
};
