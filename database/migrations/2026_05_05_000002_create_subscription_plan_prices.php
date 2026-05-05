<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plan_prices')) {
            Schema::create('subscription_plan_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
                $table->string('country_code', 2);
                $table->string('currency', 3)->default('EUR');
                $table->unsignedInteger('monthly_price_cents')->default(0);
                $table->unsignedInteger('yearly_price_cents')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['subscription_plan_id', 'country_code'], 'spp_plan_country_unique');
                $table->index(['country_code', 'is_active'], 'spp_country_active_index');
            });
        } else {
            $this->ensureIndex('spp_plan_country_unique', 'unique', ['subscription_plan_id', 'country_code']);
            $this->ensureIndex('spp_country_active_index', 'index', ['country_code', 'is_active']);
        }

        $now = now();
        $plans = DB::table('subscription_plans')->get(['id', 'monthly_price_cents', 'yearly_price_cents', 'currency']);

        foreach ($plans as $plan) {
            DB::table('subscription_plan_prices')->updateOrInsert([
                'subscription_plan_id' => $plan->id,
                'country_code' => 'DE',
            ], [
                'subscription_plan_id' => $plan->id,
                'country_code' => 'DE',
                'currency' => $plan->currency ?: 'EUR',
                'monthly_price_cents' => $plan->monthly_price_cents,
                'yearly_price_cents' => $plan->yearly_price_cents,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_prices');
    }

    private function ensureIndex(string $name, string $type, array $columns): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'subscription_plan_prices')
            ->where('index_name', $name)
            ->exists();

        if ($exists) {
            return;
        }

        $columnSql = collect($columns)
            ->map(fn (string $column) => '`'.$column.'`')
            ->implode(', ');

        $keyword = $type === 'unique' ? 'unique' : 'index';

        DB::statement("alter table `subscription_plan_prices` add {$keyword} `{$name}` ({$columnSql})");
    }
};
