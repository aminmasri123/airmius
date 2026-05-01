<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'country')) {
                $table->string('country', 2)->nullable()->after('theme');
            }
            if (! Schema::hasColumn('users', 'street')) {
                $table->string('street')->nullable()->after('country');
            }
            if (! Schema::hasColumn('users', 'house_number')) {
                $table->string('house_number', 40)->nullable()->after('street');
            }
            if (! Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 30)->nullable()->after('house_number');
            }
            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable()->after('postal_code');
            }
            if (! Schema::hasColumn('users', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'sport_type')) {
                $table->string('sport_type', 80)->nullable()->after('name');
            }
            if (! Schema::hasColumn('clubs', 'country')) {
                $table->string('country', 2)->nullable()->after('cover_image');
            }
            if (! Schema::hasColumn('clubs', 'street')) {
                $table->string('street')->nullable()->after('country');
            }
            if (! Schema::hasColumn('clubs', 'house_number')) {
                $table->string('house_number', 40)->nullable()->after('street');
            }
            if (! Schema::hasColumn('clubs', 'postal_code')) {
                $table->string('postal_code', 30)->nullable()->after('house_number');
            }
            if (! Schema::hasColumn('clubs', 'city')) {
                $table->string('city')->nullable()->after('postal_code');
            }
            if (! Schema::hasColumn('clubs', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            foreach (['state', 'city', 'postal_code', 'house_number', 'street', 'country', 'sport_type'] as $column) {
                if (Schema::hasColumn('clubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['state', 'city', 'postal_code', 'house_number', 'street', 'country'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
