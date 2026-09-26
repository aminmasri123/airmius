<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_permission_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grantor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grantee_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('permissions');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['club_id', 'grantee_user_id', 'starts_at', 'ends_at'], 'club_permission_delegations_active_index');
            $table->index(['club_id', 'grantor_user_id', 'revoked_at'], 'club_permission_delegations_grantor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_permission_delegations');
    }
};
