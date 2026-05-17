<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_courses', function (Blueprint $table) {
            if (! Schema::hasColumn('learning_courses', 'sales_points')) {
                $table->json('sales_points')->nullable()->after('target_groups');
            }
            if (! Schema::hasColumn('learning_courses', 'faq_items')) {
                $table->json('faq_items')->nullable()->after('sales_points');
            }
            if (! Schema::hasColumn('learning_courses', 'guarantee_text')) {
                $table->text('guarantee_text')->nullable()->after('faq_items');
            }
            if (! Schema::hasColumn('learning_courses', 'certificate_logo_url')) {
                $table->string('certificate_logo_url', 500)->nullable()->after('guarantee_text');
            }
            if (! Schema::hasColumn('learning_courses', 'certificate_signature_name')) {
                $table->string('certificate_signature_name')->nullable()->after('certificate_logo_url');
            }
            if (! Schema::hasColumn('learning_courses', 'certificate_footer_text')) {
                $table->text('certificate_footer_text')->nullable()->after('certificate_signature_name');
            }
            if (! Schema::hasColumn('learning_courses', 'quality_status')) {
                $table->string('quality_status')->default('pending')->after('status');
            }
            if (! Schema::hasColumn('learning_courses', 'quality_note')) {
                $table->text('quality_note')->nullable()->after('quality_status');
            }
            if (! Schema::hasColumn('learning_courses', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('quality_note')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('learning_courses', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('learning_courses', 'featured_at')) {
                $table->timestamp('featured_at')->nullable()->after('reviewed_at');
            }
        });

        if (! Schema::hasTable('learning_coupons')) {
            Schema::create('learning_coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_course_id')->constrained()->cascadeOnDelete();
                $table->string('code');
                $table->string('discount_type')->default('percent');
                $table->unsignedInteger('discount_value');
                $table->unsignedInteger('max_redemptions')->nullable();
                $table->unsignedInteger('redeemed_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['learning_course_id', 'code'], 'learning_coupons_course_code_unique');
            });
        } else {
            $this->ensureIndex('learning_coupons', 'learning_coupons_course_code_unique', 'unique', ['learning_course_id', 'code']);
        }

        if (! Schema::hasTable('learning_assignments')) {
            Schema::create('learning_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('learning_lesson_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title');
                $table->text('instructions')->nullable();
                $table->unsignedInteger('points')->default(100);
                $table->unsignedInteger('due_after_days')->nullable();
                $table->boolean('is_required')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('learning_assignment_submissions')) {
            Schema::create('learning_assignment_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_assignment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('learning_enrollment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('body')->nullable();
                $table->string('attachment_url', 500)->nullable();
                $table->string('status')->default('submitted');
                $table->unsignedInteger('score')->nullable();
                $table->text('feedback')->nullable();
                $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('graded_at')->nullable();
                $table->timestamps();
                $table->unique(['learning_assignment_id', 'learning_enrollment_id'], 'learning_submission_assignment_enrollment_unique');
            });
        } else {
            $this->ensureIndex('learning_assignment_submissions', 'learning_submission_assignment_enrollment_unique', 'unique', ['learning_assignment_id', 'learning_enrollment_id']);
        }

        if (! Schema::hasTable('learning_email_deliveries')) {
            Schema::create('learning_email_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('learning_enrollment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('learning_lesson_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('type');
                $table->timestamp('sent_at');
                $table->timestamps();
                $table->unique(['learning_enrollment_id', 'learning_lesson_id', 'type'], 'learning_email_unique_delivery');
            });
        } else {
            $this->ensureIndex('learning_email_deliveries', 'learning_email_unique_delivery', 'unique', ['learning_enrollment_id', 'learning_lesson_id', 'type']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_email_deliveries');
        Schema::dropIfExists('learning_assignment_submissions');
        Schema::dropIfExists('learning_assignments');
        Schema::dropIfExists('learning_coupons');

        Schema::table('learning_courses', function (Blueprint $table) {
            if (Schema::hasColumn('learning_courses', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
            }

            foreach ([
                'sales_points',
                'faq_items',
                'guarantee_text',
                'certificate_logo_url',
                'certificate_signature_name',
                'certificate_footer_text',
                'quality_status',
                'quality_note',
                'reviewed_by',
                'reviewed_at',
                'featured_at',
            ] as $column) {
                if (Schema::hasColumn('learning_courses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function ensureIndex(string $table, string $name, string $type, array $columns): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        $columnSql = collect($columns)
            ->map(fn (string $column) => '`'.$column.'`')
            ->implode(', ');

        $keyword = $type === 'unique' ? 'unique' : 'index';

        DB::statement("alter table `{$table}` add {$keyword} `{$name}` ({$columnSql})");
    }

    private function indexExists(string $table, string $name): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $name)
                ->exists();
        }

        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $name);
    }
};
