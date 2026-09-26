<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_member_timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('type', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('occurred_on');
            $table->string('from_value')->nullable();
            $table->string('to_value')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['club_id', 'subject_type', 'subject_id', 'occurred_on'],
                'club_member_timeline_subject_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_member_timeline_entries');
    }
};
