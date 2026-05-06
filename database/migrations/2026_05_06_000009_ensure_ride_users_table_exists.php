<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ride_user') && ! Schema::hasTable('ride_users')) {
            Schema::rename('ride_user', 'ride_users');
        }

        if (! Schema::hasTable('ride_users')) {
            Schema::create('ride_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ride_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            });
        }

        $driverRows = DB::table('rides')
            ->select('id as ride_id', 'driver_id as user_id')
            ->whereNotNull('driver_id')
            ->get();

        foreach ($driverRows as $row) {
            DB::table('ride_users')->updateOrInsert([
                'ride_id' => $row->ride_id,
                'user_id' => $row->user_id,
            ]);
        }
    }

    public function down(): void
    {
        // Keep user ride memberships intact on rollback.
    }
};
