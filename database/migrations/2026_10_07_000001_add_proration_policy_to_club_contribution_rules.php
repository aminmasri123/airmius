<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_contribution_rules', function (Blueprint $table) {
            $table->string('proration_policy', 40)
                ->default('prorate_days')
                ->after('billing_interval');
        });
    }

    public function down(): void
    {
        Schema::table('club_contribution_rules', function (Blueprint $table) {
            $table->dropColumn('proration_policy');
        });
    }
};
