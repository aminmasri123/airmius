<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    dashboard: { type: Object, default: () => ({}) },
})

const page = usePage()
const storage = computed(() => page.props.auth?.user?.storage_usage || null)
const showCustomize = ref(false)
const defaultWidgetKeys = ['training', 'focus', 'nutrition', 'events', 'sport_map', 'files', 'notifications']
const visibleWidgetKeys = ref([...defaultWidgetKeys])
const preferencesLoaded = ref(false)
const legacyStorageKey = 'airmius.dashboard.widgets.v2'
const storageKey = computed(() => `airmius.dashboard.widgets.v3.${page.props.auth?.user?.id || 'guest'}`)
let preferencesSaveTimer = null

const widgets = [
    { key: 'training', label: 'Training', icon: 'las la-running' },
    { key: 'focus', label: 'Heute wichtig', icon: 'las la-bolt' },
    { key: 'nutrition', label: 'Ernährung', icon: 'las la-utensils' },
    { key: 'events', label: 'Termine', icon: 'las la-calendar-check' },
    { key: 'sport_map', label: 'Sportkarte', icon: 'las la-map-marked-alt' },
    { key: 'files', label: 'Dateien', icon: 'las la-folder-open' },
    { key: 'notifications', label: 'Inbox', icon: 'las la-bell' },
]

const userName = computed(() => props.dashboard?.profile?.name || page.props.auth?.user?.first_name || page.props.auth?.user?.name || 'Sportler')
const training = computed(() => props.dashboard?.training || {})
const events = computed(() => props.dashboard?.events || {})
const nutrition = computed(() => props.dashboard?.nutrition || {})
const sportMap = computed(() => props.dashboard?.sport_map || {})
const files = computed(() => props.dashboard?.files || {})
const notifications = computed(() => props.dashboard?.notifications || {})
const focusItems = computed(() => props.dashboard?.focus || [])
const trainingChart = computed(() => training.value.chart || [])
const nutritionChart = computed(() => nutrition.value.chart || [])
const chartMaxMinutes = computed(() => Math.max(1, ...trainingChart.value.map((item) => Number(item.minutes || 0))))
const chartMaxCalories = computed(() => Math.max(1, ...nutritionChart.value.map((item) => Number(item.calories || 0))))
const hasTrainingChart = computed(() => trainingChart.value.some((item) => Number(item.minutes || 0) > 0))
const hasNutritionChart = computed(() => nutritionChart.value.some((item) => Number(item.calories || 0) > 0))

const stats = computed(() => [
    {
        key: 'trainings',
        label: 'Trainings diese Woche',
        value: formatNumber(training.value.week_count || 0),
        meta: trendLabel(training.value.trend_percent),
        icon: 'las la-running',
        tone: 'from-sky-500 to-cyan-400',
    },
    {
        key: 'minutes',
        label: 'Trainingszeit',
        value: `${formatNumber(training.value.week_minutes || 0)} min`,
        meta: `${formatNumber(training.value.active_days || 0)} aktive Tage`,
        icon: 'las la-stopwatch',
        tone: 'from-emerald-500 to-lime-400',
    },
    {
        key: 'fitness',
        label: 'Aktivitätswert',
        value: `${formatNumber(training.value.activity_score || 0)}%`,
        meta: `${formatNumber(training.value.streak_days || 0)} Tage Serie`,
        icon: 'las la-chart-line',
        tone: 'from-violet-500 to-fuchsia-400',
    },
    {
        key: 'storage',
        label: 'Speicher frei',
        value: storage.value ? formatBytes(storage.value.remaining_bytes) : formatBytes(files.value.bytes || 0),
        meta: storage.value ? `${storage.value.used_percent}% genutzt` : `${formatNumber(files.value.count || 0)} Dateien`,
        icon: 'las la-database',
        tone: 'from-amber-400 to-orange-500',
    },
])

const quickActions = computed(() => [
    {
        title: 'Training',
        subtitle: 'Dokumentieren',
        href: route('auth.training.logs.create'),
        icon: 'las la-clipboard-check',
        tone: 'bg-sky-500/15 text-sky-200 border-sky-400/30',
    },
    {
        title: 'Route',
        subtitle: 'Planen',
        href: route('auth.sport-map.index'),
        icon: 'las la-route',
        tone: 'bg-emerald-500/15 text-emerald-200 border-emerald-400/30',
    },
    {
        title: 'Ernährung',
        subtitle: 'Eintragen',
        href: route('auth.nutrition.index'),
        icon: 'las la-utensils',
        tone: 'bg-orange-500/15 text-orange-100 border-orange-400/30',
    },
    {
        title: 'Plan',
        subtitle: 'Öffnen',
        href: route('auth.training.index'),
        icon: 'las la-calendar-plus',
        tone: 'bg-violet-500/15 text-violet-100 border-violet-400/30',
    },
])

const isWidgetVisible = (key) => visibleWidgetKeys.value.includes(key)
const toggleWidget = (key) => {
    if (isWidgetVisible(key)) {
        visibleWidgetKeys.value = visibleWidgetKeys.value.filter((item) => item !== key)
        return
    }

    visibleWidgetKeys.value = [...visibleWidgetKeys.value, key]
}

const validWidgetKeys = computed(() => widgets.map((widget) => widget.key))

const normalizeWidgetKeys = (keys) => {
    if (!Array.isArray(keys)) return null

    return keys.filter((key, index) => validWidgetKeys.value.includes(key) && keys.indexOf(key) === index)
}

const readStoredWidgetKeys = () => {
    try {
        const saved = window.localStorage.getItem(storageKey.value)
        const legacySaved = saved === null ? window.localStorage.getItem(legacyStorageKey) : null
        const raw = saved ?? legacySaved

        if (raw === null) return null

        const parsed = normalizeWidgetKeys(JSON.parse(raw))

        if (legacySaved !== null) {
            window.localStorage.removeItem(legacyStorageKey)
        }

        return parsed
    } catch (error) {
        window.localStorage.removeItem(storageKey.value)
        window.localStorage.removeItem(legacyStorageKey)
        return null
    }
}

const storeWidgetKeysLocally = (keys) => {
    try {
        window.localStorage.setItem(storageKey.value, JSON.stringify(keys))
    } catch (error) {
        // The backend persistence still keeps the preference when local storage is unavailable.
    }
}

const syncWidgetPreferences = (keys) => {
    window.clearTimeout(preferencesSaveTimer)
    preferencesSaveTimer = window.setTimeout(() => {
        if (!window.axios) return

        window.axios.patch(route('auth.dashboard.preferences.update'), {
            widget_keys: keys,
        }).catch(() => {
            // Local storage is the offline fallback; the next change will try syncing again.
        })
    }, 350)
}

const barHeight = (value, max) => `${Math.max(10, Math.round((Number(value || 0) / max) * 100))}%`

const formatNumber = (value) => new Intl.NumberFormat('de-DE').format(Number(value || 0))

const formatBytes = (bytes) => {
    const value = Number(bytes || 0)

    if (value < 1024) return `${value} B`
    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`
    if (value < 1024 * 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`

    return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
}

const formatDistance = (meters) => {
    const value = Number(meters || 0)

    if (value <= 0) return '0 km'

    return `${(value / 1000).toLocaleString('de-DE', { maximumFractionDigits: value >= 10000 ? 0 : 1 })} km`
}

const formatDateTime = (value) => {
    if (!value) return 'Kein Termin'

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const trendLabel = (value) => {
    const trend = Number(value || 0)

    if (trend > 0) return `+${trend}% zur Vorwoche`
    if (trend < 0) return `${trend}% zur Vorwoche`

    return 'stabil zur Vorwoche'
}

const notificationTime = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

onMounted(() => {
    const serverKeys = normalizeWidgetKeys(props.dashboard?.preferences?.widgets)
    const storedKeys = serverKeys ?? readStoredWidgetKeys()

    if (storedKeys !== null) {
        visibleWidgetKeys.value = storedKeys
    }

    storeWidgetKeysLocally(visibleWidgetKeys.value)
    preferencesLoaded.value = true
})

watch(visibleWidgetKeys, (keys) => {
    if (!preferencesLoaded.value) return

    const normalized = normalizeWidgetKeys(keys) ?? [...defaultWidgetKeys]

    if (normalized.length !== keys.length || normalized.some((key, index) => key !== keys[index])) {
        visibleWidgetKeys.value = normalized
        return
    }

    storeWidgetKeysLocally(normalized)
    syncWidgetPreferences(normalized)
}, { deep: true })
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <div class="space-y-4 pb-20 md:space-y-6">
        <section class="surface-card overflow-hidden">
            <div class="relative isolate p-4 sm:p-6">
                <div class="absolute inset-x-0 top-0 -z-10 h-28 bg-gradient-to-r from-air-blue/25 via-emerald-400/15 to-fuchsia-500/20"></div>
                <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-wide text-air-blue">Dashboard</p>
                        <h1 class="mt-1 truncate text-2xl font-black text-primary sm:text-3xl">Hallo {{ userName }}</h1>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-secondary">
                            Deine wichtigsten Werte, Aufgaben und Schnellstarts auf einen Blick.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold text-primary transition hover:border-air-blue hover:text-air-blue sm:w-auto"
                        @click="showCustomize = !showCustomize"
                    >
                        <i class="las la-sliders-h text-lg"></i>
                        Dashboard anpassen
                    </button>
                </div>

                <div
                    v-if="showCustomize"
                    class="mt-4 rounded-2xl border border-border bg-inputBg/70 p-3 shadow-inner shadow-black/10 sm:p-4"
                >
                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-primary">Widgets</p>
                            <p class="text-xs text-secondary">Wähle aus, was auf deinem Dashboard sichtbar ist.</p>
                        </div>
                        <button type="button" class="w-full rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted sm:w-auto" @click="visibleWidgetKeys = widgets.map((widget) => widget.key)">
                            Alles zeigen
                        </button>
                    </div>
                    <div class="mt-4 flex min-w-0 flex-wrap gap-2">
                        <button
                            v-for="widget in widgets"
                            :key="widget.key"
                            type="button"
                            class="inline-flex min-w-0 max-w-full items-center gap-2 rounded-full border px-3 py-2 text-xs font-bold transition"
                            :class="isWidgetVisible(widget.key) ? 'border-air-blue bg-air-blue/15 text-air-blue' : 'border-border bg-card text-secondary'"
                            @click="toggleWidget(widget.key)"
                        >
                            <i :class="[widget.icon, 'shrink-0 text-base']"></i>
                            <span class="truncate">{{ widget.label }}</span>
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="action in quickActions"
                        :key="action.title"
                        :href="action.href"
                        class="min-w-0 rounded-2xl border p-3 transition hover:-translate-y-0.5 hover:border-air-blue sm:p-4"
                        :class="action.tone"
                    >
                        <i :class="[action.icon, 'text-xl sm:text-2xl']"></i>
                        <p class="mt-2 truncate text-sm font-black sm:mt-3">{{ action.title }}</p>
                        <p class="truncate text-xs font-semibold opacity-80">{{ action.subtitle }}</p>
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div v-for="item in stats" :key="item.key" class="surface-card overflow-hidden p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-secondary">{{ item.label }}</p>
                        <p class="mt-2 text-2xl font-black text-primary">{{ item.value }}</p>
                        <p class="mt-1 text-xs font-semibold text-secondary">{{ item.meta }}</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg" :class="item.tone">
                        <i :class="[item.icon, 'text-xl']"></i>
                    </span>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-12">
            <div v-if="isWidgetVisible('training')" class="surface-card p-4 sm:p-5 xl:col-span-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-air-blue">Wochenübersicht</p>
                        <h2 class="mt-1 text-xl font-black text-primary">Training</h2>
                    </div>
                    <Link :href="route('auth.training.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                        Öffnen
                    </Link>
                </div>

                <div class="mt-5 h-44">
                    <div v-if="hasTrainingChart" class="flex h-full items-end gap-2">
                        <div v-for="day in trainingChart" :key="day.date" class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <div class="flex h-32 w-full items-end rounded-full bg-inputBg/80 px-1">
                                <div
                                    class="w-full rounded-full bg-gradient-to-t from-air-blue to-cyan-300"
                                    :style="{ height: barHeight(day.minutes, chartMaxMinutes) }"
                                    :title="`${day.minutes} min`"
                                ></div>
                            </div>
                            <span class="text-[11px] font-semibold text-secondary">{{ day.label }}</span>
                        </div>
                    </div>
                    <div v-else class="flex h-full items-center justify-center rounded-2xl border border-dashed border-border text-center text-sm text-secondary">
                        Noch keine Trainingsdaten für diese Woche.
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p class="text-lg font-black text-primary">{{ formatDistance(training.week_distance_meters) }}</p>
                        <p class="text-xs text-secondary">Distanz</p>
                    </div>
                    <div>
                        <p class="text-lg font-black text-primary">{{ formatNumber(training.week_calories) }}</p>
                        <p class="text-xs text-secondary">Kalorien</p>
                    </div>
                    <div>
                        <p class="text-lg font-black text-primary">{{ formatNumber(training.plan_count) }}</p>
                        <p class="text-xs text-secondary">Pläne</p>
                    </div>
                </div>
            </div>

            <div v-if="isWidgetVisible('focus')" class="surface-card p-4 sm:p-5 xl:col-span-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-air-blue">Heute wichtig</p>
                        <h2 class="mt-1 text-xl font-black text-primary">Nächste Schritte</h2>
                    </div>
                    <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-bold text-air-blue">{{ focusItems.length }}</span>
                </div>

                <div v-if="focusItems.length" class="mt-5 divide-y divide-border">
                    <Link
                        v-for="item in focusItems"
                        :key="`${item.title}-${item.body}`"
                        :href="item.href"
                        class="flex items-center gap-3 py-3 transition hover:text-air-blue"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-inputBg text-air-blue">
                            <i :class="[item.icon, 'text-xl']"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-black text-primary">{{ item.title }}</span>
                            <span class="block truncate text-xs text-secondary">{{ item.body }}</span>
                        </span>
                        <span class="text-right text-xs font-semibold text-secondary">{{ item.meta }}</span>
                    </Link>
                </div>
                <div v-else class="mt-5 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-secondary">
                    Alles ruhig. Du hast gerade keine offenen Punkte.
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <div v-if="isWidgetVisible('nutrition')" class="surface-card p-4 sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-orange-300">Ernährung</p>
                        <h2 class="mt-1 text-lg font-black text-primary">Heute</h2>
                    </div>
                    <Link :href="route('auth.nutrition.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                        Öffnen
                    </Link>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p class="text-lg font-black text-primary">{{ formatNumber(nutrition.today_calories) }}</p>
                        <p class="text-xs text-secondary">kcal</p>
                    </div>
                    <div>
                        <p class="text-lg font-black text-primary">{{ formatNumber(Math.round(nutrition.today_protein_g || 0)) }} g</p>
                        <p class="text-xs text-secondary">Protein</p>
                    </div>
                    <div>
                        <p class="text-lg font-black text-primary">{{ formatNumber(nutrition.meals_today) }}</p>
                        <p class="text-xs text-secondary">Mahlzeiten</p>
                    </div>
                </div>

                <div class="mt-5 h-24">
                    <div v-if="hasNutritionChart" class="flex h-full items-end gap-2">
                        <div v-for="day in nutritionChart" :key="day.date" class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                            <div class="flex h-16 w-full items-end rounded-full bg-inputBg/80 px-1">
                                <div
                                    class="w-full rounded-full bg-gradient-to-t from-orange-500 to-amber-300"
                                    :style="{ height: barHeight(day.calories, chartMaxCalories) }"
                                ></div>
                            </div>
                            <span class="text-[10px] font-semibold text-secondary">{{ day.label }}</span>
                        </div>
                    </div>
                    <div v-else class="flex h-full items-center justify-center rounded-xl border border-dashed border-border text-center text-xs text-secondary">
                        Noch keine Mahlzeiten eingetragen.
                    </div>
                </div>
            </div>

            <div v-if="isWidgetVisible('events')" class="surface-card p-4 sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-300">Termine</p>
                        <h2 class="mt-1 text-lg font-black text-primary">{{ formatNumber(events.upcoming_count) }} geplant</h2>
                    </div>
                    <Link :href="route('auth.events.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                        Kalender
                    </Link>
                </div>

                <div v-if="events.next?.length" class="mt-4 divide-y divide-border">
                    <Link v-for="event in events.next.slice(0, 3)" :key="event.id" :href="route('auth.events.index')" class="block py-3">
                        <p class="truncate text-sm font-black text-primary">{{ event.title }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ formatDateTime(event.start_time) }} · {{ event.location || 'ohne Ort' }}</p>
                    </Link>
                </div>
                <div v-else class="mt-5 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-secondary">
                    Keine kommenden Termine.
                </div>
            </div>

            <div v-if="isWidgetVisible('sport_map')" class="surface-card p-4 sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-sky-300">Sportkarte</p>
                        <h2 class="mt-1 text-lg font-black text-primary">Routen & Orte</h2>
                    </div>
                    <Link :href="route('auth.sport-map.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                        Karte
                    </Link>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p class="text-lg font-black text-primary">{{ formatNumber(sportMap.routes_count) }}</p>
                        <p class="text-xs text-secondary">Routen</p>
                    </div>
                    <div>
                        <p class="text-lg font-black text-primary">{{ formatNumber(sportMap.tracks_count) }}</p>
                        <p class="text-xs text-secondary">Tracks</p>
                    </div>
                    <div>
                        <p class="text-lg font-black text-primary">{{ formatNumber(sportMap.places_count) }}</p>
                        <p class="text-xs text-secondary">Plätze</p>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-border p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-secondary">Diese Woche getrackt</p>
                    <p class="mt-1 text-2xl font-black text-primary">{{ formatDistance(sportMap.week_track_distance_meters) }}</p>
                    <p class="mt-2 truncate text-xs text-secondary">
                        Letzte Route: {{ sportMap.last_route?.title || 'Noch keine Route' }}
                    </p>
                </div>
            </div>

            <div v-if="isWidgetVisible('files')" class="surface-card p-4 sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-violet-300">Dateien</p>
                        <h2 class="mt-1 text-lg font-black text-primary">Speicher</h2>
                    </div>
                    <Link :href="route('auth.files.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                        Dateien
                    </Link>
                </div>

                <div v-if="storage" class="mt-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-2xl font-black text-primary">{{ formatBytes(storage.remaining_bytes) }}</p>
                        <span class="rounded-full border border-border px-3 py-1 text-xs font-bold text-primary">{{ storage.plan_name }}</span>
                    </div>
                    <p class="mt-1 text-sm text-secondary">frei von {{ storage.limit_gb }} GB</p>
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-inputBg">
                        <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-400" :style="{ width: `${storage.used_percent}%` }"></div>
                    </div>
                    <p class="mt-2 text-xs text-secondary">{{ formatBytes(storage.used_bytes) }} genutzt · {{ storage.used_percent }}%</p>
                </div>
                <div v-else class="mt-5 rounded-2xl border border-border p-4">
                    <p class="text-2xl font-black text-primary">{{ formatBytes(files.bytes) }}</p>
                    <p class="mt-1 text-sm text-secondary">{{ formatNumber(files.count) }} Dateien gespeichert</p>
                </div>
            </div>

            <div v-if="isWidgetVisible('notifications')" class="surface-card p-4 sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-amber-300">Inbox</p>
                        <h2 class="mt-1 text-lg font-black text-primary">{{ formatNumber(notifications.unread_count) }} ungelesen</h2>
                    </div>
                    <Link :href="route('auth.notifications.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                        Öffnen
                    </Link>
                </div>

                <div v-if="notifications.latest?.length" class="mt-4 divide-y divide-border">
                    <Link
                        v-for="item in notifications.latest.slice(0, 3)"
                        :key="item.id"
                        :href="route('auth.notifications.index')"
                        class="block py-3"
                    >
                        <p class="truncate text-sm font-black" :class="item.read ? 'text-secondary' : 'text-primary'">{{ item.title }}</p>
                        <p class="mt-1 truncate text-xs text-secondary">{{ item.body || notificationTime(item.created_at) }}</p>
                    </Link>
                </div>
                <div v-else class="mt-5 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-secondary">
                    Keine neuen Nachrichten.
                </div>
            </div>
        </section>
    </div>
</template>
