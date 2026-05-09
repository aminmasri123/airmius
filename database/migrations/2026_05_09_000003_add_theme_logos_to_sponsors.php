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
            if (! Schema::hasColumn('sponsors', 'logo_light')) {
                $table->string('logo_light', 2048)->nullable()->after('logo');
            }

            if (! Schema::hasColumn('sponsors', 'logo_dark')) {
                $table->string('logo_dark', 2048)->nullable()->after('logo_light');
            }
        });

        DB::table('sponsors')
            ->whereNotNull('logo')
            ->whereNull('logo_light')
            ->update(['logo_light' => DB::raw('logo')]);

        DB::table('sponsors')
            ->whereNotNull('logo')
            ->whereNull('logo_dark')
            ->update(['logo_dark' => DB::raw('logo')]);
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            if (Schema::hasColumn('sponsors', 'logo_dark')) {
                $table->dropColumn('logo_dark');
            }

            if (Schema::hasColumn('sponsors', 'logo_light')) {
                $table->dropColumn('logo_light');
            }
        });
    }
};
