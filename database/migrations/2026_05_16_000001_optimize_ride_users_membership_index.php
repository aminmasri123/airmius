<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "DELETE ru_keep
                FROM ride_users ru_keep
                INNER JOIN ride_users ru_remove
                    ON ru_keep.ride_id = ru_remove.ride_id
                    AND ru_keep.user_id = ru_remove.user_id
                    AND (
                        CASE
                            WHEN ru_remove.status = 'accepted' THEN 0
                            WHEN ru_remove.status = 'requested' THEN 1
                            ELSE 2
                        END < CASE
                            WHEN ru_keep.status = 'accepted' THEN 0
                            WHEN ru_keep.status = 'requested' THEN 1
                            ELSE 2
                        END
                        OR (
                            CASE
                                WHEN ru_remove.status = 'accepted' THEN 0
                                WHEN ru_remove.status = 'requested' THEN 1
                                ELSE 2
                            END = CASE
                                WHEN ru_keep.status = 'accepted' THEN 0
                                WHEN ru_keep.status = 'requested' THEN 1
                                ELSE 2
                            END
                            AND ru_remove.id < ru_keep.id
                        )
                    )"
            );
        }

        Schema::table('ride_users', function (Blueprint $table) {
            if (! Schema::hasIndex('ride_users', 'ride_users_ride_id_user_id_unique')) {
                $table->unique(['ride_id', 'user_id'], 'ride_users_ride_id_user_id_unique');
            }

            if (! Schema::hasIndex('ride_users', 'ride_users_ride_id_status_index')) {
                $table->index(['ride_id', 'status'], 'ride_users_ride_id_status_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ride_users', function (Blueprint $table) {
            if (Schema::hasIndex('ride_users', 'ride_users_ride_id_user_id_unique')) {
                $table->dropUnique('ride_users_ride_id_user_id_unique');
            }

            if (Schema::hasIndex('ride_users', 'ride_users_ride_id_status_index')) {
                $table->dropIndex('ride_users_ride_id_status_index');
            }
        });
    }
};
