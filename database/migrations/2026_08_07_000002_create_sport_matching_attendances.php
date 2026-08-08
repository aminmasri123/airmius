<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sport_matching_attendances')) {
            return;
        }

        Schema::create('sport_matching_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_matching_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_matching_application_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('no_show_reported_at')->nullable();
            $table->foreignId('no_show_reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('no_show_reason')->nullable();
            $table->timestamps();

            $table->unique(['sport_matching_id', 'user_id'], 'sport_matching_attendance_unique');
            $table->index(['sport_matching_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_matching_attendances');
    }
};
