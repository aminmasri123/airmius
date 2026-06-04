<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    reviewSummary: {
        type: Object,
        default: () => ({
            rating_avg: null,
            rating_count: 0,
            verified_purchase_count: 0,
            rating_distribution: [],
        }),
    },
    reviews: {
        type: Array,
        default: () => [],
    },
})

const { t } = useI18n()
const hasReviews = computed(() => Number(props.reviewSummary?.rating_count || 0) > 0)
const roundedRating = computed(() => Math.round(Number(props.reviewSummary?.rating_avg || 0)))
const ratingLabel = computed(() => hasReviews.value
    ? `${props.reviewSummary.rating_avg} / 5`
    : t('Noch keine Bewertungen'))
const ratingDistribution = computed(() => {
    const source = props.reviewSummary?.rating_distribution || []

    if (Array.isArray(source)) {
        return source
            .map((item) => ({
                rating: Number(item.rating || 0),
                count: Number(item.count || 0),
            }))
            .filter((item) => item.rating >= 1 && item.rating <= 5)
            .sort((a, b) => b.rating - a.rating)
    }

    return [5, 4, 3, 2, 1].map((rating) => ({
        rating,
        count: Number(source[String(rating)] || source[rating] || 0),
    }))
})
const maxRatingCount = computed(() => Math.max(1, ...ratingDistribution.value.map((item) => item.count)))
const ratingShare = (count) => `${Math.round((Number(count || 0) / maxRatingCount.value) * 100)}%`
</script>

<template>
    <section class="mt-6 rounded border border-border bg-bg p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ $t("Bewertungen") }}</p>
                <h2 class="mt-1 text-lg font-black text-primary">{{ ratingLabel }}</h2>
            </div>
            <div v-if="hasReviews" class="flex items-center gap-2 rounded bg-card px-3 py-2 text-sm font-black text-primary">
                <span class="flex text-air-orange">
                    <i
                        v-for="star in 5"
                        :key="star"
                        :class="[star <= roundedRating ? 'las la-star' : 'lar la-star', 'text-base']"
                    ></i>
                </span>
                <span>{{ reviewSummary.rating_count }} {{ $t("Bewertungen") }}</span>
            </div>
        </div>

        <p v-if="hasReviews" class="mt-2 text-sm text-secondary">
            {{ $t('{count} davon sind als verifizierter Kauf markiert.', { count: reviewSummary.verified_purchase_count }) }}
        </p>
        <p v-else class="mt-2 text-sm text-secondary">
            {{ $t("Noch keine öffentlichen Bewertungen für dieses Angebot.") }}
        </p>

        <div v-if="hasReviews && ratingDistribution.length" class="mt-4 space-y-2">
            <div
                v-for="item in ratingDistribution"
                :key="item.rating"
                class="grid grid-cols-[3rem,minmax(0,1fr),2rem] items-center gap-3 text-xs font-bold text-secondary"
            >
                <span class="flex items-center gap-1 text-primary">
                    {{ item.rating }}
                    <i class="las la-star text-air-orange"></i>
                </span>
                <span class="h-2 overflow-hidden rounded-full bg-card">
                    <span
                        class="block h-full rounded-full bg-air-orange"
                        :style="{ width: ratingShare(item.count) }"
                    ></span>
                </span>
                <span class="text-right">{{ item.count }}</span>
            </div>
        </div>

        <div v-if="reviews.length" class="mt-4 grid gap-3 md:grid-cols-2">
            <article
                v-for="review in reviews"
                :key="review.id"
                class="rounded border border-border bg-card p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-buttonPrimary/10 text-xs font-black text-buttonPrimary">
                            {{ review.author_initials }}
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-black text-primary">{{ review.author_name }}</span>
                            <span v-if="review.verified_purchase" class="mt-0.5 inline-flex items-center gap-1 text-xs font-bold text-success">
                                <i class="las la-check-circle text-sm"></i>
                                {{ $t("Verifizierter Kauf") }}
                            </span>
                        </span>
                    </div>
                    <span class="shrink-0 text-sm font-black text-air-orange">{{ review.rating }} / 5</span>
                </div>
                <h3 v-if="review.title" class="mt-3 text-sm font-black text-primary">{{ review.title }}</h3>
                <p v-if="review.body" class="mt-2 line-clamp-4 text-sm leading-6 text-secondary">{{ review.body }}</p>
            </article>
        </div>
    </section>
</template>

