<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gamification_xp_events', function (Blueprint $table) {
            if (! Schema::hasColumn('gamification_xp_events', 'owner_type')) {
                $table->nullableMorphs('owner');
            }
        });

        Schema::table('badges', function (Blueprint $table) {
            if (! Schema::hasColumn('badges', 'key')) {
                $table->string('key')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('badges', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('badges', 'actor_type')) {
                $table->string('actor_type')->default('sportler')->after('icon');
            }

            if (! Schema::hasColumn('badges', 'trigger')) {
                $table->string('trigger')->default('xp')->after('actor_type');
            }

            if (! Schema::hasColumn('badges', 'threshold')) {
                $table->unsignedInteger('threshold')->default(0)->after('trigger');
            }

            if (! Schema::hasColumn('badges', 'meta')) {
                $table->json('meta')->nullable()->after('threshold');
            }
        });

        Schema::table('user_badges', function (Blueprint $table) {
            if (! Schema::hasColumn('user_badges', 'awardable_type')) {
                $table->nullableMorphs('awardable');
            }

            if (! Schema::hasColumn('user_badges', 'reason')) {
                $table->string('reason')->nullable()->after('badge_id');
            }

            if (! Schema::hasColumn('user_badges', 'meta')) {
                $table->json('meta')->nullable()->after('reason');
            }

            if (! Schema::hasColumn('user_badges', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_badges', function (Blueprint $table) {
            if (Schema::hasColumn('user_badges', 'awardable_type')) {
                $table->dropMorphs('awardable');
            }

            foreach (['reason', 'meta', 'created_at', 'updated_at'] as $column) {
                if (Schema::hasColumn('user_badges', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('badges', function (Blueprint $table) {
            foreach (['key', 'description', 'actor_type', 'trigger', 'threshold', 'meta'] as $column) {
                if (Schema::hasColumn('badges', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('gamification_xp_events', function (Blueprint $table) {
            if (Schema::hasColumn('gamification_xp_events', 'owner_type')) {
                $table->dropMorphs('owner');
            }
        });
    }
};
