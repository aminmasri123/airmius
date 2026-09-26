<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_products', 'teamwear_supplier')) {
                $table->string('teamwear_supplier')->nullable()->after('variants');
            }

            if (! Schema::hasColumn('marketplace_products', 'teamwear_funded_share_cents')) {
                $table->unsignedInteger('teamwear_funded_share_cents')->default(0)->after('teamwear_supplier');
            }

            if (! Schema::hasColumn('marketplace_products', 'teamwear_personalization_rules')) {
                $table->json('teamwear_personalization_rules')->nullable()->after('teamwear_funded_share_cents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table): void {
            foreach (['teamwear_personalization_rules', 'teamwear_funded_share_cents', 'teamwear_supplier'] as $column) {
                if (Schema::hasColumn('marketplace_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
