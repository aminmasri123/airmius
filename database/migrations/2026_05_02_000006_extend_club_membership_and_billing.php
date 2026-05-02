<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            if (! Schema::hasColumn('club_user', 'membership_status')) {
                $table->string('membership_status', 30)->default('non_member')->after('role');
            }

            if (! Schema::hasColumn('club_user', 'member_number')) {
                $table->string('member_number', 80)->nullable()->after('membership_status');
            }

            if (! Schema::hasColumn('club_user', 'contribution_amount')) {
                $table->decimal('contribution_amount', 10, 2)->nullable()->after('member_number');
            }

            if (! Schema::hasColumn('club_user', 'contribution_interval')) {
                $table->string('contribution_interval', 30)->nullable()->after('contribution_amount');
            }

            if (! Schema::hasColumn('club_user', 'joined_on')) {
                $table->date('joined_on')->nullable()->after('contribution_interval');
            }

            if (! Schema::hasColumn('club_user', 'membership_notes')) {
                $table->text('membership_notes')->nullable()->after('joined_on');
            }
        });

        DB::table('club_user')
            ->whereNull('membership_status')
            ->update(['membership_status' => 'non_member']);

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('club_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('invoices', 'title')) {
                $table->string('title')->nullable()->after('number');
            }

            if (! Schema::hasColumn('invoices', 'description')) {
                $table->text('description')->nullable()->after('title');
            }

            if (! Schema::hasColumn('invoices', 'status')) {
                $table->string('status', 30)->default('open')->after('amount');
            }

            if (! Schema::hasColumn('invoices', 'issued_at')) {
                $table->timestamp('issued_at')->nullable()->after('due_date');
            }

            if (! Schema::hasColumn('invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('issued_at');
            }

            if (! Schema::hasColumn('invoices', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('paid_at');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('payments', 'method')) {
                $table->string('method', 60)->nullable()->after('status');
            }

            if (! Schema::hasColumn('payments', 'reference')) {
                $table->string('reference')->nullable()->after('method');
            }

            if (! Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('reference');
            }

            if (! Schema::hasColumn('payments', 'notes')) {
                $table->text('notes')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            foreach (['notes', 'paid_at', 'reference', 'method'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('payments', 'invoice_id')) {
                $table->dropConstrainedForeignId('invoice_id');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            foreach (['reminder_sent_at', 'paid_at', 'issued_at', 'status', 'description', 'title'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('invoices', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });

        Schema::table('club_user', function (Blueprint $table) {
            foreach (['membership_notes', 'joined_on', 'contribution_interval', 'contribution_amount', 'member_number', 'membership_status'] as $column) {
                if (Schema::hasColumn('club_user', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
