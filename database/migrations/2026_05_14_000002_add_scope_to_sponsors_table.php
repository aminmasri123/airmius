<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            if (! Schema::hasColumn('sponsors', 'scope')) {
                $table->string('scope', 40)->default('platform')->after('club_id')->index();
            }
        });

        DB::table('sponsors')
            ->whereNotNull('club_id')
            ->update(['scope' => 'club']);

        if (Schema::hasTable('outfit_subscription_plans')) {
            $planSponsorIds = DB::table('outfit_subscription_plans')
                ->whereNotNull('sponsor_id')
                ->pluck('sponsor_id')
                ->filter()
                ->unique()
                ->values();

            if ($planSponsorIds->isNotEmpty()) {
                DB::table('sponsors')
                    ->whereIn('id', $planSponsorIds)
                    ->whereNull('club_id')
                    ->update(['scope' => 'outfit_subscription']);
            }
        }

        if (Schema::hasTable('outfit_subscriptions')) {
            $subscriptionSponsorIds = DB::table('outfit_subscriptions')
                ->whereNotNull('sponsor_id')
                ->pluck('sponsor_id')
                ->filter()
                ->unique()
                ->values();

            if ($subscriptionSponsorIds->isNotEmpty()) {
                DB::table('sponsors')
                    ->whereIn('id', $subscriptionSponsorIds)
                    ->whereNull('club_id')
                    ->update(['scope' => 'outfit_subscription']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            if (Schema::hasColumn('sponsors', 'scope')) {
                $table->dropIndex(['scope']);
                $table->dropColumn('scope');
            }
        });
    }
};
