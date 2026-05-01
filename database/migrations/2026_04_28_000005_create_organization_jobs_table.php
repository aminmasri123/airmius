<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['volunteer', 'professional'])->default('volunteer');
            $table->string('location')->nullable();
            $table->string('workload')->nullable();
            $table->string('employment_type')->nullable();
            $table->text('description');
            $table->string('contact_email')->nullable();
            $table->string('application_url')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
            $table->index(['club_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_jobs');
    }
};
