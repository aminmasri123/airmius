<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_policy_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('title', 160);
            $table->string('version_label', 80);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_public')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['club_id', 'type', 'title', 'version_label'],
                'club_policy_documents_version_unique'
            );
            $table->index(
                ['club_id', 'type', 'title', 'valid_from', 'valid_until'],
                'club_policy_documents_validity_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_policy_documents');
    }
};
