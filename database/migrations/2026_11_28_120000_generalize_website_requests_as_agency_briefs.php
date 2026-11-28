<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_requests', function (Blueprint $table) {
            $table->string('request_type', 40)->default('website')->after('package');
            $table->json('deliverables')->nullable()->after('goals');
            $table->json('channels')->nullable()->after('deliverables');
            $table->string('budget_range', 80)->nullable()->after('channels');
            $table->date('desired_deadline')->nullable()->after('budget_range');
            $table->text('brand_guidelines')->nullable()->after('desired_deadline');
            $table->string('contact_preference', 30)->nullable()->after('brand_guidelines');
        });
    }

    public function down(): void
    {
        Schema::table('website_requests', fn (Blueprint $table) => $table->dropColumn([
            'request_type', 'deliverables', 'channels', 'budget_range',
            'desired_deadline', 'brand_guidelines', 'contact_preference',
        ]));
    }
};
