<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_membership_types', function (Blueprint $table) {
            if (! Schema::hasColumn('club_membership_types', 'application_fields')) {
                $table->json('application_fields')->nullable()->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_membership_types', function (Blueprint $table) {
            if (Schema::hasColumn('club_membership_types', 'application_fields')) {
                $table->dropColumn('application_fields');
            }
        });
    }
};
