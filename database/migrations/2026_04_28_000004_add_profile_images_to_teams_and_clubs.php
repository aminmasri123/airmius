<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            if (! Schema::hasColumn('teams', 'logo')) {
                $table->string('logo')->nullable()->after('sport_type');
            }

            if (! Schema::hasColumn('teams', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('logo');
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('logo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            if (Schema::hasColumn('teams', 'cover_image')) {
                $table->dropColumn('cover_image');
            }

            if (Schema::hasColumn('teams', 'logo')) {
                $table->dropColumn('logo');
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            if (Schema::hasColumn('clubs', 'cover_image')) {
                $table->dropColumn('cover_image');
            }
        });
    }
};
