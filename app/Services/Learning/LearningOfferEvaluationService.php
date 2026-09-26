<?php

namespace App\Services\Learning;

use App\Models\ClubFinanceEntry;
use App\Models\CommerceOrderItem;
use App\Models\LearningCourse;
use App\Models\MarketplaceProduct;
use Illuminate\Support\Collection;

final class LearningOfferEvaluationService
{
    public function evaluate(LearningCourse $course): array
    {
        $course->loadMissing(['enrollments.certificate', 'marketplaceProducts:id,learning_course_id,price_cents,currency']);

        $enrollments = $course->enrollments;
        $activeOrCompleted = $enrollments->whereIn('status', ['active', 'completed']);
        $completed = $enrollments->filter(fn ($enrollment) => $enrollment->completed_at || $enrollment->certificate);
        $waitlisted = $enrollments->whereIn('status', ['waitlisted', 'waitlist']);
        $cancelled = $enrollments->whereIn('status', ['cancelled', 'revoked']);
        $capacity = $course->capacity ? max(1, (int) $course->capacity) : null;
        $revenue = $this->revenue($course);
        $expenses = $this->expenses($course);

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'club_id' => $course->club_id,
                'offer_type' => $course->offer_type ?: 'course',
                'currency' => $revenue['currency'] ?: ($course->currency ?: 'EUR'),
                'capacity' => $capacity,
                'starts_at' => optional($course->starts_at)->toIso8601String(),
                'ends_at' => optional($course->ends_at)->toIso8601String(),
            ],
            'participation' => [
                'total_enrollments' => $enrollments->count(),
                'active_or_completed' => $activeOrCompleted->count(),
                'completed' => $completed->count(),
                'certificates_issued' => $completed->filter(fn ($enrollment) => (bool) $enrollment->certificate)->count(),
                'cancelled' => $cancelled->count(),
                'waitlisted' => $waitlisted->count(),
                'completion_rate_percent' => $this->percent($completed->count(), $activeOrCompleted->count()),
            ],
            'utilization' => [
                'capacity' => $capacity,
                'occupied' => $activeOrCompleted->count(),
                'available' => $capacity ? max(0, $capacity - $activeOrCompleted->count()) : null,
                'occupancy_rate_percent' => $capacity ? $this->percent($activeOrCompleted->count(), $capacity) : null,
                'overbooked_count' => $capacity ? max(0, $activeOrCompleted->count() - $capacity) : 0,
            ],
            'waitlist' => [
                'count' => $waitlisted->count(),
                'enabled_by_capacity' => (bool) $capacity,
                'pressure_percent' => $capacity ? $this->percent($waitlisted->count(), $capacity) : null,
            ],
            'finance' => [
                'revenue_cents' => $revenue['cents'],
                'revenue_source' => $revenue['source'],
                'revenue_order_items' => $revenue['order_items'],
                'expenses_cents' => $expenses,
                'net_cents' => $revenue['cents'] - $expenses,
                'currency' => $revenue['currency'] ?: ($course->currency ?: 'EUR'),
            ],
            'privacy' => [
                'contains_personal_data' => false,
                'participant_rows_exposed' => false,
            ],
        ];
    }

    private function revenue(LearningCourse $course): array
    {
        $productIds = $course->marketplaceProducts->pluck('id');
        $items = $productIds->isEmpty()
            ? collect()
            : CommerceOrderItem::query()
                ->where('orderable_type', MarketplaceProduct::class)
                ->whereIn('orderable_id', $productIds)
                ->whereHas('order', fn ($query) => $query->whereIn('status', ['paid', 'completed']))
                ->get(['total_cents', 'currency']);

        if ($items->isNotEmpty()) {
            return [
                'cents' => (int) $items->sum('total_cents'),
                'source' => 'commerce_order_items',
                'order_items' => $items->count(),
                'currency' => $this->dominantCurrency($items),
            ];
        }

        $paidCount = $course->enrollments->whereIn('status', ['active', 'completed'])->count();

        return [
            'cents' => (int) ($paidCount * max(0, (int) $course->price_cents)),
            'source' => 'estimated_from_enrollments',
            'order_items' => 0,
            'currency' => $course->currency ?: 'EUR',
        ];
    }

    private function expenses(LearningCourse $course): int
    {
        if (! $course->club_id) {
            return 0;
        }

        $references = ['learning_course:'.$course->id, 'course:'.$course->id];

        return (int) round(((float) ClubFinanceEntry::query()
            ->where('club_id', $course->club_id)
            ->where('type', 'expense')
            ->where(function ($query) use ($references): void {
                $query->whereIn('reference', $references)
                    ->orWhere(function ($nested): void {
                        $nested->where('category', 'learning')
                            ->where('title', 'like', '%Kurs%');
                    });
            })
            ->sum('amount')) * 100);
    }

    private function percent(int $value, int $of): int
    {
        return $of > 0 ? (int) round(($value / $of) * 100) : 0;
    }

    private function dominantCurrency(Collection $items): ?string
    {
        return $items
            ->pluck('currency')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }
}
