<?php

use App\Support\ClubRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            if (! Schema::hasColumn('club_user', 'roles')) {
                $table->json('roles')->nullable()->after('role');
            }
        });

        DB::table('club_user')
            ->select(['club_id', 'user_id', 'role'])
            ->orderBy('club_id')
            ->get()
            ->each(function ($membership) {
                $roles = ClubRoles::normalize($membership->role, []);

                DB::table('club_user')
                    ->where('club_id', $membership->club_id)
                    ->where('user_id', $membership->user_id)
                    ->update(['roles' => json_encode($roles)]);
            });
    }

    public function down(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            if (Schema::hasColumn('club_user', 'roles')) {
                $table->dropColumn('roles');
            }
        });
    }
};
