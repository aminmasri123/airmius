<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_holder')->nullable();
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('paypal_email')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('gross_cents')->default(0);
            $table->unsignedInteger('commission_cents')->default(0);
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('method', 30)->default('bank_transfer');
            $table->string('status', 30)->default('prepared');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('commerce_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_orders', 'payout_id')) {
                $table->foreignId('payout_id')->nullable()->after('commission_cents')->constrained('marketplace_payouts')->nullOnDelete();
            }

            if (! Schema::hasColumn('commerce_orders', 'payout_status')) {
                $table->string('payout_status', 30)->default('not_applicable')->after('payout_id');
            }
        });

        DB::table('commerce_orders')
            ->where('type', 'marketplace_product')
            ->where('status', 'completed')
            ->where('payout_status', 'not_applicable')
            ->update(['payout_status' => 'pending']);

        DB::table('marketplace_products')
            ->where('status', 'published')
            ->where('payout_status', 'not_applicable')
            ->update(['payout_status' => 'pending_sales']);
    }

    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_orders', 'payout_id')) {
                $table->dropConstrainedForeignId('payout_id');
            }

            if (Schema::hasColumn('commerce_orders', 'payout_status')) {
                $table->dropColumn('payout_status');
            }
        });

        Schema::dropIfExists('marketplace_payouts');
        Schema::dropIfExists('payout_profiles');
    }
};
