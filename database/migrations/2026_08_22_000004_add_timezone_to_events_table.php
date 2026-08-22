<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'event_timezone')) {
                $table->string('event_timezone', 80)->nullable()->after('end_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'event_timezone')) {
                $table->dropColumn('event_timezone');
            }
        });
    }
};
