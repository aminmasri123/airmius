<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('team_user')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->select('teams.club_id', 'team_user.user_id')
            ->whereNotNull('teams.club_id')
            ->orderBy('team_user.user_id')
            ->chunk(500, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $exists = DB::table('club_user')
                        ->where('club_id', $row->club_id)
                        ->where('user_id', $row->user_id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('club_user')->insert([
                        'club_id' => $row->club_id,
                        'user_id' => $row->user_id,
                        'role' => 'member',
                        'membership_status' => 'non_member',
                        'joined_on' => $now->toDateString(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
