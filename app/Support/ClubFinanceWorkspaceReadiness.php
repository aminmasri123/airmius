<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class ClubFinanceWorkspaceReadiness
{
    public static function ready(): bool
    {
        return Schema::hasColumn('club_finance_entries', 'entry_kind')
            && Schema::hasColumn('team_fees', 'club_finance_entry_id');
    }
}
