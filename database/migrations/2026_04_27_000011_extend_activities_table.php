<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('activities', 'club_id')) {
                $table->foreignId('club_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('activities', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('club_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('activities', 'type')) {
                $table->string('type')->after('team_id');
            }

            if (! Schema::hasColumn('activities', 'subject_type')) {
                $table->nullableMorphs('subject');
            }

            if (! Schema::hasColumn('activities', 'data')) {
                $table->json('data')->nullable()->after('subject_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropMorphs('subject');
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('club_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['type', 'data']);
        });
    }
};
