<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_recurrence_series')) {
            Schema::create('event_recurrence_series', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('club_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->string('timezone', 80)->default('UTC');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('active_from')->nullable();
                $table->timestamp('active_until')->nullable();
                $table->unsignedInteger('current_version')->default(1);
                $table->timestamps();
                $table->index(['club_id', 'team_id']);
                $table->index(['active_from', 'active_until']);
            });
        }

        if (! Schema::hasTable('event_recurrence_rule_versions')) {
            Schema::create('event_recurrence_rule_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('series_id')->constrained('event_recurrence_series')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('version');
                $table->string('frequency', 40);
                $table->unsignedSmallInteger('interval')->default(1);
                $table->json('days_of_week')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_until')->nullable();
                $table->json('rule_payload')->nullable();
                $table->timestamps();
                $table->unique(['series_id', 'version']);
                $table->index(['series_id', 'effective_from', 'effective_until']);
            });
        }

        if (! Schema::hasTable('event_recurrence_exceptions')) {
            Schema::create('event_recurrence_exceptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('series_id')->constrained('event_recurrence_series')->cascadeOnDelete();
                $table->foreignId('rule_version_id')->nullable()->constrained('event_recurrence_rule_versions')->nullOnDelete();
                $table->string('kind', 40);
                $table->date('local_date')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('timezone', 80)->nullable();
                $table->string('name')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['series_id', 'kind', 'local_date']);
                $table->index(['series_id', 'starts_at', 'ends_at']);
            });
        }

        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'recurrence_series_id')) {
                $table->foreignId('recurrence_series_id')->nullable()->after('recurring')->constrained('event_recurrence_series')->nullOnDelete();
            }
            if (! Schema::hasColumn('events', 'recurrence_rule_version_id')) {
                $table->foreignId('recurrence_rule_version_id')->nullable()->after('recurrence_series_id')->constrained('event_recurrence_rule_versions')->nullOnDelete();
            }
            if (! Schema::hasColumn('events', 'recurrence_original_start_time')) {
                $table->timestamp('recurrence_original_start_time')->nullable()->after('recurrence_rule_version_id');
            }
            if (! Schema::hasColumn('events', 'recurrence_local_date')) {
                $table->string('recurrence_local_date', 10)->nullable()->after('recurrence_original_start_time');
            }
            if (! Schema::hasColumn('events', 'recurrence_exception_kind')) {
                $table->string('recurrence_exception_kind', 40)->nullable()->after('recurrence_local_date');
            }
            if (! Schema::hasColumn('events', 'recurrence_snapshot')) {
                $table->json('recurrence_snapshot')->nullable()->after('recurrence_exception_kind');
            }
            if (! Schema::hasColumn('events', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            foreach (['recurrence_series_id', 'recurrence_rule_version_id'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['recurrence_original_start_time', 'recurrence_local_date', 'recurrence_exception_kind', 'recurrence_snapshot', 'completed_at'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('event_recurrence_exceptions');
        Schema::dropIfExists('event_recurrence_rule_versions');
        Schema::dropIfExists('event_recurrence_series');
    }
};
