<?php

namespace App\Support;

use App\Models\Club;
use Illuminate\Support\Facades\DB;

final class ClubStandardRoles
{
    public static function ensureFor(Club $club): void
    {
        $now = now();
        $rows = collect(ClubRoleTemplates::payload())->map(fn (array $template) => [
            'club_id' => $club->id,
            'key' => 'standard_'.$template['key'],
            'name' => $template['name'],
            'permissions' => json_encode($template['permissions'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('club_role_definitions')->insertOrIgnore($rows);
    }
}
