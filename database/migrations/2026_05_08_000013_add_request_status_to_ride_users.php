<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ride_users', function (Blueprint $table) {
            if (! Schema::hasColumn('ride_users', 'status')) {
                $table->string('status', 20)->default('accepted')->after('user_id');
            }
            if (! Schema::hasColumn('ride_users', 'message')) {
                $table->text('message')->nullable()->after('status');
            }
            if (! Schema::hasColumn('ride_users', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('message');
            }
            if (! Schema::hasColumn('ride_users', 'created_at')) {
                $table->timestamps();
            }
        });

        DB::table('ride_users')->whereNull('status')->update(['status' => 'accepted']);
        $now = now();

        DB::table('ride_users')
            ->whereNull('created_at')
            ->update(['created_at' => $now]);

        DB::table('ride_users')
            ->whereNull('updated_at')
            ->update(['updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::table('ride_users', function (Blueprint $table) {
            foreach (['updated_at', 'created_at', 'responded_at', 'message', 'status'] as $column) {
                if (Schema::hasColumn('ride_users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
