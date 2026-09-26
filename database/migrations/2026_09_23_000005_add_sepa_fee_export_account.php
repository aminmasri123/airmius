<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', fn (Blueprint $table) => $table->string('datev_fee_account', 20)->nullable());
    }

    public function down(): void
    {
        Schema::table('clubs', fn (Blueprint $table) => $table->dropColumn('datev_fee_account'));
    }
};
