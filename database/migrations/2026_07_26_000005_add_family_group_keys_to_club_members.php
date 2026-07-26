<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_user', 'family_group_key')) {
                $table->string('family_group_key', 80)->nullable()->after('club_membership_type_id');
                $table->index(['club_id', 'family_group_key']);
            }
        });

        Schema::table('club_external_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_external_members', 'family_group_key')) {
                $table->string('family_group_key', 80)->nullable()->after('membership_status');
                $table->index(['club_id', 'family_group_key']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_external_members', function (Blueprint $table): void {
            if (Schema::hasColumn('club_external_members', 'family_group_key')) {
                $table->dropIndex('club_external_members_club_id_family_group_key_index');
                $table->dropColumn('family_group_key');
            }
        });

        Schema::table('club_user', function (Blueprint $table): void {
            if (Schema::hasColumn('club_user', 'family_group_key')) {
                $table->dropIndex('club_user_club_id_family_group_key_index');
                $table->dropColumn('family_group_key');
            }
        });
    }
};
