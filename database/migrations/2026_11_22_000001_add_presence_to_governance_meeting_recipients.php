<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('club_governance_meeting_recipients', function (Blueprint $table) {
            $table->boolean('was_present')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('club_governance_meeting_recipients', function (Blueprint $table) {
            $table->dropColumn('was_present');
        });
    }
};
