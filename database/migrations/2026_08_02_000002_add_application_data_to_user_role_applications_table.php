<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_role_applications', function (Blueprint $table) {
            $table->json('application_data')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('user_role_applications', function (Blueprint $table) {
            $table->dropColumn('application_data');
        });
    }
};
