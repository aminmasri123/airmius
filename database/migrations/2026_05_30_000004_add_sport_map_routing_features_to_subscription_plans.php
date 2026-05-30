<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $features = [
            'free' => ['Sportkarte Basis'],
            'club' => ['Sportorte und Vereinsstandorte'],
            'pro' => ['150 automatische Routenvorschlaege pro Monat'],
            'sportler-free' => ['Sportkarte, Tracking und Sportplaetze', '10 automatische Routenvorschlaege pro Monat'],
            'sportler-pro' => ['150 automatische Routenvorschlaege pro Monat'],
            'trainer-free' => ['Sportkarte und Tracking Basis'],
            'trainer-pro' => ['150 automatische Routenvorschlaege pro Monat'],
        ];

        foreach ($features as $slug => $items) {
            $plan = DB::table('subscription_plans')->where('slug', $slug)->first(['id', 'features']);

            if (! $plan) {
                continue;
            }

            $current = json_decode((string) $plan->features, true);
            $current = is_array($current) ? $current : [];

            foreach ($items as $item) {
                if (! in_array($item, $current, true)) {
                    $current[] = $item;
                }
            }

            DB::table('subscription_plans')
                ->where('id', $plan->id)
                ->update([
                    'features' => json_encode(array_values($current)),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Feature wording is additive and intentionally left in place.
    }
};
