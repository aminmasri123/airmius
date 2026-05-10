<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('team_invitations', 'invited_at')) {
                $table->timestamp('invited_at')->nullable()->after('status')->index();
            }
        });

        if (Schema::hasColumn('team_invitations', 'invited_at')) {
            DB::table('team_invitations')
                ->whereNull('invited_at')
                ->where('status', 'pending')
                ->update(['invited_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('team_invitations', 'invited_at')) {
                $table->dropColumn('invited_at');
            }
        });
    }
};
