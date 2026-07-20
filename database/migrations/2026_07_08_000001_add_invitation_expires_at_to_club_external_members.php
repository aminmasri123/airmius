<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_external_members', function (Blueprint $table) {
            if (! Schema::hasColumn('club_external_members', 'invitation_expires_at')) {
                $table->timestamp('invitation_expires_at')->nullable()->after('invited_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_external_members', function (Blueprint $table) {
            if (Schema::hasColumn('club_external_members', 'invitation_expires_at')) {
                $table->dropColumn('invitation_expires_at');
            }
        });
    }
};
