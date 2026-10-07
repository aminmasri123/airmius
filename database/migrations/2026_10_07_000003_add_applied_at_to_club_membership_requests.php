<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table) {
            $table->timestamp('applied_at')->nullable()->after('effective_on')->index();
        });
    }

    public function down(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table) {
            $table->dropColumn('applied_at');
        });
    }
};
