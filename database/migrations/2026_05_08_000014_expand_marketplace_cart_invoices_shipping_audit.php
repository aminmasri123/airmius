<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_carts')) {
            Schema::create('commerce_carts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('currency', 3)->default('EUR');
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->unique('user_id');
            });
        }

        if (! Schema::hasTable('commerce_cart_items')) {
            Schema::create('commerce_cart_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commerce_cart_id')->constrained()->cascadeOnDelete();
                $table->foreignId('marketplace_product_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->timestamps();

                $table->unique(['commerce_cart_id', 'marketplace_product_id'], 'commerce_cart_product_unique');
            });
        }

        if (! Schema::hasTable('commerce_audit_logs')) {
            Schema::create('commerce_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->nullableMorphs('auditable');
                $table->string('action', 80);
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['action', 'created_at']);
            });
        }

        Schema::table('commerce_orders', function (Blueprint $table) {
            foreach ([
                'invoice_number' => fn () => $table->string('invoice_number')->nullable()->after('payment_reference'),
                'credit_note_number' => fn () => $table->string('credit_note_number')->nullable()->after('invoice_number'),
                'shipping_status' => fn () => $table->string('shipping_status', 30)->default('open')->after('status'),
                'shipping_carrier' => fn () => $table->string('shipping_carrier', 80)->nullable()->after('shipping_status'),
                'shipping_label_url' => fn () => $table->text('shipping_label_url')->nullable()->after('shipping_carrier'),
                'tracking_number' => fn () => $table->string('tracking_number')->nullable()->after('shipping_label_url'),
                'tracking_url' => fn () => $table->text('tracking_url')->nullable()->after('tracking_number'),
                'shipped_at' => fn () => $table->timestamp('shipped_at')->nullable()->after('tracking_url'),
                'delivered_at' => fn () => $table->timestamp('delivered_at')->nullable()->after('shipped_at'),
                'refunded_cents' => fn () => $table->unsignedInteger('refunded_cents')->default(0)->after('amount_cents'),
                'refund_provider_id' => fn () => $table->string('refund_provider_id')->nullable()->after('provider_checkout_id'),
                'customer_vat_is_valid' => fn () => $table->boolean('customer_vat_is_valid')->nullable()->after('customer_vat_id'),
                'customer_vat_validated_at' => fn () => $table->timestamp('customer_vat_validated_at')->nullable()->after('customer_vat_is_valid'),
            ] as $column => $definition) {
                if (! Schema::hasColumn('commerce_orders', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            foreach ([
                'return_policy_type' => fn () => $table->string('return_policy_type', 40)->default('standard')->after('tax_class'),
                'return_window_days' => fn () => $table->unsignedInteger('return_window_days')->default(14)->after('return_policy_type'),
                'low_stock_threshold' => fn () => $table->unsignedInteger('low_stock_threshold')->default(0)->after('stock_quantity'),
            ] as $column => $definition) {
                if (! Schema::hasColumn('marketplace_products', $column)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            foreach (['return_policy_type', 'return_window_days', 'low_stock_threshold'] as $column) {
                if (Schema::hasColumn('marketplace_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('commerce_orders', function (Blueprint $table) {
            foreach ([
                'invoice_number',
                'credit_note_number',
                'shipping_status',
                'shipping_carrier',
                'shipping_label_url',
                'tracking_number',
                'tracking_url',
                'shipped_at',
                'delivered_at',
                'refunded_cents',
                'refund_provider_id',
                'customer_vat_is_valid',
                'customer_vat_validated_at',
            ] as $column) {
                if (Schema::hasColumn('commerce_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('commerce_audit_logs');
        Schema::dropIfExists('commerce_cart_items');
        Schema::dropIfExists('commerce_carts');
    }
};
