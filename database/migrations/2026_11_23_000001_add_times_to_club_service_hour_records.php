<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_service_hour_records', function (Blueprint $table) {
            $table->char('start_time', 5)->nullable();
            $table->char('end_time', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('club_service_hour_records', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
