<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            $table->timestamps();
            $table->unique(['club_id', 'user_id']);
        });

        Schema::table('team_user', function (Blueprint $table) {
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('team_user', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'user_id']);
            $table->dropTimestamps();
        });

        Schema::table('club_user', function (Blueprint $table) {
            $table->dropUnique(['club_id', 'user_id']);
            $table->dropTimestamps();
        });
    }
};
