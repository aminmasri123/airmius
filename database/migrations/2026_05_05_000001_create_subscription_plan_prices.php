<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('country_code', 2);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('monthly_price_cents')->default(0);
            $table->unsignedInteger('yearly_price_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'country_code']);
            $table->index(['country_code', 'is_active']);
        });

        $now = now();
        $plans = DB::table('subscription_plans')->get(['id', 'monthly_price_cents', 'yearly_price_cents', 'currency']);
        $rows = [];

        foreach ($plans as $plan) {
            $rows[] = [
                'subscription_plan_id' => $plan->id,
                'country_code' => 'DE',
                'currency' => $plan->currency ?: 'EUR',
                'monthly_price_cents' => $plan->monthly_price_cents,
                'yearly_price_cents' => $plan->yearly_price_cents,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('subscription_plan_prices')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_prices');
    }
};
