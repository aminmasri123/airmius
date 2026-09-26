<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('state');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
            $table->string('website_url', 500)->nullable()->after('contact_phone');
            $table->boolean('contact_details_public')->default(false)->after('website_url');
            $table->json('contact_persons')->nullable()->after('contact_details_public');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn([
                'contact_email',
                'contact_phone',
                'website_url',
                'contact_details_public',
                'contact_persons',
            ]);
        });
    }
};
