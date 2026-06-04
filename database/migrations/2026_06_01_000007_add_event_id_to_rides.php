<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table): void {
            if (! Schema::hasColumn('rides', 'event_id')) {
                $table->foreignId('event_id')->nullable()->after('team_id')->constrained('events')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('rides', 'event_id')) {
            Schema::table('rides', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('event_id');
            });
        }
    }
};
