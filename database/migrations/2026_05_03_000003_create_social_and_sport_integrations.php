<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index(['user_id', 'provider']);
        });

        Schema::create('connected_sport_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_user_id')->nullable();
            $table->string('display_name')->nullable();
            $table->string('status', 30)->default('requested');
            $table->json('scopes')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_summary')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['provider', 'status']);
        });

        Schema::create('connected_sport_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connected_sport_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 50);
            $table->string('provider_activity_id')->nullable();
            $table->string('activity_type')->nullable();
            $table->string('title')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedInteger('calories')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_activity_id']);
            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_sport_activities');
        Schema::dropIfExists('connected_sport_accounts');
        Schema::dropIfExists('social_accounts');
    }
};
