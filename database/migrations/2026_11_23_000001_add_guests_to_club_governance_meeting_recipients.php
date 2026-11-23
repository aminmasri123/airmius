<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_governance_meeting_recipients', function (Blueprint $table): void {
            $table->string('guest_name', 160)->nullable()->after('club_external_member_id');
            $table->string('guest_role', 160)->nullable()->after('guest_name');
            $table->string('guest_organization', 180)->nullable()->after('guest_role');
            $table->string('guest_email', 254)->nullable()->after('guest_organization');
        });
    }

    public function down(): void
    {
        Schema::table('club_governance_meeting_recipients', function (Blueprint $table): void {
            $table->dropColumn(['guest_name', 'guest_role', 'guest_organization', 'guest_email']);
        });
    }
};
