<?php

use App\Models\Club;
use App\Support\ClubStandardRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Club::query()->select('id')->chunkById(100, function ($clubs): void {
            foreach ($clubs as $club) {
                ClubStandardRoles::ensureFor($club);
            }
        });
    }

    public function down(): void
    {
        // Keep definitions and assignments that clubs may have edited or used.
    }
};
