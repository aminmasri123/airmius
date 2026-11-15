<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            foreach (['pickup', 'destination'] as $prefix) {
                $table->double($prefix.'_area_latitude')->nullable();
                $table->double($prefix.'_area_longitude')->nullable();
                $table->index([$prefix.'_area_latitude', $prefix.'_area_longitude']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            foreach (['pickup', 'destination'] as $prefix) {
                $table->dropIndex([$prefix.'_area_latitude', $prefix.'_area_longitude']);
                $table->dropColumn([$prefix.'_area_latitude', $prefix.'_area_longitude']);
            }
        });
    }
};
