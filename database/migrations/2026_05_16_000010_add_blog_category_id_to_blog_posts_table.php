<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('blog_category_id')
                ->nullable()
                ->after('category')
                ->constrained('blog_categories')
                ->nullOnDelete();

            $table->index('blog_category_id');
        });

        DB::table('blog_categories')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function ($category) {
                DB::table('blog_posts')
                    ->where('category', $category->name)
                    ->update(['blog_category_id' => $category->id]);
            });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropForeign(['blog_category_id']);
            $table->dropIndex(['blog_category_id']);
            $table->dropColumn('blog_category_id');
        });
    }
};
