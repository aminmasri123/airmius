<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('clubs')
            ->whereNotNull('owner_id')
            ->orderBy('id')
            ->get(['id', 'owner_id'])
            ->each(function ($club) use ($now) {
                $exists = DB::table('club_user')
                    ->where('club_id', $club->id)
                    ->where('user_id', $club->owner_id)
                    ->exists();

                if ($exists) {
                    DB::table('club_user')
                        ->where('club_id', $club->id)
                        ->where('user_id', $club->owner_id)
                        ->update([
                            'role' => 'owner',
                            'updated_at' => $now,
                        ]);

                    return;
                }

                DB::table('club_user')->insert([
                    'club_id' => $club->id,
                    'user_id' => $club->owner_id,
                    'role' => 'owner',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        //
    }
};
