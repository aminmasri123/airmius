<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('placement', 40)->default('feed');
            $table->json('audience')->nullable();
            $table->unsignedInteger('daily_budget_cents')->default(0);
            $table->string('status', 30)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['ad_campaign_id', 'status']);
            $table->index(['placement', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_groups');
    }
};
