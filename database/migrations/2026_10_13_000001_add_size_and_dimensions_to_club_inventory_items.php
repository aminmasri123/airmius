<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->string('size', 120)->nullable()->after('category');
            $table->string('dimensions', 180)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('club_inventory_items', function (Blueprint $table): void {
            $table->dropColumn(['size', 'dimensions']);
        });
    }
};
