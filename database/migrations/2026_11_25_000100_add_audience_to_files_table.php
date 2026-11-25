<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->string('audience', 32)->default('club_members')->after('folder_id');
            $table->index(['club_id', 'audience'], 'files_club_audience_index');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropIndex('files_club_audience_index');
            $table->dropColumn('audience');
        });
    }
};
