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
                $table->foreignId('club_id')->nullable()->constrained(indexName: 'event_recur_series_club_fk')->cascadeOnDelete();
                $table->foreignId('team_id')->nullable()->constrained(indexName: 'event_recur_series_team_fk')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'event_recur_series_creator_fk')->nullOnDelete();
                $table->string('title');
                $table->string('timezone', 80)->default('UTC');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('active_from')->nullable();
                $table->timestamp('active_until')->nullable();
                $table->unsignedInteger('current_version')->default(1);
                $table->timestamps();
                $table->index(['club_id', 'team_id'], 'event_recur_series_club_team_idx');
                $table->index(['active_from', 'active_until'], 'event_recur_series_active_window_idx');
            });
        }

        if (! Schema::hasTable('event_recurrence_rule_versions')) {
            Schema::create('event_recurrence_rule_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('series_id')->constrained('event_recurrence_series', indexName: 'event_recur_versions_series_fk')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'event_recur_versions_creator_fk')->nullOnDelete();
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
                $table->unique(['series_id', 'version'], 'event_recur_versions_series_version_unique');
                $table->index(['series_id', 'effective_from', 'effective_until'], 'event_recur_versions_effective_idx');
            });
        }

        if (! Schema::hasTable('event_recurrence_exceptions')) {
            Schema::create('event_recurrence_exceptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('series_id')->constrained('event_recurrence_series', indexName: 'event_recur_exceptions_series_fk')->cascadeOnDelete();
                $table->foreignId('rule_version_id')->nullable()->constrained('event_recurrence_rule_versions', indexName: 'event_recur_exceptions_version_fk')->nullOnDelete();
                $table->string('kind', 40);
                $table->date('local_date')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('timezone', 80)->nullable();
                $table->string('name')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['series_id', 'kind', 'local_date'], 'event_recur_exceptions_kind_date_idx');
                $table->index(['series_id', 'starts_at', 'ends_at'], 'event_recur_exceptions_window_idx');
            });
        }

        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'recurrence_series_id')) {
                $table->foreignId('recurrence_series_id')->nullable()->after('recurring')->constrained('event_recurrence_series', indexName: 'events_recur_series_fk')->nullOnDelete();
            }
            if (! Schema::hasColumn('events', 'recurrence_rule_version_id')) {
                $table->foreignId('recurrence_rule_version_id')->nullable()->after('recurrence_series_id')->constrained('event_recurrence_rule_versions', indexName: 'events_recur_version_fk')->nullOnDelete();
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
