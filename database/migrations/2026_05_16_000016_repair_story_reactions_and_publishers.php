<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stories')) {
            Schema::table('stories', function (Blueprint $table) {
                if (! Schema::hasColumn('stories', 'publisher_type')) {
                    $table->string('publisher_type', 20)->default('user')->after('team_id');
                }

                if (! Schema::hasColumn('stories', 'publisher_id')) {
                    $table->unsignedBigInteger('publisher_id')->nullable()->after('publisher_type');
                }
            });
        }

        if (! Schema::hasTable('story_reactions')) {
            Schema::create('story_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('reaction', 20);
                $table->timestamps();

                $table->unique(['story_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('story_reactions');
    }
};
