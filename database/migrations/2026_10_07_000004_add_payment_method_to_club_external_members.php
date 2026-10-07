<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_external_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_external_members', 'payment_method')) {
                $table->string('payment_method', 40)->nullable()->after('contribution_interval');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_external_members', function (Blueprint $table): void {
            if (Schema::hasColumn('club_external_members', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
