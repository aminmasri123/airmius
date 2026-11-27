<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('sepa_approval_mode', 16)->default('dual');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', fn (Blueprint $table) => $table->dropColumn('sepa_approval_mode'));
    }
};
