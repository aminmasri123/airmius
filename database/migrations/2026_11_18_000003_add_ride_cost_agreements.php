<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->string('cost_mode', 20)->nullable();
            $table->unsignedBigInteger('cost_amount_minor')->nullable();
            $table->char('cost_currency', 3)->nullable();
            $table->unsignedInteger('price_version')->default(0);
        });
        Schema::table('ride_users', function (Blueprint $table) {
            $table->string('agreed_cost_mode', 20)->nullable();
            $table->unsignedBigInteger('agreed_cost_amount_minor')->nullable();
            $table->char('agreed_cost_currency', 3)->nullable();
            $table->unsignedInteger('agreed_price_version')->nullable();
            $table->boolean('price_confirmation_required')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ride_users', fn (Blueprint $table) => $table->dropColumn(['agreed_cost_mode', 'agreed_cost_amount_minor', 'agreed_cost_currency', 'agreed_price_version', 'price_confirmation_required']));
        Schema::table('rides', fn (Blueprint $table) => $table->dropColumn(['cost_mode', 'cost_amount_minor', 'cost_currency', 'price_version']));
    }
};
