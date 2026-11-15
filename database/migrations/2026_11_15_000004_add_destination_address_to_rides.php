<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->string('destination_street')->nullable();
            $table->string('destination_house_number', 40)->nullable();
            $table->string('destination_postal_code', 30)->nullable()->index();
            $table->string('destination_city')->nullable();
            $table->string('destination_country', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex(['destination_postal_code']);
            $table->dropColumn(['destination_street', 'destination_house_number', 'destination_postal_code', 'destination_city', 'destination_country']);
        });
    }
};
