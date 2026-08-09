<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gamification_rules')) {
            return;
        }

        DB::table('gamification_rules')->updateOrInsert(
            [
                'key' => 'training_completed',
                'actor_type' => 'sportler',
            ],
            [
                'category' => 'Training',
                'label' => 'Verifiziertes Training abgeschlossen',
                'description' => 'Eine abgeschlossene, einem Trainingsplan oder eigenen GPS-Track zugeordnete Einheit wurde dokumentiert. Gesundheitswerte beeinflussen die Vergabe nicht.',
                'xp_amount' => 8,
                'daily_limit' => 1,
                'trust_delta' => 0,
                'is_penalty' => false,
                'is_active' => true,
                'meta' => json_encode([
                    'requires' => ['training_plan_item_or_owned_completed_gps_track'],
                    'excludes' => ['notes', 'wellness', 'calories', 'body_metrics'],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('gamification_rules')) {
            return;
        }

        DB::table('gamification_rules')
            ->where('key', 'training_completed')
            ->where('actor_type', 'sportler')
            ->delete();
    }
};
