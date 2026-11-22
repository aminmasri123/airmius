<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_coupons', function (Blueprint $table) {
            $table->string('scope')->default('subscription');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_coupons', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
