<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['teams', 'club_training_groups'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if ($tableName === 'club_training_groups' && ! Schema::hasColumn($tableName, 'sport_year_period_id')) {
                    $table->foreignId('sport_year_period_id')->nullable()->after('club_location_id')->constrained('club_year_periods')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'birth_year_from')) {
                    $table->unsignedSmallInteger('birth_year_from')->nullable()->after('sport_type');
                }
                if (! Schema::hasColumn($tableName, 'birth_year_to')) {
                    $table->unsignedSmallInteger('birth_year_to')->nullable()->after('birth_year_from');
                }
                if (! Schema::hasColumn($tableName, 'performance_level')) {
                    $table->string('performance_level')->nullable()->after('birth_year_to');
                }
                if (! Schema::hasColumn($tableName, 'capacity')) {
                    $table->unsignedInteger('capacity')->nullable()->after('performance_level');
                }
                if (! Schema::hasColumn($tableName, 'waitlist_enabled')) {
                    $table->boolean('waitlist_enabled')->default(false)->after('capacity');
                }
                if (! Schema::hasColumn($tableName, 'valid_from')) {
                    $table->date('valid_from')->nullable()->after('waitlist_enabled');
                }
                if (! Schema::hasColumn($tableName, 'valid_until')) {
                    $table->date('valid_until')->nullable()->after('valid_from');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['club_training_groups', 'teams'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach (['valid_until', 'valid_from', 'waitlist_enabled', 'capacity', 'performance_level', 'birth_year_to', 'birth_year_from'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
                if ($tableName === 'club_training_groups' && Schema::hasColumn($tableName, 'sport_year_period_id')) {
                    $table->dropConstrainedForeignId('sport_year_period_id');
                }
            });
        }
    }
};
