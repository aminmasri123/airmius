<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gamification_xp_events', function (Blueprint $table) {
            if (! Schema::hasColumn('gamification_xp_events', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('source_id');
                $table->unique('idempotency_key', 'gamification_xp_events_idempotency_key_unique');
            }

            $table->index(['user_id', 'actor_type', 'reason', 'created_at'], 'gamification_xp_events_actor_reason_created_index');
            $table->index(['owner_type', 'owner_id', 'actor_type', 'reason'], 'gamification_xp_events_owner_actor_reason_index');
        });
    }

    public function down(): void
    {
        Schema::table('gamification_xp_events', function (Blueprint $table) {
            $table->dropIndex('gamification_xp_events_actor_reason_created_index');
            $table->dropIndex('gamification_xp_events_owner_actor_reason_index');

            if (Schema::hasColumn('gamification_xp_events', 'idempotency_key')) {
                $table->dropUnique('gamification_xp_events_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
