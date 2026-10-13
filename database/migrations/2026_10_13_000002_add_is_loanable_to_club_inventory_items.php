<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->boolean('is_loanable')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->dropColumn('is_loanable');
        });
    }
};
