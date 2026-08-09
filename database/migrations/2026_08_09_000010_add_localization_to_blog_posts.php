<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('content_locale', 2)->default('de')->after('slug');
            $table->uuid('translation_group')->nullable()->after('content_locale');
            $table->index(
                ['content_locale', 'status', 'published_at', 'id'],
                'blog_posts_locale_public_index',
            );
        });

        DB::table('blog_posts')
            ->select('id')
            ->whereNull('translation_group')
            ->orderBy('id')
            ->chunkById(500, function ($posts): void {
                foreach ($posts as $post) {
                    DB::table('blog_posts')
                        ->where('id', $post->id)
                        ->update(['translation_group' => (string) Str::uuid()]);
                }
            });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->unique(
                ['translation_group', 'content_locale'],
                'blog_posts_translation_locale_unique',
            );
        });

        Schema::table('blog_post_revisions', function (Blueprint $table) {
            $table->string('content_locale', 2)->default('de')->after('slug');
            $table->uuid('translation_group')->nullable()->after('content_locale');
        });
    }

    public function down(): void
    {
        Schema::table('blog_post_revisions', function (Blueprint $table) {
            $table->dropColumn(['content_locale', 'translation_group']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique('blog_posts_translation_locale_unique');
            $table->dropIndex('blog_posts_locale_public_index');
            $table->dropColumn(['content_locale', 'translation_group']);
        });
    }
};
