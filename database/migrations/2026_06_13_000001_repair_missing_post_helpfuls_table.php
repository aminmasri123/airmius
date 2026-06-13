<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->postHelpfulsTableIsUsable()) {
            return;
        }

        try {
            Schema::dropIfExists('post_helpfuls');
        } catch (Throwable) {
            //
        }

        Schema::create('post_helpfuls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context')->default('helpful');
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_helpfuls');
    }

    private function postHelpfulsTableIsUsable(): bool
    {
        try {
            if (! Schema::hasTable('post_helpfuls')) {
                return false;
            }

            DB::table('post_helpfuls')->limit(1)->count();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
