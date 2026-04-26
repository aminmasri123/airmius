<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->foreignId('club_id')->nullable()->change();
            $table->foreign('club_id')->references('id')->on('clubs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->foreignId('club_id')->nullable(false)->change();
            $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
        });
    }
};
