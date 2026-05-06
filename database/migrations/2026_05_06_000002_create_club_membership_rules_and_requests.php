<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'membership_requests_enabled')) {
                $table->boolean('membership_requests_enabled')->default(true)->after('datev_bank_account');
            }

            if (! Schema::hasColumn('clubs', 'member_pause_requests_enabled')) {
                $table->boolean('member_pause_requests_enabled')->default(false)->after('membership_requests_enabled');
            }
        });

        Schema::create('club_membership_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['club_id', 'slug']);
        });

        Schema::create('club_contribution_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_membership_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('billing_interval', 30)->default('monthly');
            $table->decimal('amount', 10, 2)->default(0);
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->string('factor_key', 80)->nullable();
            $table->string('factor_operator', 20)->nullable();
            $table->string('factor_value', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'valid_from', 'valid_until']);
        });

        Schema::create('club_membership_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_membership_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30)->default('membership');
            $table->string('status', 30)->default('pending');
            $table->text('message')->nullable();
            $table->date('requested_pause_from')->nullable();
            $table->date('requested_pause_until')->nullable();
            $table->decimal('preview_amount', 10, 2)->nullable();
            $table->string('preview_interval', 30)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status', 'type']);
        });

        Schema::table('club_user', function (Blueprint $table) {
            if (! Schema::hasColumn('club_user', 'club_membership_type_id')) {
                $table->foreignId('club_membership_type_id')->nullable()->after('membership_status')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('club_user', 'pause_requested_at')) {
                $table->timestamp('pause_requested_at')->nullable()->after('membership_ends_on');
            }

            if (! Schema::hasColumn('club_user', 'paused_from')) {
                $table->date('paused_from')->nullable()->after('pause_requested_at');
            }

            if (! Schema::hasColumn('club_user', 'paused_until')) {
                $table->date('paused_until')->nullable()->after('paused_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            foreach (['paused_until', 'paused_from', 'pause_requested_at'] as $column) {
                if (Schema::hasColumn('club_user', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('club_user', 'club_membership_type_id')) {
                $table->dropConstrainedForeignId('club_membership_type_id');
            }
        });

        Schema::dropIfExists('club_membership_requests');
        Schema::dropIfExists('club_contribution_rules');
        Schema::dropIfExists('club_membership_types');

        Schema::table('clubs', function (Blueprint $table) {
            foreach (['member_pause_requests_enabled', 'membership_requests_enabled'] as $column) {
                if (Schema::hasColumn('clubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
