<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'account_status')) {
                $table->string('account_status', 30)->default('active')->after('status');
            }

            if (! Schema::hasColumn('users', 'suspended_until')) {
                $table->timestamp('suspended_until')->nullable()->after('account_status');
            }

            if (! Schema::hasColumn('users', 'suspension_reason')) {
                $table->text('suspension_reason')->nullable()->after('suspended_until');
            }
        });

        Schema::table('moderation_flags', function (Blueprint $table) {
            if (! Schema::hasColumn('moderation_flags', 'automated_action')) {
                $table->string('automated_action', 40)->nullable()->after('status');
            }
        });

        Schema::create('account_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderation_flag_id')->nullable()->constrained()->nullOnDelete();
            $table->string('severity', 30);
            $table->unsignedTinyInteger('points')->default(1);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_products', 'moderation_status')) {
                $table->string('moderation_status', 40)->default('approved')->after('status');
            }

            if (! Schema::hasColumn('marketplace_products', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('moderation_status');
            }
        });

        Schema::table('commerce_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_orders', 'issue_status')) {
                $table->string('issue_status', 30)->default('none')->after('status');
            }

            if (! Schema::hasColumn('commerce_orders', 'issue_note')) {
                $table->text('issue_note')->nullable()->after('issue_status');
            }

            if (! Schema::hasColumn('commerce_orders', 'issue_reported_at')) {
                $table->timestamp('issue_reported_at')->nullable()->after('issue_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            foreach (['issue_reported_at', 'issue_note', 'issue_status'] as $column) {
                if (Schema::hasColumn('commerce_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            foreach (['rejection_reason', 'moderation_status'] as $column) {
                if (Schema::hasColumn('marketplace_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('account_warnings');

        Schema::table('moderation_flags', function (Blueprint $table) {
            if (Schema::hasColumn('moderation_flags', 'automated_action')) {
                $table->dropColumn('automated_action');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['suspension_reason', 'suspended_until', 'account_status'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
