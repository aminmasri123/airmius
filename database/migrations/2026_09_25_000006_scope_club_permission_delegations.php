<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_permission_delegations', function (Blueprint $table): void {
            $table->string('scope_type', 20)->default('club')->after('permissions');
            $table->unsignedBigInteger('scope_id')->nullable()->after('scope_type');
            $table->string('scope_key', 64)->default('club')->after('scope_id');
            $table->index(
                ['club_id', 'grantee_user_id', 'scope_type', 'scope_id'],
                'club_permission_delegations_scope_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('club_permission_delegations', function (Blueprint $table): void {
            $table->dropIndex('club_permission_delegations_scope_index');
            $table->dropColumn(['scope_type', 'scope_id', 'scope_key']);
        });
    }
};
