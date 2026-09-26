<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('brand_primary_color', 7)->nullable()->after('cover_image');
            $table->string('brand_secondary_color', 7)->nullable()->after('brand_primary_color');
            $table->string('brand_accent_color', 7)->nullable()->after('brand_secondary_color');
            $table->json('letterhead_settings')->nullable()->after('brand_accent_color');
            $table->json('document_templates')->nullable()->after('letterhead_settings');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn([
                'brand_primary_color',
                'brand_secondary_color',
                'brand_accent_color',
                'letterhead_settings',
                'document_templates',
            ]);
        });
    }
};
