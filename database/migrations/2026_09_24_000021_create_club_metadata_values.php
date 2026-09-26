<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_custom_field_definition_id')
                ->constrained('club_custom_field_definitions')->restrictOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->json('payload');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['club_custom_field_definition_id', 'subject_type', 'subject_id'],
                'club_custom_field_values_subject_unique'
            );
            $table->index(['club_id', 'subject_type', 'subject_id'], 'club_custom_field_values_subject_lookup');
        });

        Schema::create('club_category_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_category_id')->constrained('club_categories')->restrictOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['club_category_id', 'subject_type', 'subject_id'],
                'club_category_assignments_subject_unique'
            );
            $table->index(['club_id', 'subject_type', 'subject_id'], 'club_category_assignments_subject_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_category_assignments');
        Schema::dropIfExists('club_custom_field_values');
    }
};
