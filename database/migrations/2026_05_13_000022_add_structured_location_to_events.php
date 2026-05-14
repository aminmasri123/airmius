<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            foreach ([
                'location_name' => fn () => $table->string('location_name')->nullable()->after('location'),
                'location_street' => fn () => $table->string('location_street')->nullable()->after('location_name'),
                'location_house_number' => fn () => $table->string('location_house_number', 40)->nullable()->after('location_street'),
                'location_postal_code' => fn () => $table->string('location_postal_code', 20)->nullable()->after('location_house_number')->index(),
                'location_city' => fn () => $table->string('location_city')->nullable()->after('location_postal_code')->index(),
                'location_country' => fn () => $table->string('location_country', 2)->nullable()->after('location_city')->index(),
                'location_latitude' => fn () => $table->decimal('location_latitude', 10, 7)->nullable()->after('location_country'),
                'location_longitude' => fn () => $table->decimal('location_longitude', 10, 7)->nullable()->after('location_latitude'),
            ] as $column => $definition) {
                if (! Schema::hasColumn('events', $column)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            foreach ([
                'location_longitude',
                'location_latitude',
                'location_country',
                'location_city',
                'location_postal_code',
                'location_house_number',
                'location_street',
                'location_name',
            ] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
