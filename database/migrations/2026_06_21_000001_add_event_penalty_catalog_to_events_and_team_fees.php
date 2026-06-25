<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'uses_penalty_catalog')) {
                $table->boolean('uses_penalty_catalog')
                    ->default(false)
                    ->after('max_participants');
            }
        });

        Schema::table('team_fees', function (Blueprint $table): void {
            if (! Schema::hasColumn('team_fees', 'event_id')) {
                $table->foreignId('event_id')
                    ->nullable()
                    ->after('team_id')
                    ->constrained('events')
                    ->nullOnDelete();

                $table->index(['event_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_fees', function (Blueprint $table): void {
            if (Schema::hasColumn('team_fees', 'event_id')) {
                $table->dropIndex(['event_id', 'status']);
                $table->dropConstrainedForeignId('event_id');
            }
        });

        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'uses_penalty_catalog')) {
                $table->dropColumn('uses_penalty_catalog');
            }
        });
    }
};
