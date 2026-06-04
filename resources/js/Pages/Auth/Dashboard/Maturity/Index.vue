<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n()

const overview = ref({})
const feedDiscovery = ref({ items: [], meta: null })
const feedTrending = ref({ items: [], meta: null })
const searchQuery = ref('')
const searchResult = ref({ term: '', results: [], counts: {}, loaded: false, pagination: null })
const searchTypeFilter = ref('all')
const challenges = ref({ challenges: [], total: 0 })
const selectedRouteId = ref('')
const routeAnalytics = ref(null)
const coachWeekly = ref(null)
const onboarding = ref(null)
const viral = ref(null)
const safety = ref(null)

const searchLoading = ref(false)
const copiedShare = ref(false)
const discoveryPage = ref(1)
const discoveryScope = ref('all')
const discoveryPerPage = ref(6)
const trendingPage = ref(1)
const trendingTrendDays = ref(14)
const trendingPerPage = ref(6)

const loading = reactive({
    overview: false,
    discovery: false,
    trending: false,
    search: false,
    challenges: false,
    routeAnalytics: false,
    coachWeekly: false,
    onboarding: false,
    viral: false,
    safety: false,
})

const errors = reactive({
    overview: null,
    discovery: null,
    trending: null,
    search: null,
    challenges: null,
    routeAnalytics: null,
    coachWeekly: null,
    onboarding: null,
    viral: null,
    safety: null,
})

const lastUpdated = reactive({
    overview: null,
    discovery: null,
    trending: null,
    search: null,
    challenges: null,
    routeAnalytics: null,
    coachWeekly: null,
    onboarding: null,
    viral: null,
    safety: null,
})

const parseDecimal = (value, fallback = 0) => {
    const number = Number(value)

    return Number.isFinite(number) ? number : fallback
}

const parseInteger = (value, fallback = 0) => {
    const parsed = parseDecimal(value, NaN)

    return Number.isFinite(parsed) ? Math.round(parsed) : fallback
}

const activeLocale = computed(() => locale.value || 'de')

const formatDate = (value) => {
    if (!value) return '-'

    try {
        return new Intl.DateTimeFormat(activeLocale.value, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value))
    } catch {
        return String(value)
    }
}

const formatDistance = (value) => `${parseDecimal(value, 0).toFixed(2)} km`
const formatMinutes = (value) => `${parseInteger(value, 0)} Min`
const formatSeconds = (value) => `${parseInteger(value, 0)} s`
const formatPercent = (value) => `${parseDecimal(value, 0).toFixed(1)}%`
const formatTrend = (value) => `${parseDecimal(value, 0) > 0 ? '+' : ''}${parseDecimal(value, 0).toFixed(1)}%`
const formatSpeed = (value) => `${parseDecimal(value, 0).toFixed(2)} m/s`
const formatUpdatedAt = (value) => {
    if (!value) return t('maturity.common.notLoaded')

    try {
        return new Intl.DateTimeFormat(activeLocale.value, {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value))
    } catch {
        return '-'
    }
}

const trendClass = (value) => {
    const numericValue = parseDecimal(value, 0)

    if (numericValue > 0) return 'text-success'
    if (numericValue < 0) return 'text-error'

    return 'text-secondary'
}

const parseCollection = (responsePayload) => ({
    items: Array.isArray(responsePayload?.data) ? responsePayload.data : [],
    meta: responsePayload?.meta || null,
    links: responsePayload?.links || null,
})

const dedupeById = (items) => {
    const seen = new Set()

    return items.filter((item) => {
        const id = item?.id

        if (id === undefined || id === null) return true
        if (seen.has(id)) return false
        seen.add(id)

        return true
    })
}

const hasMorePages = (collectionRef) => {
    const meta = collectionRef.value?.meta || {}
    const currentPage = parseInteger(meta.current_page, 1)
    const lastPage = parseInteger(meta.last_page, 1)

    return currentPage < lastPage
}

const discoveryHasMore = computed(() => hasMorePages(feedDiscovery))
const trendingHasMore = computed(() => hasMorePages(feedTrending))

const searchTypeOptions = [
    { value: 'all', labelKey: 'maturity.search.types.all' },
    { value: 'user', labelKey: 'maturity.search.types.user' },
    { value: 'club', labelKey: 'maturity.search.types.club' },
    { value: 'team', labelKey: 'maturity.search.types.team' },
    { value: 'route', labelKey: 'maturity.search.types.route' },
    { value: 'training_plan', labelKey: 'maturity.search.types.trainingPlan' },
    { value: 'post', labelKey: 'maturity.search.types.post' },
]

const filteredSearchResults = computed(() => {
    const base = searchResult.value?.results || []

    if (searchTypeFilter.value === 'all') {
        return base
    }

    return base.filter((result) => result?.type === searchTypeFilter.value)
})

const onboardingActionFor = (key) => {
    const actions = {
        name: { labelKey: 'maturity.onboarding.actions.name', href: route('auth.settings') },
        email_verified: { labelKey: 'maturity.onboarding.actions.emailVerified', href: route('auth.settings') },
        photo: { labelKey: 'maturity.onboarding.actions.photo', href: route('auth.settings') },
        bio: { labelKey: 'maturity.onboarding.actions.bio', href: route('auth.settings') },
        country: { labelKey: 'maturity.onboarding.actions.country', href: route('auth.settings') },
        first_route: { labelKey: 'maturity.onboarding.actions.firstRoute', href: route('auth.sport-map.index') },
        first_track: { labelKey: 'maturity.onboarding.actions.firstTrack', href: route('auth.sport-map.index') },
        first_training_log: { labelKey: 'maturity.onboarding.actions.firstTrainingLog', href: route('auth.training.logs.create') },
        first_post: { labelKey: 'maturity.onboarding.actions.firstPost', href: route('auth.feed.index') },
        first_friendship: { labelKey: 'maturity.onboarding.actions.firstFriendship', href: route('auth.friends.index') },
    }

    return actions[key] || null
}

const searchResultHref = (result) => {
    if (!result) return null

    if (result.type === 'user') {
        return route('auth.users.show', result.id)
    }
    if (result.type === 'club') {
        return route('auth.clubs.show', result.id)
    }
    if (result.type === 'team') {
        return route('auth.teams.show', result.id)
    }
    if (result.type === 'route') {
        return route('auth.sport-map.index', { route_id: result.id })
    }
    if (result.type === 'training_plan') {
        return route('auth.training.index')
    }

    if (result.type === 'post') {
        return route('auth.feed.index')
    }

    return null
}

const requestWithLoading = async (key, requestFn) => {
    loading[key] = true
    errors[key] = null

    try {
        const response = await requestFn()
        if (response) {
            lastUpdated[key] = new Date().toISOString()
        }

        return response
    } catch (error) {
        errors[key] = error?.response?.data?.message
            || error?.response?.data?.error
            || error?.message
            || t('maturity.errors.loadFailed')

        return null
    } finally {
        loading[key] = false
    }
}

const loadOverview = async () => {
    const response = await requestWithLoading('overview', () => window.axios.get(route('api.v1.maturity.overview')))

    if (!response) {
        return
    }

    overview.value = response.data?.data || {}
}

const loadDiscovery = async (append = false) => {
    if (append && !discoveryHasMore.value) {
        return
    }

    const page = append ? discoveryPage.value + 1 : 1

    const response = await requestWithLoading(
        'discovery',
        () => window.axios.get(route('api.v1.maturity.feed-discovery'), {
            params: {
                scope: discoveryScope.value,
                per_page: discoveryPerPage.value,
                page,
            },
        }),
    )

    if (!response) {
        return
    }

    const payload = parseCollection(response.data || {})

    feedDiscovery.value = append
        ? {
              ...payload,
              items: dedupeById([
                  ...(feedDiscovery.value.items || []),
                  ...payload.items,
              ]),
          }
        : payload

    if (payload.meta?.current_page) {
        discoveryPage.value = parseInteger(payload.meta.current_page, 1)
    }
}

const loadTrending = async (append = false) => {
    if (append && !trendingHasMore.value) {
        return
    }

    const page = append ? trendingPage.value + 1 : 1

    const response = await requestWithLoading(
        'trending',
        () => window.axios.get(route('api.v1.maturity.feed-trending'), {
            params: {
                trend_days: trendingTrendDays.value,
                per_page: trendingPerPage.value,
                page,
            },
        }),
    )

    if (!response) {
        return
    }

    const payload = parseCollection(response.data || {})

    feedTrending.value = append
        ? {
              ...payload,
              items: dedupeById([
                  ...(feedTrending.value.items || []),
                  ...payload.items,
              ]),
          }
        : payload

    if (payload.meta?.current_page) {
        trendingPage.value = parseInteger(payload.meta.current_page, 1)
    }
}

const loadSearch = async () => {
    const term = searchQuery.value.trim()

    if (term.length < 2) {
        errors.search = t('maturity.errors.searchMinLength')
        return
    }

    searchLoading.value = true
    errors.search = null

    try {
        const response = await window.axios.get(route('api.v1.maturity.search'), {
            params: {
                q: term,
                type: searchTypeFilter.value !== 'all' ? searchTypeFilter.value : undefined,
            },
        })
        const payload = response.data?.data || {}

        searchResult.value = {
            term: payload.term || term,
            results: payload.results || [],
            counts: payload.counts || {},
            loaded: true,
            pagination: payload.pagination || null,
        }
        lastUpdated.search = new Date().toISOString()
    } catch (error) {
        errors.search = error?.response?.data?.message || t('maturity.errors.searchFailed')
    } finally {
        searchLoading.value = false
    }
}

const loadChallenges = async () => {
    const response = await requestWithLoading('challenges', () => window.axios.get(route('api.v1.maturity.challenges'), { params: { limit: 6 } }))

    if (!response) {
        return
    }

    const payload = response.data?.data || {}

    challenges.value = {
        challenges: payload.challenges || [],
        total: payload.total || 0,
    }
}

const loadRouteAnalytics = async () => {
    const routeId = selectedRouteId.value

    if (!routeId) {
        errors.routeAnalytics = t('maturity.errors.selectRoute')
        return
    }

    const response = await requestWithLoading(
        'routeAnalytics',
        () => window.axios.get(route('api.v1.maturity.route-analytics', routeId)),
    )

    if (!response) {
        return
    }

    routeAnalytics.value = response.data?.data || null
}

const loadCoachWeekly = async () => {
    const response = await requestWithLoading('coachWeekly', () => window.axios.get(route('api.v1.maturity.coach-weekly')))

    if (!response) {
        return
    }

    coachWeekly.value = response.data?.data || null
}

const loadOnboarding = async () => {
    const response = await requestWithLoading('onboarding', () => window.axios.get(route('api.v1.maturity.onboarding')))

    if (!response) {
        return
    }

    onboarding.value = response.data?.data || null
}

const loadViral = async () => {
    const response = await requestWithLoading('viral', () => window.axios.get(route('api.v1.maturity.viral')))

    if (!response) {
        return
    }

    viral.value = response.data?.data || null
}

const loadSafety = async () => {
    const response = await requestWithLoading('safety', () => window.axios.get(route('api.v1.maturity.safety')))

    if (!response) {
        return
    }

    safety.value = response.data?.data || null
}

const refreshAll = async () => {
    await Promise.allSettled([
        loadOverview(),
        loadDiscovery(false),
        loadTrending(false),
        loadChallenges(),
        loadCoachWeekly(),
        loadOnboarding(),
        loadViral(),
        loadSafety(),
    ])
}

const refreshDiscovery = async () => {
    discoveryPage.value = 1
    await loadDiscovery(false)
}

const refreshTrending = async () => {
    trendingPage.value = 1
    await loadTrending(false)
}

const selectRoute = async (routeId) => {
    selectedRouteId.value = String(routeId)
    await loadRouteAnalytics()
}

const copyReferralCode = async () => {
    if (!viral.value?.share_url) {
        return
    }

    try {
        await navigator.clipboard.writeText(viral.value.share_url)
        copiedShare.value = true
        window.setTimeout(() => {
            copiedShare.value = false
        }, 1200)
    } catch {
        copiedShare.value = false
    }
}

const routeOptions = computed(() => {
    return (challenges.value?.challenges || [])
        .map((challenge) => ({
            id: String(challenge.id),
            label: `${challenge.title || t('maturity.routeAnalytics.routeFallback')} - ${formatDistance(challenge.distance_km || 0)}`,
        }))
        .filter((item) => item.id && item.id !== '0')
})

const overviewScores = computed(() => {
    const scores = overview.value?.scores || {}

    return Object.entries(scores).map(([key, value]) => ({
        key,
        label: t(`maturity.scores.${key}`, key),
        value: parseInteger(value, 0),
    }))
})

const maturityScore = computed(() => parseInteger(overview.value?.maturity_score || overview.value?.maturityScore || 0, 0))
const routeAnalyticsLink = computed(() => selectedRouteId.value
    ? route('auth.sport-map.index', { maturity_route_id: selectedRouteId.value })
    : route('auth.sport-map.index'))

const onboardingIncomplete = computed(() => (onboarding.value?.items || []).filter((item) => !item?.done))

watch(
    () => discoveryScope.value,
    () => {
        feedDiscovery.value = { items: [], meta: null }
        discoveryPage.value = 1
        void loadDiscovery(false)
    },
)

watch(
    () => trendingTrendDays.value,
    () => {
        feedTrending.value = { items: [], meta: null }
        trendingPage.value = 1
        void loadTrending(false)
    },
)

watch(
    () => searchTypeFilter.value,
    () => {
        if (!searchResult.value.loaded || searchQuery.value.trim().length < 2) {
            return
        }

        void loadSearch()
    },
)

watch(
    () => challenges.value.challenges,
    async (items) => {
        if (!items?.length || selectedRouteId.value) return

        selectedRouteId.value = String(items[0].id)
        await loadRouteAnalytics()
    },
    { deep: true, immediate: false },
)

onMounted(async () => {
    await refreshAll()
})
</script>

<template>
    <Head :title="$t('maturity.header.title')" />

    <div class="mx-auto max-w-6xl space-y-6 pb-20">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-primary">{{ $t('maturity.header.title') }}</h1>
                    <p class="mt-1 text-sm text-secondary">
                        {{ $t('maturity.header.subtitle') }}
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                    :disabled="Object.values(loading).some(Boolean)"
                    @click="refreshAll"
                >
                    <i class="las la-rotate mr-2"></i>
                    {{ Object.values(loading).some(Boolean) ? $t('maturity.common.loading') : $t('maturity.common.reload') }}
                </button>
            </div>

            <div class="mt-4 grid gap-2 text-xs text-secondary sm:grid-cols-2">
                <p>{{ $t('maturity.header.updatedOverview', { time: formatUpdatedAt(lastUpdated.overview) }) }}</p>
                <p>{{ $t('maturity.header.updatedModules', { time: formatUpdatedAt(lastUpdated.challenges) }) }}</p>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <article class="rounded-lg border border-border bg-inputBg p-4">
                    <h2 class="text-sm font-semibold text-secondary">{{ $t('maturity.overview.scoreTitle') }}</h2>
                    <p class="mt-1 text-4xl font-black text-primary">{{ maturityScore }}%</p>
                    <p class="mt-2 text-xs text-secondary">
                        {{ $t('maturity.overview.scoreDescription') }}
                    </p>
                </article>

                <article class="rounded-lg border border-border bg-inputBg p-4">
                    <h2 class="text-sm font-semibold text-secondary">{{ $t('maturity.overview.kpisTitle') }}</h2>
                    <div v-if="overview?.overview" class="mt-2 grid gap-2 text-sm">
                        <div class="flex justify-between"><span>{{ $t('maturity.overview.weeklyTrainings') }}</span><strong>{{ overview.overview?.weekly_trainings || 0 }}</strong></div>
                        <div class="flex justify-between"><span>{{ $t('maturity.overview.friends') }}</span><strong>{{ overview.overview?.friend_connections || 0 }}</strong></div>
                        <div class="flex justify-between"><span>{{ $t('maturity.overview.completedRoutes') }}</span><strong>{{ overview.overview?.completed_routes || 0 }}</strong></div>
                        <div class="flex justify-between"><span>{{ $t('maturity.overview.completedTracks') }}</span><strong>{{ overview.overview?.completed_tracks || 0 }}</strong></div>
                    </div>
                </article>
            </div>

            <div v-if="overviewScores.length" class="mt-4 grid gap-2">
                <div
                    v-for="score in overviewScores"
                    :key="score.key"
                    class="rounded-lg border border-border bg-bg p-3"
                >
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="text-secondary">{{ score.label }}</span>
                        <strong>{{ score.value }}%</strong>
                    </div>
                    <div class="h-2 rounded-full bg-card">
                        <div
                            class="h-2 rounded-full bg-buttonPrimary transition-all duration-300"
                            :style="{ width: `${Math.min(100, Math.max(0, score.value))}%` }"
                        />
                    </div>
                </div>
            </div>

            <p v-if="errors.overview" class="mt-4 text-sm text-error">{{ errors.overview }}</p>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="surface-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ $t('maturity.discovery.title') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ $t('maturity.discovery.subtitle') }}</p>
                    </div>

                    <div class="flex items-center gap-2 text-xs">
                        <label class="text-secondary">{{ $t('maturity.discovery.scope') }}</label>
                        <select
                            v-model="discoveryScope"
                            class="rounded-lg border border-border bg-inputBg px-2 py-1 text-xs"
                        >
                            <option value="all">{{ $t('maturity.discovery.scopes.all') }}</option>
                            <option value="mine">{{ $t('maturity.discovery.scopes.mine') }}</option>
                            <option value="club">{{ $t('maturity.discovery.scopes.club') }}</option>
                            <option value="team">{{ $t('maturity.discovery.scopes.team') }}</option>
                            <option value="friends">{{ $t('maturity.discovery.scopes.friends') }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="loading.discovery" class="mt-4 text-sm text-secondary">{{ $t('maturity.discovery.loading') }}</div>
                <p v-else-if="errors.discovery" class="mt-4 text-sm text-error">{{ errors.discovery }}</p>
                <div v-else class="mt-4 space-y-3">
                    <article
                        v-for="post in feedDiscovery.items"
                        :key="post.id"
                        class="rounded-lg border border-border bg-inputBg p-3"
                    >
                        <p class="text-sm text-secondary">{{ post.content ? post.content.slice(0, 150) : post.title }}</p>
                        <p class="mt-2 text-xs text-secondary">
                            {{ post.user?.name || $t('maturity.common.unknown') }} - {{ formatDate(post.created_at) }} - {{ post.post_type || $t('maturity.common.post') }}
                        </p>
                    </article>
                    <p v-if="!feedDiscovery.items.length" class="text-sm text-secondary">
                        {{ $t('maturity.discovery.empty') }}
                    </p>
                    <button
                        v-if="discoveryHasMore"
                        type="button"
                        class="inline-flex items-center rounded-lg border border-border bg-bg px-3 py-2 text-xs font-semibold text-primary hover:bg-muted"
                        :disabled="loading.discovery"
                        @click="loadDiscovery(true)"
                    >
                        {{ loading.discovery ? $t('maturity.common.loadingMore') : $t('maturity.discovery.loadMore') }}
                    </button>
                    <p class="text-xs text-secondary">{{ $t('maturity.common.lastLoaded', { time: formatUpdatedAt(lastUpdated.discovery) }) }}</p>
                </div>
            </article>

            <article class="surface-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ $t('maturity.trending.title') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ $t('maturity.trending.subtitle', { days: trendingTrendDays }) }}</p>
                    </div>

                    <div class="flex items-center gap-2 text-xs">
                        <label class="text-secondary">{{ $t('maturity.trending.period') }}</label>
                        <select
                            v-model="trendingTrendDays"
                            class="rounded-lg border border-border bg-inputBg px-2 py-1 text-xs"
                        >
                            <option :value="7">{{ $t('maturity.trending.days', { days: 7 }) }}</option>
                            <option :value="14">{{ $t('maturity.trending.days', { days: 14 }) }}</option>
                            <option :value="30">{{ $t('maturity.trending.days', { days: 30 }) }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="loading.trending" class="mt-4 text-sm text-secondary">{{ $t('maturity.trending.loading') }}</div>
                <p v-else-if="errors.trending" class="mt-4 text-sm text-error">{{ errors.trending }}</p>
                <div v-else class="mt-4 space-y-3">
                    <article
                        v-for="post in feedTrending.items"
                        :key="post.id"
                        class="rounded-lg border border-border bg-inputBg p-3"
                    >
                        <p class="text-sm text-secondary">{{ post.content ? post.content.slice(0, 150) : post.title }}</p>
                        <p class="mt-2 text-xs text-secondary">
                            {{ post.user?.name || $t('maturity.common.unknown') }} - {{ formatDate(post.created_at) }} - {{ post.post_type || $t('maturity.common.post') }}
                        </p>
                    </article>
                    <p v-if="!feedTrending.items.length" class="text-sm text-secondary">
                        {{ $t('maturity.trending.empty') }}
                    </p>
                    <button
                        v-if="trendingHasMore"
                        type="button"
                        class="inline-flex items-center rounded-lg border border-border bg-bg px-3 py-2 text-xs font-semibold text-primary hover:bg-muted"
                        :disabled="loading.trending"
                        @click="loadTrending(true)"
                    >
                        {{ loading.trending ? $t('maturity.common.loadingMore') : $t('maturity.trending.loadMore') }}
                    </button>
                    <p class="text-xs text-secondary">{{ $t('maturity.common.lastLoaded', { time: formatUpdatedAt(lastUpdated.trending) }) }}</p>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ $t('maturity.search.title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ $t('maturity.search.subtitle') }}</p>

                <form class="mt-4 flex gap-2" @submit.prevent="loadSearch">
                    <input
                        v-model="searchQuery"
                        type="text"
                        maxlength="80"
                        class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        :placeholder="$t('maturity.search.placeholder')"
                    />
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                        :disabled="searchLoading"
                    >
                        {{ searchLoading ? $t('maturity.search.searching') : $t('maturity.search.submit') }}
                    </button>
                </form>

                <p v-if="errors.search" class="mt-2 text-sm text-error">{{ errors.search }}</p>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <button
                        v-for="option in searchTypeOptions"
                        :key="option.value"
                        type="button"
                        class="rounded-lg border border-border px-3 py-1.5 text-xs"
                        :class="searchTypeFilter === option.value ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-bg text-secondary'"
                        @click="searchTypeFilter = option.value"
                    >
                        {{ $t(option.labelKey) }}
                    </button>
                </div>

                <div v-if="searchResult.loaded" class="mt-4 space-y-2 text-sm">
                    <p class="text-secondary">
                        {{ $t('maturity.search.resultFor') }} <strong>{{ searchResult.term }}</strong>
                    </p>
                    <div class="grid gap-2">
                        <article
                            v-for="result in filteredSearchResults"
                            :key="`${result.type}-${result.id}`"
                            class="rounded-lg border border-border bg-inputBg p-3"
                        >
                            <p class="font-semibold text-primary">{{ result.title }}</p>
                            <p class="text-xs text-secondary">{{ result.subtitle }}</p>
                            <p class="mt-2 text-xs uppercase tracking-wide text-secondary">{{ result.type }}</p>
                            <Link
                                v-if="searchResultHref(result)"
                                :href="searchResultHref(result)"
                                class="mt-2 inline-flex items-center text-xs font-semibold text-primary underline"
                            >
                                {{ $t('maturity.common.open') }}
                            </Link>
                        </article>
                    </div>
                    <div class="text-xs text-secondary">
                        <span>{{ $t('maturity.search.counts.users') }}: {{ searchResult.counts.users || 0 }}</span> -
                        <span class="ml-2">{{ $t('maturity.search.counts.clubs') }}: {{ searchResult.counts.clubs || 0 }}</span> -
                        <span class="ml-2">{{ $t('maturity.search.counts.teams') }}: {{ searchResult.counts.teams || 0 }}</span> -
                        <span class="ml-2">{{ $t('maturity.search.counts.routes') }}: {{ searchResult.counts.routes || 0 }}</span> -
                        <span class="ml-2">{{ $t('maturity.search.counts.posts') }}: {{ searchResult.counts.posts || 0 }}</span>
                        <span v-if="searchResult.pagination?.total !== null" class="ml-2">{{ $t('maturity.search.counts.total') }}: {{ searchResult.pagination?.total || 0 }}</span>
                    </div>
                    <p class="text-xs text-secondary">{{ $t('maturity.common.lastLoaded', { time: formatUpdatedAt(lastUpdated.search) }) }}</p>
                </div>
                <p v-else class="mt-4 text-sm text-secondary">{{ $t('maturity.search.notRun') }}</p>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ $t('maturity.challenges.title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ $t('maturity.challenges.subtitle') }}</p>

                <div v-if="loading.challenges" class="mt-4 text-sm text-secondary">{{ $t('maturity.challenges.loading') }}</div>
                <p v-else-if="errors.challenges" class="mt-4 text-sm text-error">{{ errors.challenges }}</p>
                <div v-else class="mt-4 space-y-3">
                    <article
                        v-for="challenge in challenges.challenges"
                        :key="challenge.id"
                        class="rounded-lg border border-border bg-inputBg p-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-primary">{{ challenge.title }}</p>
                                <p class="text-xs text-secondary">
                                    {{ $t('maturity.challenges.participants') }}: {{ challenge.participant_count || 0 }} - {{ $t('maturity.challenges.myRank') }}: {{ challenge.my_rank || '-' }}
                                </p>
                                <p class="text-xs text-secondary">{{ $t('maturity.common.distance') }}: {{ formatDistance(challenge.distance_km || 0) }}</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg bg-buttonSecondary px-3 py-1.5 text-xs font-semibold text-buttonTextSecondary"
                                @click="selectRoute(challenge.id)"
                            >
                                {{ $t('maturity.challenges.routeAnalysis') }}
                            </button>
                        </div>
                    </article>
                    <p v-if="!challenges.challenges.length" class="text-sm text-secondary">
                        {{ $t('maturity.challenges.empty') }}
                    </p>
                    <p v-if="challenges.total > 0" class="text-xs text-secondary">
                        {{ $t('maturity.challenges.total', { count: challenges.total }) }}
                    </p>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ $t('maturity.routeAnalytics.title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ $t('maturity.routeAnalytics.subtitle') }}</p>

                <div class="mt-4 grid gap-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <label class="text-sm text-secondary">{{ $t('maturity.routeAnalytics.route') }}</label>
                        <select
                            v-model="selectedRouteId"
                            class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm"
                            @change="loadRouteAnalytics"
                        >
                            <option value="">{{ $t('maturity.common.choose') }}</option>
                            <option v-for="item in routeOptions" :key="item.id" :value="item.id">{{ item.label }}</option>
                        </select>
                        <button
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                            :disabled="loading.routeAnalytics"
                            @click="loadRouteAnalytics"
                        >
                            {{ loading.routeAnalytics ? $t('maturity.common.loadingShort') : $t('maturity.routeAnalytics.analyze') }}
                        </button>
                    </div>

                    <div v-if="loading.routeAnalytics" class="text-sm text-secondary">{{ $t('maturity.routeAnalytics.loading') }}</div>
                    <p v-else-if="errors.routeAnalytics" class="text-sm text-error">{{ errors.routeAnalytics }}</p>
                    <div v-else-if="routeAnalytics" class="space-y-3">
                        <p class="text-sm text-primary">
                            <Link :href="routeAnalyticsLink" class="underline">{{ $t('maturity.routeAnalytics.openMap') }}</Link>
                        </p>
                        <div class="grid gap-2 text-sm">
                            <div class="flex justify-between"><span>{{ $t('maturity.routeAnalytics.attempts') }}:</span><strong>{{ routeAnalytics.analytics?.attempts || 0 }}</strong></div>
                            <div class="flex justify-between"><span>{{ $t('maturity.routeAnalytics.totalDistance') }}:</span><strong>{{ formatDistance(routeAnalytics.analytics?.distance_km || 0) }}</strong></div>
                            <div class="flex justify-between"><span>{{ $t('maturity.routeAnalytics.avgDistance') }}:</span><strong>{{ formatDistance(routeAnalytics.analytics?.avg_distance_km_per_attempt || 0) }}</strong></div>
                            <div class="flex justify-between"><span>{{ $t('maturity.routeAnalytics.totalDuration') }}:</span><strong>{{ formatMinutes(routeAnalytics.analytics?.duration_min || 0) }}</strong></div>
                            <div class="flex justify-between"><span>{{ $t('maturity.routeAnalytics.elevation') }}:</span><strong>{{ parseInteger(routeAnalytics.analytics?.elevation_gain_m || 0) }} m</strong></div>
                            <div class="flex justify-between"><span>{{ $t('maturity.routeAnalytics.avgSpeed') }}:</span><strong>{{ formatSpeed(routeAnalytics.analytics?.average_speed_mps || 0) }}</strong></div>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-primary">{{ $t('maturity.routeAnalytics.topAttempts') }}</p>
                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="attempt in routeAnalytics.analytics?.top_attempts || []"
                                    :key="`${attempt.user?.id}-${attempt.started_at}`"
                                    class="rounded-lg border border-border bg-bg p-2 text-xs"
                                >
                                    {{ attempt.user?.name || $t('maturity.common.unknown') }} - {{ formatDistance(attempt.distance_km || 0) }}
                                    ({{ formatSeconds(attempt.duration_seconds || 0) }})
                                </div>
                                <p v-if="(routeAnalytics.analytics?.top_attempts || []).length === 0" class="text-xs text-secondary">
                                    {{ $t('maturity.routeAnalytics.noAttempts') }}
                                </p>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-primary">{{ $t('maturity.routeAnalytics.leaderboard') }}</p>
                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="entry in routeAnalytics.analytics?.leaderboard || []"
                                    :key="`${entry.user?.id}-${entry.rank}`"
                                    class="rounded-lg border border-border bg-bg p-2 text-xs"
                                >
                                    <p class="font-semibold text-primary">{{ $t('maturity.routeAnalytics.rank', { rank: entry.rank }) }}: {{ entry.user?.name || $t('maturity.common.unknown') }}</p>
                                    <p class="text-secondary">
                                        {{ parseInteger(entry.attempts || 0, 0) }} {{ $t('maturity.routeAnalytics.runs') }} -
                                        {{ formatDistance(entry.total_distance_km || 0) }} -
                                        {{ $t('maturity.routeAnalytics.average') }} {{ formatSeconds(Math.round((entry.total_duration_seconds || 0) / Math.max(1, entry.attempts || 1))) }}
                                    </p>
                                </div>
                                <p v-if="(routeAnalytics.analytics?.leaderboard || []).length === 0" class="text-xs text-secondary">
                                    {{ $t('maturity.routeAnalytics.noCompetition') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-secondary">{{ $t('maturity.routeAnalytics.noRoute') }}</p>
                </div>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ $t('maturity.coach.title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ $t('maturity.coach.subtitle') }}</p>

                <div v-if="loading.coachWeekly" class="mt-4 text-sm text-secondary">{{ $t('maturity.coach.loading') }}</div>
                <p v-else-if="errors.coachWeekly" class="mt-4 text-sm text-error">{{ errors.coachWeekly }}</p>
                <div v-else-if="coachWeekly" class="mt-4 space-y-3 text-sm">
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="font-semibold text-primary">{{ $t('maturity.coach.currentWeek') }}</p>
                        <p class="text-secondary">
                            {{ $t('maturity.coach.sessions') }} {{ coachWeekly.current_week?.session_count || 0 }} -
                            {{ $t('maturity.common.distance') }} {{ formatDistance(coachWeekly.current_week?.distance_km || 0) }} -
                            {{ $t('maturity.coach.duration') }} {{ formatMinutes(coachWeekly.current_week?.duration_minutes || 0) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="font-semibold text-primary">{{ $t('maturity.coach.previousWeek') }}</p>
                        <p class="text-secondary">
                            {{ $t('maturity.coach.sessions') }} {{ coachWeekly.previous_week?.session_count || 0 }} -
                            {{ $t('maturity.common.distance') }} {{ formatDistance(coachWeekly.previous_week?.distance_km || 0) }} -
                            {{ $t('maturity.coach.duration') }} {{ formatMinutes(coachWeekly.previous_week?.duration_minutes || 0) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="font-semibold text-primary">{{ $t('maturity.coach.trends') }}</p>
                        <p><span class="text-secondary">{{ $t('maturity.coach.sessions') }}:</span> <span :class="trendClass(coachWeekly.trend?.sessions_percent || 0)">{{ formatTrend(coachWeekly.trend?.sessions_percent || 0) }}</span></p>
                        <p><span class="text-secondary">{{ $t('maturity.common.distance') }}:</span> <span :class="trendClass(coachWeekly.trend?.distance_percent || 0)">{{ formatTrend(coachWeekly.trend?.distance_percent || 0) }}</span></p>
                        <p><span class="text-secondary">{{ $t('maturity.coach.duration') }}:</span> <span :class="trendClass(coachWeekly.trend?.duration_percent || 0)">{{ formatTrend(coachWeekly.trend?.duration_percent || 0) }}</span></p>
                    </div>
                    <div v-if="coachWeekly.insights?.recommendations?.length" class="rounded-lg border border-border bg-bg p-3">
                        <p class="font-semibold text-primary">{{ $t('maturity.coach.recommendations') }}</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-secondary">
                            <li v-for="note in coachWeekly.insights.recommendations" :key="note">{{ note }}</li>
                        </ul>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-secondary">{{ $t('maturity.coach.empty') }}</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ $t('maturity.onboarding.title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ $t('maturity.onboarding.subtitle') }}</p>

                <div v-if="loading.onboarding" class="mt-4 text-sm text-secondary">{{ $t('maturity.onboarding.loading') }}</div>
                <p v-else-if="errors.onboarding" class="mt-4 text-sm text-error">{{ errors.onboarding }}</p>
                <div v-else-if="onboarding" class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <p>
                            <strong>{{ parseInteger(onboarding.completion_percent || 0, 0) }}%</strong> {{ $t('maturity.onboarding.completed') }}
                        </p>
                        <p class="text-xs text-secondary">{{ $t('maturity.onboarding.openCount', { count: onboardingIncomplete.length }) }}</p>
                    </div>
                    <div class="h-2 rounded-full bg-card">
                        <div
                            class="h-2 rounded-full bg-success transition-all duration-300"
                            :style="{ width: `${Math.min(100, parseInteger(onboarding.completion_percent || 0, 0))}%` }"
                        />
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="item in onboarding.items || []"
                            :key="item.key"
                            class="space-y-2 rounded-lg border border-border bg-bg px-3 py-2"
                        >
                            <div class="flex items-center justify-between">
                                <span>{{ item.label }}</span>
                                <span :class="item.done ? 'text-success' : 'text-error'">
                                    {{ item.done ? $t('maturity.onboarding.done') : $t('maturity.onboarding.open') }}
                                </span>
                            </div>
                            <Link
                                v-if="!item.done && onboardingActionFor(item.key)"
                                :href="onboardingActionFor(item.key).href"
                                class="inline-flex text-xs font-semibold text-primary underline"
                            >
                                {{ $t(onboardingActionFor(item.key).labelKey) }}
                            </Link>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-secondary">{{ $t('maturity.onboarding.empty') }}</p>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ $t('maturity.viral.title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ $t('maturity.viral.subtitle') }}</p>

                <div v-if="loading.viral" class="mt-4 text-sm text-secondary">{{ $t('maturity.viral.loading') }}</div>
                <p v-else-if="errors.viral" class="mt-4 text-sm text-error">{{ errors.viral }}</p>
                <div v-else-if="viral" class="mt-4 space-y-2 text-sm">
                    <p><strong>{{ $t('maturity.viral.referralCode') }}:</strong> {{ viral.share_code }}</p>
                    <p><strong>{{ $t('maturity.viral.shareLink') }}:</strong> {{ viral.share_url }}</p>
                    <div class="grid gap-2">
                        <p class="rounded-lg border border-border bg-bg p-2">
                            {{ $t('maturity.viral.friendsInvited') }}: {{ viral.invitation_metrics?.friend_invites_sent || 0 }}
                            ({{ $t('maturity.viral.accepted') }} {{ viral.invitation_metrics?.friend_invites_accepted || 0 }},
                            {{ $t('maturity.viral.pending') }} {{ viral.invitation_metrics?.friend_invites_pending || 0 }})
                        </p>
                        <p class="rounded-lg border border-border bg-bg p-2">
                            {{ $t('maturity.viral.teamsInvited') }}: {{ viral.invitation_metrics?.team_invites_sent || 0 }}
                            ({{ $t('maturity.viral.accepted') }} {{ viral.invitation_metrics?.team_invites_accepted || 0 }})
                        </p>
                        <p class="rounded-lg border border-border bg-bg p-2">
                            {{ $t('maturity.viral.friendConversion') }}: {{ formatPercent(viral.invitation_metrics?.friend_conversion_rate || 0) }} |
                            {{ $t('maturity.viral.teamConversion') }}: {{ formatPercent(viral.invitation_metrics?.team_conversion_rate || 0) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                        :disabled="!viral.share_url"
                        @click="copyReferralCode"
                    >
                        {{ copiedShare ? $t('maturity.viral.copied') : $t('maturity.viral.copy') }}
                    </button>
                </div>
                <p v-else class="mt-4 text-sm text-secondary">{{ $t('maturity.viral.empty') }}</p>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ $t('maturity.safety.title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ $t('maturity.safety.subtitle') }}</p>

                <div v-if="loading.safety" class="mt-4 text-sm text-secondary">{{ $t('maturity.safety.loading') }}</div>
                <p v-else-if="errors.safety" class="mt-4 text-sm text-error">{{ errors.safety }}</p>
                <div v-else-if="safety" class="mt-4 space-y-2 text-sm">
                    <p><strong>{{ $t('maturity.safety.trustScore') }}:</strong> {{ parseInteger(safety.trust_score || 0, 0) }}/100</p>
                    <p><strong>{{ $t('maturity.safety.riskLevel') }}:</strong> {{ safety.risk_level }}</p>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="font-semibold text-primary">{{ $t('maturity.safety.riskFactors') }}</p>
                        <p>{{ $t('maturity.safety.openReports') }}: {{ safety.risk_factors?.open_reports || 0 }}</p>
                        <p>{{ $t('maturity.safety.flags') }}: {{ safety.risk_factors?.open_flags || 0 }}</p>
                        <p>{{ $t('maturity.safety.warningPoints') }}: {{ safety.risk_factors?.account_warnings || 0 }}</p>
                    </div>
                    <ul class="space-y-1 pl-5 text-xs text-secondary">
                        <li v-for="(note, index) in safety.recommendations || []" :key="`${note}-${index}`">
                            {{ note }}
                        </li>
                    </ul>
                </div>
                <p v-else class="mt-4 text-sm text-secondary">{{ $t('maturity.safety.empty') }}</p>
            </article>
        </section>
    </div>
</template>
