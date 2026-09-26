<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_deliverables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('location')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->date('due_at')->nullable();
            $table->string('status', 30)->default('planned');
            $table->text('fulfillment_evidence')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sponsor_id', 'due_at'], 'sponsor_deliverables_sponsor_due_idx');
            $table->index(['club_id', 'status', 'due_at'], 'sponsor_deliverables_club_status_due_idx');
            $table->index(['responsible_user_id', 'status', 'due_at'], 'sponsor_deliverables_responsible_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_deliverables');
    }
};
