<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('marketplace_products', 'marketplace_public_catalog_idx')) {
            Schema::table('marketplace_products', function (Blueprint $table): void {
                $table->index(
                    ['status', 'moderation_status', 'category', 'id'],
                    'marketplace_public_catalog_idx',
                );
            });
        }

        if (! Schema::hasIndex('marketplace_products', 'marketplace_public_price_idx')) {
            Schema::table('marketplace_products', function (Blueprint $table): void {
                $table->index(
                    ['status', 'moderation_status', 'price_cents', 'id'],
                    'marketplace_public_price_idx',
                );
            });
        }

        if (! Schema::hasIndex('learning_courses', 'learning_public_catalog_idx')) {
            Schema::table('learning_courses', function (Blueprint $table): void {
                $table->index(
                    ['status', 'is_public', 'featured_at', 'published_at', 'id'],
                    'learning_public_catalog_idx',
                );
            });
        }

        if (! Schema::hasIndex('learning_courses', 'learning_public_filter_idx')) {
            Schema::table('learning_courses', function (Blueprint $table): void {
                $table->index(
                    ['status', 'is_public', 'category', 'level', 'is_free'],
                    'learning_public_filter_idx',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('learning_courses', 'learning_public_filter_idx')) {
            Schema::table('learning_courses', fn (Blueprint $table) => $table->dropIndex('learning_public_filter_idx'));
        }

        if (Schema::hasIndex('learning_courses', 'learning_public_catalog_idx')) {
            Schema::table('learning_courses', fn (Blueprint $table) => $table->dropIndex('learning_public_catalog_idx'));
        }

        if (Schema::hasIndex('marketplace_products', 'marketplace_public_price_idx')) {
            Schema::table('marketplace_products', fn (Blueprint $table) => $table->dropIndex('marketplace_public_price_idx'));
        }

        if (Schema::hasIndex('marketplace_products', 'marketplace_public_catalog_idx')) {
            Schema::table('marketplace_products', fn (Blueprint $table) => $table->dropIndex('marketplace_public_catalog_idx'));
        }
    }
};
