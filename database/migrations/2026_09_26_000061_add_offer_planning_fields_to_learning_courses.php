<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_courses', function (Blueprint $table): void {
            if (! Schema::hasColumn('learning_courses', 'offer_type')) {
                $table->string('offer_type', 40)->default('course')->after('category')->index();
            }

            if (! Schema::hasColumn('learning_courses', 'capacity')) {
                $table->unsignedInteger('capacity')->nullable()->after('price_cents');
            }

            if (! Schema::hasColumn('learning_courses', 'registration_deadline_at')) {
                $table->timestamp('registration_deadline_at')->nullable()->after('capacity');
            }

            if (! Schema::hasColumn('learning_courses', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('registration_deadline_at');
            }

            if (! Schema::hasColumn('learning_courses', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('starts_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('learning_courses', function (Blueprint $table): void {
            foreach (['ends_at', 'starts_at', 'registration_deadline_at', 'capacity', 'offer_type'] as $column) {
                if (Schema::hasColumn('learning_courses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
