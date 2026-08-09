<?php

namespace App\Models;

use App\Support\SupportedLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LearningCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'club_id',
        'title',
        'slug',
        'subtitle',
        'description',
        'category',
        'sport_type',
        'level',
        'language',
        'translation_group',
        'cover_image',
        'learning_goals',
        'requirements',
        'target_groups',
        'sales_points',
        'faq_items',
        'guarantee_text',
        'certificate_logo_url',
        'certificate_signature_name',
        'certificate_footer_text',
        'tags',
        'status',
        'quality_status',
        'quality_note',
        'reviewed_by',
        'reviewed_at',
        'featured_at',
        'is_public',
        'is_free',
        'price_cents',
        'currency',
        'estimated_minutes',
        'published_at',
    ];

    protected $hidden = [
        'translation_group',
    ];

    protected function casts(): array
    {
        return [
            'learning_goals' => 'array',
            'requirements' => 'array',
            'target_groups' => 'array',
            'sales_points' => 'array',
            'faq_items' => 'array',
            'tags' => 'array',
            'is_public' => 'boolean',
            'is_free' => 'boolean',
            'price_cents' => 'integer',
            'estimated_minutes' => 'integer',
            'reviewed_at' => 'datetime',
            'featured_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LearningCourse $course) {
            $course->language = SupportedLocale::normalize($course->language)
                ?? SupportedLocale::DEFAULT;
            $course->translation_group = $course->translation_group ?: (string) Str::uuid();

            if (! $course->slug) {
                $course->slug = static::uniqueSlug($course->title);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'kurs';
        $slug = $base;
        $counter = 2;

        while (static::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    public function tutor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function sections()
    {
        return $this->hasMany(LearningCourseSection::class)->orderBy('position')->orderBy('id');
    }

    public function lessons()
    {
        return $this->hasMany(LearningLesson::class)->orderBy('position')->orderBy('id');
    }

    public function quizzes()
    {
        return $this->hasMany(LearningQuiz::class);
    }

    public function enrollments()
    {
        return $this->hasMany(LearningEnrollment::class);
    }

    public function marketplaceProducts()
    {
        return $this->hasMany(MarketplaceProduct::class);
    }

    public function reviews()
    {
        return $this->hasMany(LearningCourseReview::class);
    }

    public function coupons()
    {
        return $this->hasMany(LearningCoupon::class);
    }

    public function assignments()
    {
        return $this->hasMany(LearningAssignment::class);
    }

    public function certificates()
    {
        return $this->hasMany(LearningCertificate::class);
    }

    public function translationVariants()
    {
        return $this->hasMany(self::class, 'translation_group', 'translation_group')
            ->whereNotNull('translation_group');
    }

    public function scopePublishedPublic(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('is_public', true)
            ->where(fn (Builder $published) => $published
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    public function scopePreferredForLocale(Builder $query, mixed $locale = null): Builder
    {
        $locale = SupportedLocale::normalize($locale ?? app()->getLocale()) ?? SupportedLocale::DEFAULT;

        if ($locale === SupportedLocale::DEFAULT) {
            return $query->where('learning_courses.language', SupportedLocale::DEFAULT);
        }

        return $query->where(function (Builder $preferred) use ($locale): void {
            $preferred
                ->where('learning_courses.language', $locale)
                ->orWhere(function (Builder $fallback) use ($locale): void {
                    $fallback
                        ->where('learning_courses.language', SupportedLocale::DEFAULT)
                        ->whereNotExists(function ($translation) use ($locale): void {
                            $translation
                                ->selectRaw('1')
                                ->from('learning_courses as localized_learning_courses')
                                ->whereColumn(
                                    'localized_learning_courses.translation_group',
                                    'learning_courses.translation_group',
                                )
                                ->whereColumn(
                                    'localized_learning_courses.user_id',
                                    'learning_courses.user_id',
                                )
                                ->where('localized_learning_courses.language', $locale)
                                ->where('localized_learning_courses.status', 'published')
                                ->where('localized_learning_courses.is_public', true)
                                ->where(function ($published): void {
                                    $published
                                        ->whereNull('localized_learning_courses.published_at')
                                        ->orWhere('localized_learning_courses.published_at', '<=', now());
                                });
                        });
                });
        });
    }
}
