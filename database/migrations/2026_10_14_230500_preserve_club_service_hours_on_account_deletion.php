<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'club_service_hour_records' => ['user_id', 'recorded_by'],
        'club_service_hour_corrections' => ['user_id', 'corrected_by'],
        'club_service_hour_exemptions' => ['user_id', 'approved_by'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->foreignId($column)->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $name => $columns) {
            foreach ($columns as $column) {
                if (DB::table($name)->whereNull($column)->exists()) {
                    throw new RuntimeException('Cannot restore required service-hour participants after account deletion.');
                }
            }
        }

        foreach (self::COLUMNS as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->foreignId($column)->nullable(false)->change();
                }
            });
        }
    }
};
