<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('outfit_subscriptions', 'accepted_terms_at')) {
                $table->timestamp('accepted_terms_at')->nullable()->after('payment_payload');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'accepted_contract_at')) {
                $table->timestamp('accepted_contract_at')->nullable()->after('accepted_terms_at');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'contract_version')) {
                $table->string('contract_version')->nullable()->after('accepted_contract_at');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'contract_snapshot')) {
                $table->json('contract_snapshot')->nullable()->after('contract_version');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'accepted_ip')) {
                $table->string('accepted_ip', 64)->nullable()->after('contract_snapshot');
            }

            if (! Schema::hasColumn('outfit_subscriptions', 'accepted_user_agent')) {
                $table->text('accepted_user_agent')->nullable()->after('accepted_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outfit_subscriptions', function (Blueprint $table) {
            foreach ([
                'accepted_user_agent',
                'accepted_ip',
                'contract_snapshot',
                'contract_version',
                'accepted_contract_at',
                'accepted_terms_at',
            ] as $column) {
                if (Schema::hasColumn('outfit_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
