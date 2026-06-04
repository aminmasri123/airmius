import { usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const defaultWidgetKeys = ['daily_flow', 'training', 'focus', 'nutrition', 'events', 'sport_map', 'files', 'notifications']
const legacyStorageKey = 'airmius.dashboard.widgets.v2'

export function useDashboardWorkspace(props) {
    const page = usePage()
    const { t, locale } = useI18n()
    const storage = computed(() => page.props.auth?.user?.storage_usage || null)
    const showCustomize = ref(false)
    const visibleWidgetKeys = ref([...defaultWidgetKeys])
    const preferencesLoaded = ref(false)
    const storageKey = computed(() => `airmius.dashboard.widgets.v3.${page.props.auth?.user?.id || 'guest'}`)
    let preferencesSaveTimer = null

    const widgets = [
        { key: 'daily_flow', label: 'Heute', icon: 'las la-compass' },
        { key: 'training', label: 'Training', icon: 'las la-running' },
        { key: 'focus', label: 'Heute wichtig', icon: 'las la-bolt' },
        { key: 'nutrition', label: 'Ernährung', icon: 'las la-utensils' },
        { key: 'events', label: 'Termine', icon: 'las la-calendar-check' },
        { key: 'sport_map', label: 'Sportkarte', icon: 'las la-map-marked-alt' },
        { key: 'files', label: 'Dateien', icon: 'las la-folder-open' },
        { key: 'notifications', label: 'Inbox', icon: 'las la-bell' },
    ]

    const userName = computed(() => props.dashboard?.profile?.name || page.props.auth?.user?.first_name || page.props.auth?.user?.name || 'Sportler')
    const dailyFlow = computed(() => props.dashboard?.daily_flow || {})
    const training = computed(() => props.dashboard?.training || {})
    const events = computed(() => props.dashboard?.events || {})
    const nutrition = computed(() => props.dashboard?.nutrition || {})
    const sportMap = computed(() => props.dashboard?.sport_map || {})
    const files = computed(() => props.dashboard?.files || {})
    const notifications = computed(() => props.dashboard?.notifications || {})
    const focusItems = computed(() => props.dashboard?.focus || [])
    const trainingChart = computed(() => training.value.chart || [])
    const nutritionChart = computed(() => nutrition.value.chart || [])
    const intlLocale = computed(() => ({
        ar: 'ar',
        de: 'de-DE',
        en: 'en-US',
        fr: 'fr-FR',
    }[locale.value] || 'de-DE'))
    const chartMaxMinutes = computed(() => Math.max(1, ...trainingChart.value.map((item) => Number(item.minutes || 0))))
    const chartMaxCalories = computed(() => Math.max(1, ...nutritionChart.value.map((item) => Number(item.calories || 0))))
    const hasTrainingChart = computed(() => trainingChart.value.some((item) => Number(item.minutes || 0) > 0))
    const hasNutritionChart = computed(() => nutritionChart.value.some((item) => Number(item.calories || 0) > 0))

    const stats = computed(() => [
        {
            key: 'trainings',
            label: t('Trainings diese Woche'),
            value: formatNumber(training.value.week_count || 0),
            meta: trendLabel(training.value.trend_percent),
            icon: 'las la-running',
            tone: 'from-sky-500 to-cyan-400',
        },
        {
            key: 'minutes',
            label: t('Trainingszeit'),
            value: `${formatNumber(training.value.week_minutes || 0)} min`,
            meta: t('dashboard.active_days_count', { count: formatNumber(training.value.active_days || 0) }),
            icon: 'las la-stopwatch',
            tone: 'from-emerald-500 to-lime-400',
        },
        {
            key: 'fitness',
            label: t('Aktivitätswert'),
            value: `${formatNumber(training.value.activity_score || 0)}%`,
            meta: t('dashboard.streak_days_count', { count: formatNumber(training.value.streak_days || 0) }),
            icon: 'las la-chart-line',
            tone: 'from-violet-500 to-fuchsia-400',
        },
        {
            key: 'storage',
            label: t('Speicher frei'),
            value: storage.value ? formatBytes(storage.value.remaining_bytes) : formatBytes(files.value.bytes || 0),
            meta: storage.value ? t('dashboard.percent_used', { percent: storage.value.used_percent }) : t('dashboard.files_count', { count: formatNumber(files.value.count || 0) }),
            icon: 'las la-database',
            tone: 'from-amber-400 to-orange-500',
        },
    ])

    const quickActions = computed(() => [
        {
            title: t('Training'),
            subtitle: t('Dokumentieren'),
            href: route('auth.training.logs.create'),
            icon: 'las la-clipboard-check',
            tone: 'bg-sky-500/15 text-sky-200 border-sky-400/30',
        },
        {
            title: t('Route'),
            subtitle: t('Planen'),
            href: route('auth.sport-map.index'),
            icon: 'las la-route',
            tone: 'bg-emerald-500/15 text-emerald-200 border-emerald-400/30',
        },
        {
            title: t('Ernährung'),
            subtitle: t('Eintragen'),
            href: route('auth.nutrition.index'),
            icon: 'las la-utensils',
            tone: 'bg-orange-500/15 text-orange-100 border-orange-400/30',
        },
        {
            title: t('Plan'),
            subtitle: t('Öffnen'),
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
    const showAllWidgets = () => {
        visibleWidgetKeys.value = widgets.map((widget) => widget.key)
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
            // Backend persistence still keeps the preference when local storage is unavailable.
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
    const formatNumber = (value) => new Intl.NumberFormat(intlLocale.value).format(Number(value || 0))
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

        return `${(value / 1000).toLocaleString(intlLocale.value, { maximumFractionDigits: value >= 10000 ? 0 : 1 })} km`
    }
    const formatDateTime = (value) => {
        if (!value) return t('Kein Termin')

        return new Intl.DateTimeFormat(intlLocale.value, {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value))
    }
    const trendLabel = (value) => {
        const trend = Number(value || 0)

        if (trend > 0) return t('dashboard.trend_vs_previous_week', { value: `+${formatNumber(trend)}` })
        if (trend < 0) return t('dashboard.trend_vs_previous_week', { value: formatNumber(trend) })

        return t('stabil zur Vorwoche')
    }
    const notificationTime = (value) => {
        if (!value) return ''

        return new Intl.DateTimeFormat(intlLocale.value, {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value))
    }

    const translatedText = (value) => {
        if (!value) return ''

        return t(String(value))
    }
    const translatedFocusBody = (item) => {
        const body = String(item?.body || '')
        const unreadMatch = body.match(/^(\d+)\s+ungelesen$/)

        if (unreadMatch) {
            return t('dashboard.unread_items', { count: formatNumber(unreadMatch[1]) })
        }

        return translatedText(body)
    }
    const translatedNotificationText = (value) => {
        const text = String(value || '')
        const followSuffix = ' folgt dir jetzt'
        const friendSuffix = ' möchte dich als Freund hinzufügen'
        const messagePrefix = 'Neue Nachricht von '

        if (text.endsWith(followSuffix)) {
            return t('dashboard.notification_follow_title', { name: text.slice(0, -followSuffix.length) })
        }

        if (text.endsWith(friendSuffix)) {
            return t('dashboard.notification_friend_title', { name: text.slice(0, -friendSuffix.length) })
        }

        if (text.startsWith(messagePrefix)) {
            return t('dashboard.notification_message_title', { name: text.slice(messagePrefix.length) })
        }

        return translatedText(text)
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

    return {
        storage,
        showCustomize,
        visibleWidgetKeys,
        widgets,
        userName,
        dailyFlow,
        training,
        events,
        nutrition,
        sportMap,
        files,
        notifications,
        focusItems,
        trainingChart,
        nutritionChart,
        chartMaxMinutes,
        chartMaxCalories,
        hasTrainingChart,
        hasNutritionChart,
        stats,
        quickActions,
        isWidgetVisible,
        toggleWidget,
        showAllWidgets,
        barHeight,
        formatNumber,
        formatBytes,
        formatDistance,
        formatDateTime,
        notificationTime,
        translatedText,
        translatedFocusBody,
        translatedNotificationText,
    }
}

