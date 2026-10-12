<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('training_session_id')->nullable()->after('sport_route_id')->constrained()->nullOnDelete();
            $table->json('training_session_snapshot')->nullable()->after('training_session_id');
            $table->index(['team_id', 'training_session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'training_session_id']);
            $table->dropConstrainedForeignId('training_session_id');
            $table->dropColumn('training_session_snapshot');
        });
    }
};
