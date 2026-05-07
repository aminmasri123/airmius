<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            if (! Schema::hasColumn('rides', 'pickup_name')) {
                $table->string('pickup_name')->nullable()->after('to');
            }

            if (! Schema::hasColumn('rides', 'pickup_street')) {
                $table->string('pickup_street')->nullable()->after('pickup_name');
            }

            if (! Schema::hasColumn('rides', 'pickup_house_number')) {
                $table->string('pickup_house_number', 40)->nullable()->after('pickup_street');
            }

            if (! Schema::hasColumn('rides', 'pickup_postal_code')) {
                $table->string('pickup_postal_code', 30)->nullable()->after('pickup_house_number');
            }

            if (! Schema::hasColumn('rides', 'pickup_city')) {
                $table->string('pickup_city')->nullable()->after('pickup_postal_code');
            }

            if (! Schema::hasColumn('rides', 'pickup_country')) {
                $table->string('pickup_country', 2)->nullable()->after('pickup_city');
            }

            if (! Schema::hasColumn('rides', 'pickup_note')) {
                $table->string('pickup_note', 500)->nullable()->after('pickup_country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            foreach ([
                'pickup_note',
                'pickup_country',
                'pickup_city',
                'pickup_postal_code',
                'pickup_house_number',
                'pickup_street',
                'pickup_name',
            ] as $column) {
                if (Schema::hasColumn('rides', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
