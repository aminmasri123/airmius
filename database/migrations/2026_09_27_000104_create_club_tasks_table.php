<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['club_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_tasks');
    }
};
