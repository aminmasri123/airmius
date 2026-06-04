<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->json('membership_application_documents')->nullable()->after('membership_payment_methods');
        });

        Schema::table('club_membership_requests', function (Blueprint $table) {
            $table->json('accepted_documents')->nullable()->after('application_data');
        });
    }

    public function down(): void
    {
        Schema::table('club_membership_requests', function (Blueprint $table) {
            $table->dropColumn('accepted_documents');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('membership_application_documents');
        });
    }
};
