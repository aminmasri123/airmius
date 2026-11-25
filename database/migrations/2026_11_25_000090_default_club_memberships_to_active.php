<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table): void {
            $table->string('membership_status', 30)->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('club_user', function (Blueprint $table): void {
            $table->string('membership_status', 30)->default('non_member')->change();
        });
    }
};
