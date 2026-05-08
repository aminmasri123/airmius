<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_tax_rates')) {
            Schema::create('commerce_tax_rates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('country_code', 2)->index();
                $table->string('region', 80)->nullable();
                $table->string('tax_label', 40)->default('MwSt.');
                $table->decimal('rate_percent', 5, 2)->default(19);
                $table->string('currency', 3)->default('EUR');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('priority')->default(100);
                $table->timestamps();

                $table->index(['is_active', 'country_code', 'priority'], 'commerce_tax_active_country_priority_idx');
            });
        }

        if (! Schema::hasTable('commerce_shipping_rates')) {
            Schema::create('commerce_shipping_rates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('country_code', 2)->nullable()->index();
                $table->string('postal_code_prefix', 20)->nullable();
                $table->unsignedInteger('amount_cents')->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->unsignedInteger('free_from_cents')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('priority')->default(100);
                $table->timestamps();

                $table->index(['is_active', 'country_code', 'postal_code_prefix', 'priority'], 'commerce_shipping_match_idx');
            });
        }

        if (DB::table('commerce_tax_rates')->count() === 0) {
            DB::table('commerce_tax_rates')->insert([
            [
                'name' => 'Deutschland Standard',
                'country_code' => 'DE',
                'tax_label' => 'MwSt.',
                'rate_percent' => 19,
                'currency' => 'EUR',
                'is_default' => true,
                'is_active' => true,
                'priority' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ]);
        }

        if (DB::table('commerce_shipping_rates')->count() === 0) {
            DB::table('commerce_shipping_rates')->insert([
            [
                'name' => 'Deutschland Standardversand',
                'country_code' => 'DE',
                'amount_cents' => 490,
                'currency' => 'EUR',
                'free_from_cents' => 10000,
                'is_active' => true,
                'priority' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Digitale Angebote / keine Versandkosten',
                'country_code' => null,
                'amount_cents' => 0,
                'currency' => 'EUR',
                'free_from_cents' => null,
                'is_active' => true,
                'priority' => 999,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_shipping_rates');
        Schema::dropIfExists('commerce_tax_rates');
    }
};
