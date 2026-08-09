<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_route_tracks', function (Blueprint $table) {
            $table->string('source_activity_id', 160)->nullable()->after('source');
        });

        DB::table('sport_route_tracks')
            ->whereNull('source_activity_id')
            ->orderBy('id')
            ->chunkById(200, function ($tracks): void {
                foreach ($tracks as $track) {
                    $metrics = is_string($track->metrics)
                        ? (json_decode($track->metrics, true) ?: [])
                        : (array) $track->metrics;
                    $externalId = $metrics['external_activity_id'] ?? null;

                    if (! is_string($externalId) || $externalId === '' || mb_strlen($externalId) > 160) {
                        continue;
                    }

                    $alreadyMapped = DB::table('sport_route_tracks')
                        ->where('user_id', $track->user_id)
                        ->where('source', $track->source)
                        ->where('source_activity_id', $externalId)
                        ->exists();

                    if (! $alreadyMapped) {
                        DB::table('sport_route_tracks')
                            ->where('id', $track->id)
                            ->update(['source_activity_id' => $externalId]);
                    }
                }
            });

        Schema::table('sport_route_tracks', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'source', 'source_activity_id'],
                'sport_route_tracks_user_source_activity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('sport_route_tracks', function (Blueprint $table) {
            $table->dropUnique('sport_route_tracks_user_source_activity_unique');
            $table->dropColumn('source_activity_id');
        });
    }
};
