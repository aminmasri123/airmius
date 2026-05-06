<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rides', 'club_id')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE rides MODIFY club_id BIGINT UNSIGNED NULL');
            } else {
                Schema::table('rides', fn (Blueprint $table) => $table->foreignId('club_id')->nullable()->change());
            }
        }

        Schema::table('rides', function (Blueprint $table) {
            if (! Schema::hasColumn('rides', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('club_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('rides', 'visibility')) {
                $table->string('visibility', 30)->default('friends')->after('team_id');
            }

            if (! Schema::hasColumn('rides', 'contact_details')) {
                $table->text('contact_details')->nullable()->after('seats');
            }
        });

        DB::table('rides')
            ->whereNull('visibility')
            ->orWhere('visibility', '')
            ->update(['visibility' => 'club']);
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            if (Schema::hasColumn('rides', 'contact_details')) {
                $table->dropColumn('contact_details');
            }

            if (Schema::hasColumn('rides', 'visibility')) {
                $table->dropColumn('visibility');
            }

            if (Schema::hasColumn('rides', 'team_id')) {
                $table->dropConstrainedForeignId('team_id');
            }
        });
    }
};
