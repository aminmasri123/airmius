<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('status', 30)->default('pending');
            $table->text('message')->nullable();
            $table->boolean('role_activated')->default(false);
            $table->text('review_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['user_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_applications');
    }
};
