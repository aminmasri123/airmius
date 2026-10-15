<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
