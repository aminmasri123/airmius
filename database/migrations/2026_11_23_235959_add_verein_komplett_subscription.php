<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $elite = DB::table('subscription_plans')->where('slug', 'elite')->first();
        DB::table('subscription_plans')->insert([
            'slug' => 'verein-komplett',
            'target_actor' => 'verein',
            'name' => 'Verein Komplett',
            'description' => 'Alle Vereinsfunktionen und ein eigener Vereinsshop.',
            'monthly_price_cents' => 3900,
            'yearly_price_cents' => 0,
            'currency' => 'EUR',
            'member_limit' => $elite?->member_limit,
            'team_limit' => $elite?->team_limit,
            'storage_gb' => $elite?->storage_gb ?? 100,
            'features' => json_encode(['Alle Vereinsfunktionen', 'Shop-Verwaltung für den eigenen Verein'], JSON_THROW_ON_ERROR),
            'sort_order' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')->where('slug', 'verein-komplett')->delete();
    }
};
