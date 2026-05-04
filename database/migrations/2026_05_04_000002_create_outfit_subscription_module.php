<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfit_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('monthly_price_cents')->default(0);
            $table->unsignedInteger('sponsor_discount_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('target_gender')->nullable();
            $table->json('sizes')->nullable();
            $table->json('sports')->nullable();
            $table->unsignedTinyInteger('items_per_box')->default(3);
            $table->string('branding_type')->default('none');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('outfit_style_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sport_focus')->nullable();
            $table->json('sizes')->nullable();
            $table->string('fit_preference')->nullable();
            $table->json('colors')->nullable();
            $table->json('excluded_colors')->nullable();
            $table->string('brand_style')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('outfit_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outfit_subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->unsignedInteger('monthly_price_cents')->default(0);
            $table->unsignedInteger('sponsor_discount_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('next_delivery_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('outfit_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outfit_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('planned');
            $table->date('delivery_month')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outfit_deliveries');
        Schema::dropIfExists('outfit_subscriptions');
        Schema::dropIfExists('outfit_style_profiles');
        Schema::dropIfExists('outfit_subscription_plans');
    }
};
