<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()

const props = defineProps({
    items: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
})

const hiddenIds = ref([])
const skippedIds = ref([])
const selectedTeams = ref({})
const applicationTeamSizes = ref({})
const offsetX = ref(0)
const rotation = ref(0)
const isDragging = ref(false)
const isAnimating = ref(false)
const dragStartX = ref(0)
const swipeDirection = ref(null)
const feedback = ref(null)

const availableCards = computed(() => props.items.filter((matching) => (
    !matching.mine
    && !matching.my_application
    && !hiddenIds.value.includes(matching.id)
    && matching.status === 'open'
)))
const currentCard = computed(() => availableCards.value[0] || null)
const nextCard = computed(() => availableCards.value[1] || null)
const swipeProgress = computed(() => Math.min(Math.abs(offsetX.value) / 120, 1))
const actionHint = computed(() => {
    if (offsetX.value > 24) return t('sport_matching.deck.interest_label')
    if (offsetX.value < -24) return t('sport_matching.deck.not_now')
    return t('sport_matching.deck.move_card')
})
const dateLocale = computed(() => ({ de: 'de-DE', en: 'en-US', fr: 'fr-FR', ar: 'ar' })[locale.value] || 'de-DE')
const skillLabel = (level) => t(`sport_matching.skill.${level}`, level)

const cardTransform = computed(() => {
    const leavingOffset = swipeDirection.value === 'right' ? 900 : -900
    const x = isAnimating.value ? leavingOffset : offsetX.value
    const angle = isAnimating.value ? (swipeDirection.value === 'right' ? 18 : -18) : rotation.value

    return {
        transform: `translate3d(${x}px, 0, 0) rotate(${angle}deg)`,
        transition: isDragging.value ? 'none' : 'transform 240ms cubic-bezier(.22,.8,.3,1)',
    }
})

const cardStyle = (card, depth = 0) => ({
    zIndex: 20 - depth,
    transform: `translateY(${depth * 12}px) scale(${1 - depth * 0.035})`,
})

const formatDate = (value) => {
    if (!value) return t('sport_matching.common.date_open')

    return new Intl.DateTimeFormat(dateLocale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
        hour12: false,
}).format(new Date(value))
}

const formatLocation = (matching) => {
    const locationName = String(matching?.location_name || '').trim()
    const address = String(matching?.address || '').trim()
    const locality = [matching?.postal_code, matching?.city]
        .map((value) => String(value || '').trim())
        .filter(Boolean)
        .join(' ')
    const country = String(matching?.country_code || '').trim()
    const cityAndCountry = [locality, country].filter(Boolean).join(', ')

    return [locationName, address, cityAndCountry].filter(Boolean).join(' · ')
        || t('sport_matching.common.location_open')
}

const ownerInitials = (name) => (name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()

const sportIconClass = (sport) => {
    const value = `${sport?.slug || ''} ${sport?.name || ''}`.toLowerCase()
    if (value.includes('football') || value.includes('fußball') || value.includes('soccer')) return 'las la-futbol'
    if (value.includes('basket')) return 'las la-basketball-ball'
    if (value.includes('tennis') || value.includes('padel')) return 'las la-table-tennis'
    if (value.includes('swim') || value.includes('schwimm')) return 'las la-swimmer'
    if (value.includes('bike') || value.includes('rad') || value.includes('cycling')) return 'las la-biking'
    if (value.includes('hike') || value.includes('wandern')) return 'las la-hiking'
    return 'las la-running'
}

const showFeedback = (type, text) => {
    feedback.value = { type, text }
    window.setTimeout(() => {
        feedback.value = null
    }, 3000)
}

const startDrag = (event) => {
    if (!currentCard.value || isAnimating.value) return

    isDragging.value = true
    dragStartX.value = event.clientX
    event.currentTarget?.setPointerCapture?.(event.pointerId)
}

const moveDrag = (event) => {
    if (!isDragging.value || isAnimating.value) return

    offsetX.value = event.clientX - dragStartX.value
    rotation.value = Math.max(-12, Math.min(12, offsetX.value / 18))
}

const endDrag = () => {
    if (!isDragging.value) return

    isDragging.value = false

    if (offsetX.value > 110) {
        commitSwipe('right')
    } else if (offsetX.value < -110) {
        commitSwipe('left')
    } else {
        offsetX.value = 0
        rotation.value = 0
    }
}

const commitSwipe = (direction) => {
    const matching = currentCard.value
    if (!matching || isAnimating.value) return

    if (direction === 'right' && matching.mode === 'team' && (!selectedTeams.value[matching.id] || !Number(applicationTeamSizes.value[matching.id]))) {
        showFeedback('warning', t('sport_matching.deck.choose_team_first'))
        offsetX.value = 0
        rotation.value = 0
        return
    }

    isAnimating.value = true
    swipeDirection.value = direction

    window.setTimeout(() => {
        hiddenIds.value = [...hiddenIds.value, matching.id]
        offsetX.value = 0
        rotation.value = 0
        isAnimating.value = false
        swipeDirection.value = null

        if (direction === 'right') {
            router.post(route('auth.sport-matching.apply', matching.id), {
                team_id: matching.mode === 'team' ? selectedTeams.value[matching.id] : null,
                team_size: matching.mode === 'team' ? Number(applicationTeamSizes.value[matching.id]) : null,
                message: null,
            }, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => showFeedback('success', t('sport_matching.deck.interest_sent')),
                onError: () => {
                    hiddenIds.value = hiddenIds.value.filter((id) => id !== matching.id)
                    showFeedback('error', t('sport_matching.deck.request_failed'))
                },
            })
        } else {
            skippedIds.value = [...skippedIds.value, matching.id]
            router.post(route('auth.sport-matching.dismiss', matching.id), { dismissed: true }, {
                preserveState: true,
                preserveScroll: true,
                onError: () => {
                    hiddenIds.value = hiddenIds.value.filter((id) => id !== matching.id)
                    skippedIds.value = skippedIds.value.filter((id) => id !== matching.id)
                },
            })
            showFeedback('neutral', t('sport_matching.deck.skipped'))
        }
    }, 240)
}

const undo = () => {
    if (isAnimating.value || !skippedIds.value.length) return

    const matchingId = skippedIds.value[skippedIds.value.length - 1]
    skippedIds.value = skippedIds.value.slice(0, -1)
    hiddenIds.value = hiddenIds.value.filter((id) => id !== matchingId)
    if (matchingId) {
        router.post(route('auth.sport-matching.dismiss', matchingId), { dismissed: false }, {
            preserveState: true,
            preserveScroll: true,
        })
    }
    showFeedback('neutral', t('sport_matching.deck.restored'))
}

const reportCurrent = () => {
    const ownerId = currentCard.value?.owner?.id
    if (!ownerId || !window.confirm(t('sport_matching.deck.report_confirm'))) return
    router.post(route('auth.reports.store'), {
        type: 'user',
        id: ownerId,
        reason: 'other',
        details: t('sport_matching.deck.report_details'),
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => showFeedback('success', t('sport_matching.deck.reported')),
    })
}

const blockCurrent = () => {
    const ownerId = currentCard.value?.owner?.id
    if (!ownerId || !window.confirm(t('sport_matching.deck.block_confirm'))) return
    router.post(route('auth.users.block', ownerId), {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            hiddenIds.value = [...hiddenIds.value, currentCard.value.id]
            showFeedback('success', t('sport_matching.deck.blocked'))
        },
    })
}
</script>

<template>
    <section class="relative overflow-hidden rounded-[30px] border border-border bg-gradient-to-br from-card via-muted/45 to-card p-4 text-primary shadow-[0_22px_70px_rgba(15,23,42,0.10)] sm:p-7">
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-air-blue/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-20 h-72 w-72 rounded-full bg-air-green/10 blur-3xl"></div>

        <div class="relative z-10 mx-auto max-w-2xl text-center">
            <div class="inline-flex items-center gap-2 rounded-full border border-air-blue/30 bg-air-blue/10 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-air-blue">
                <i class="las la-bolt"></i>
                {{ t('sport_matching.deck.eyebrow') }}
            </div>
            <h2 class="mt-3 text-2xl font-black tracking-[-0.03em] text-primary sm:text-3xl">{{ t('sport_matching.deck.title') }}</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-secondary">{{ t('sport_matching.deck.intro') }}</p>
        </div>

        <div v-if="feedback" class="relative z-30 mx-auto mt-4 max-w-xl rounded-xl border px-4 py-3 text-center text-sm font-bold" :class="{
            'border-emerald-400/40 bg-emerald-400/10 text-emerald-300': feedback.type === 'success',
            'border-amber-400/40 bg-amber-400/10 text-amber-200': feedback.type === 'warning',
            'border-red-400/40 bg-red-400/10 text-red-300': feedback.type === 'error',
            'border-air-blue/40 bg-air-blue/10 text-air-blue': feedback.type === 'neutral',
        }">
            {{ feedback.text }}
        </div>

        <div v-if="currentCard" class="relative z-10 mx-auto mt-7 max-w-xl">
            <div class="relative h-[560px] sm:h-[590px]">
                <div v-if="nextCard" class="absolute inset-x-2 top-2 h-full rounded-[1.75rem] border border-border bg-muted shadow-lg" :style="cardStyle(nextCard, 1)"></div>

                <article
                    class="absolute inset-0 flex touch-none select-none flex-col overflow-hidden rounded-[1.75rem] border border-border bg-card shadow-2xl"
                    :class="{ 'cursor-grabbing': isDragging, 'cursor-grab': !isDragging }"
                    :style="cardTransform"
                    @pointerdown="startDrag"
                    @pointermove="moveDrag"
                    @pointerup="endDrag"
                    @pointercancel="endDrag"
                >
                    <div class="relative h-44 shrink-0 overflow-hidden bg-gradient-to-br from-air-blue via-air-blue/75 to-air-green/70 sm:h-52">
                        <div class="absolute -right-8 -top-16 h-48 w-48 rounded-full border-[20px] border-white/10"></div>
                        <div class="absolute -bottom-20 -left-10 h-52 w-52 rounded-full border-[26px] border-white/10"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="flex h-24 w-24 items-center justify-center rounded-3xl border border-white/30 bg-white/15 text-5xl text-white shadow-lg backdrop-blur-sm">
                                <i :class="sportIconClass(currentCard.sport)"></i>
                            </div>
                        </div>
                        <div class="absolute left-5 top-5 rounded-full bg-black/20 px-3 py-1 text-xs font-black uppercase tracking-wider text-white backdrop-blur-sm">
                            {{ t(currentCard.mode === 'team' ? 'sport_matching.modes.team_challenge' : 'sport_matching.modes.partner') }}
                        </div>
                        <div class="absolute right-5 top-5 rounded-full bg-white/15 px-3 py-1 text-xs font-bold text-white backdrop-blur-sm">
                            {{ currentCard.sport?.name || t('sport_matching.common.sport') }}
                        </div>
                        <div v-if="offsetX > 24" class="absolute bottom-5 left-5 rotate-[-8deg] rounded-lg border-2 border-white px-3 py-1 text-lg font-black uppercase text-white">{{ t('sport_matching.deck.interest') }}</div>
                        <div v-if="offsetX < -24" class="absolute bottom-5 right-5 rotate-[8deg] rounded-lg border-2 border-white px-3 py-1 text-lg font-black uppercase text-white">{{ t('sport_matching.deck.not_now') }}</div>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col p-5 sm:p-7">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-black uppercase tracking-wider text-air-blue">{{ currentCard.sport?.name || t('sport_matching.common.offer') }}</p>
                                <h3 class="mt-2 text-2xl font-black leading-tight text-primary sm:text-3xl">{{ currentCard.title }}</h3>
                            </div>
                            <span class="shrink-0 rounded-full bg-air-blue/10 px-3 py-1 text-xs font-bold text-air-blue">{{ skillLabel(currentCard.skill_level) }}</span>
                        </div>

                        <p v-if="currentCard.description" class="mt-4 line-clamp-3 text-sm leading-6 text-secondary">{{ currentCard.description }}</p>

                        <div class="mt-5 grid gap-3 text-sm text-secondary">
                            <div class="flex items-center gap-3"><i class="las la-calendar text-xl text-air-blue"></i><span>{{ formatDate(currentCard.starts_at) }}</span></div>
                            <div class="flex items-start gap-3"><i class="las la-map-marker mt-0.5 text-xl text-air-blue"></i><span class="min-w-0 break-words">{{ formatLocation(currentCard) }}</span></div>
                            <div class="flex items-center gap-3"><i class="las la-users text-xl text-air-blue"></i><span>{{ currentCard.mode === 'team' ? `${currentCard.own_team_size || currentCard.team_size} vs. ${currentCard.opponent_size_type === 'minimum' ? 'min. ' : ''}${currentCard.team_size}` : t(currentCard.participants_needed === 1 ? 'sport_matching.deck.person_one' : 'sport_matching.deck.person_many', { count: currentCard.participants_needed }) }}</span></div>
                        </div>

                        <div class="mt-auto border-t border-border pt-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-air-blue/15 text-sm font-black text-air-blue">
                                    <img v-if="currentCard.owner?.profile_photo_url" :src="currentCard.owner.profile_photo_url" :alt="currentCard.owner.name" class="h-full w-full object-cover">
                                    <span v-else>{{ ownerInitials(currentCard.owner?.name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs text-secondary">{{ t('sport_matching.cards.offered_by') }}</p>
                                    <p class="truncate text-sm font-bold text-primary">{{ currentCard.owner?.name || t('sport_matching.cards.community') }}<span v-if="currentCard.team"> · {{ currentCard.team.name }}</span></p>
                                </div>
                            </div>

                            <label v-if="currentCard.mode === 'team'" class="mt-4 block text-xs font-bold text-secondary">
                                {{ t('sport_matching.deck.team_for_challenge') }}
                                <select v-model="selectedTeams[currentCard.id]" class="mt-1 w-full rounded-xl border-border bg-inputBg px-3 py-2 text-sm font-medium text-primary" @pointerdown.stop>
                                    <option value="">{{ t('sport_matching.cards.choose_team') }}</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>
                            </label>
                            <label v-if="currentCard.mode === 'team'" class="mt-2 block text-xs font-bold text-secondary">{{ t('sport_matching.your_player_count') }}<input v-model.number="applicationTeamSizes[currentCard.id]" type="number" min="1" max="500" class="mt-1 w-full rounded-xl border-border bg-inputBg px-3 py-2 text-sm text-primary" @pointerdown.stop></label>
                        </div>
                    </div>
                </article>
            </div>

            <div class="mt-4 flex items-center justify-center gap-4 text-xs font-bold text-secondary">
                <span class="flex items-center gap-1"><i class="las la-arrow-left text-air-blue"></i> {{ t('sport_matching.deck.not_now') }}</span>
                <span class="h-1 w-1 rounded-full bg-secondary/50"></span>
                <span>{{ actionHint }}</span>
                <span class="h-1 w-1 rounded-full bg-secondary/50"></span>
                <span>{{ t('sport_matching.deck.interest') }} <i class="las la-arrow-right text-air-blue"></i></span>
            </div>

            <div class="mt-5 flex items-center justify-center gap-4">
                <button type="button" class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-inputBg text-secondary shadow-lg transition hover:-translate-y-1 hover:border-air-blue hover:text-primary" :disabled="!skippedIds.length || isAnimating" :aria-label="t('sport_matching.deck.undo_label')" @click="undo">
                    <i class="las la-undo text-xl"></i>
                </button>
                <button type="button" class="flex h-16 w-16 items-center justify-center rounded-full border-2 border-red-400/50 bg-red-400/10 text-red-300 shadow-lg transition hover:-translate-y-1 hover:bg-red-400/20" :disabled="isAnimating" :aria-label="t('sport_matching.deck.skip_label')" @click="commitSwipe('left')">
                    <i class="las la-times text-3xl"></i>
                </button>
                <button type="button" class="flex h-20 w-20 items-center justify-center rounded-full border-2 border-emerald-400/60 bg-emerald-400/15 text-emerald-300 shadow-lg transition hover:-translate-y-1 hover:bg-emerald-400/25" :disabled="isAnimating" :aria-label="t('sport_matching.deck.interest_label')" @click="commitSwipe('right')">
                    <i class="las la-check text-3xl"></i>
                </button>
                <button type="button" class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-inputBg text-secondary shadow-lg transition hover:-translate-y-1 hover:border-air-blue hover:text-primary" :aria-label="t('sport_matching.deck.details_label')" @click="showFeedback('neutral', t('sport_matching.deck.details_text'))">
                    <i class="las la-info text-xl"></i>
                </button>
            </div>
            <div class="mt-4 flex justify-center gap-4 text-xs font-bold text-secondary">
                <button type="button" class="hover:text-primary" @click="reportCurrent"><i class="las la-flag me-1"></i>{{ t('sport_matching.deck.report') }}</button>
                <button type="button" class="hover:text-primary" @click="blockCurrent"><i class="las la-ban me-1"></i>{{ t('sport_matching.deck.block') }}</button>
            </div>
        </div>

        <div v-else class="relative z-10 mx-auto mt-8 max-w-lg rounded-3xl border border-dashed border-border bg-muted p-8 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-air-blue/10 text-3xl text-air-blue"><i class="las la-check-double"></i></div>
            <h3 class="mt-4 text-xl font-black text-primary">{{ t('sport_matching.deck.latest_title') }}</h3>
            <p class="mt-2 text-sm leading-6 text-secondary">{{ t('sport_matching.deck.latest_text') }}</p>
        </div>

        <p class="relative z-10 mt-6 text-center text-xs text-secondary">{{ t('sport_matching.deck.privacy') }}</p>
    </section>
</template>
