<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPostRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'blog_post_id',
        'user_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'category',
        'blog_category_id',
        'tags',
        'meta_title',
        'meta_description',
        'status',
        'published_at',
        'seo_score',
        'changed_fields',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'changed_fields' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
