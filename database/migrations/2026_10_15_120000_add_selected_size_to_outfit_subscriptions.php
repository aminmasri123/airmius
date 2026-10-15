<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('outfit_subscriptions', 'selected_size')) {
            Schema::table('outfit_subscriptions', function (Blueprint $table) {
                $table->string('selected_size', 60)->nullable()->after('currency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('outfit_subscriptions', 'selected_size')) {
            Schema::table('outfit_subscriptions', function (Blueprint $table) {
                $table->dropColumn('selected_size');
            });
        }
    }
};
