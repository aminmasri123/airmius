<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('marketplace_provider_locations');
        Schema::dropIfExists('marketplace_provider_profiles');

        Schema::create('marketplace_provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('provider_type', 30)->default('private');
            $table->string('support_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website', 500)->nullable();
            $table->string('logo_url', 1000)->nullable();
            $table->text('public_description')->nullable();
            $table->string('legal_country', 2)->nullable();
            $table->string('legal_state')->nullable();
            $table->string('legal_postal_code', 30)->nullable();
            $table->string('legal_city')->nullable();
            $table->string('legal_street')->nullable();
            $table->string('legal_house_number', 40)->nullable();
            $table->boolean('show_public_address')->default(false);
            $table->boolean('show_support_email')->default(true);
            $table->boolean('show_phone')->default(false);
            $table->string('status', 30)->default('draft');
            $table->timestamps();
        });

        Schema::create('marketplace_provider_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_provider_profile_id');
            $table->string('name');
            $table->string('type', 30)->default('pickup');
            $table->string('country', 2)->default('DE');
            $table->string('state')->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('city')->nullable();
            $table->string('street')->nullable();
            $table->string('house_number', 40)->nullable();
            $table->text('opening_hours')->nullable();
            $table->text('note')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('pickup_enabled')->default(true);
            $table->boolean('returns_enabled')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['marketplace_provider_profile_id', 'is_public'], 'mp_locations_profile_public_idx');
            $table->index(['country', 'city'], 'mp_locations_country_city_idx');
            $table
                ->foreign('marketplace_provider_profile_id', 'mp_locations_profile_fk')
                ->references('id')
                ->on('marketplace_provider_profiles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_provider_locations');
        Schema::dropIfExists('marketplace_provider_profiles');
    }
};
