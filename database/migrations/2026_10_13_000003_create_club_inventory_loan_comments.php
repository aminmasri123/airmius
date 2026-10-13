<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_inventory_loan_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_inventory_loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
            $table->index(['club_inventory_loan_id', 'created_at'], 'inventory_loan_comment_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_inventory_loan_comments');
    }
};
