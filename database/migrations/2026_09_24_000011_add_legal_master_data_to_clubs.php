<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('registry_authority', 160)->nullable()->after('official_club_number');
            $table->string('registry_number', 80)->nullable()->after('registry_authority');
            $table->json('federation_affiliations')->nullable()->after('registry_number');
            $table->string('tax_authority', 160)->nullable()->after('federation_affiliations');
            $table->string('tax_number', 80)->nullable()->after('tax_authority');
            $table->string('vat_id', 32)->nullable()->after('tax_number');
            $table->string('tax_status', 24)->nullable()->after('vat_id');
            $table->date('tax_exemption_valid_until')->nullable()->after('tax_status');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn([
                'registry_authority',
                'registry_number',
                'federation_affiliations',
                'tax_authority',
                'tax_number',
                'vat_id',
                'tax_status',
                'tax_exemption_valid_until',
            ]);
        });
    }
};
