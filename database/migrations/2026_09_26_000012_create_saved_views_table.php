<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('workspace', 40);
            $table->string('name', 80);
            $table->json('configuration');
            $table->boolean('is_favorite')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'workspace', 'name']);
            $table->index(['user_id', 'workspace', 'is_favorite', 'sort_order'], 'saved_views_owner_workspace_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
