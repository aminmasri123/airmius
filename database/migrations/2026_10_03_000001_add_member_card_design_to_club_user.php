<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_user', 'member_card_design')) {
                $table->json('member_card_design')->nullable()->after('membership_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_user', function (Blueprint $table): void {
            if (Schema::hasColumn('club_user', 'member_card_design')) {
                $table->dropColumn('member_card_design');
            }
        });
    }
};
