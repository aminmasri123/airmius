<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    formatDistance: {
        type: Function,
        required: true,
    },
    formatDuration: {
        type: Function,
        required: true,
    },
    routeItem: {
        type: Object,
        required: true,
    },
    sportLabel: {
        type: Function,
        required: true,
    },
    visibilityLabel: {
        type: Function,
        required: true,
    },
})

const { locale } = useI18n()

const copy = {
    de: {
        ready: 'Navigation bereit',
        basic: 'Basisnavigation',
        watch: 'Route prüfen',
        risk: 'Nicht bereit',
        offline: 'Offline',
        warnings: 'Warnungen',
        elevation: 'Höhenprofil',
    },
    en: {
        ready: 'Navigation ready',
        basic: 'Basic navigation',
        watch: 'Check route',
        risk: 'Not ready',
        offline: 'Offline',
        warnings: 'Warnings',
        elevation: 'Elevation',
    },
    fr: {
        ready: 'Navigation prete',
        basic: 'Navigation de base',
        watch: 'Verifier route',
        risk: 'Non pret',
        offline: 'Hors ligne',
        warnings: 'Alertes',
        elevation: 'Denivele',
    },
    ar: {
        ready: 'Ø§Ù„Ù…Ù„Ø§Ø­Ø© Ø¬Ø§Ù‡Ø²Ø©',
        basic: 'Ù…Ù„Ø§Ø­Ø© Ø£Ø³Ø§Ø³ÙŠØ©',
        watch: 'ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„Ù…Ø³Ø§Ø±',
        risk: 'ØºÙŠØ± Ø¬Ø§Ù‡Ø²',
        offline: 'Ø¨Ù„Ø§ Ø§ØªØµØ§Ù„',
        warnings: 'ØªÙ†Ø¨ÙŠÙ‡Ø§Øª',
        elevation: 'Ø§Ù„Ø§Ø±ØªÙØ§Ø¹',
    },
}

const language = computed(() => String(locale.value || 'de').slice(0, 2))
const labels = computed(() => copy[language.value] || copy.de)
const intelligence = computed(() => props.routeItem.route_intelligence || props.routeItem.metrics?.route_intelligence || {})
const navigationReadiness = computed(() => intelligence.value.navigation_readiness || {})
const offlinePack = computed(() => intelligence.value.offline_pack || {})
const safetyWarnings = computed(() => intelligence.value.safety_warnings || [])
const elevationProfile = computed(() => intelligence.value.elevation_profile || [])
const readinessLabel = computed(() => labels.value[navigationReadiness.value.level] || labels.value.basic)
const readinessClass = computed(() => ({
    ready: 'bg-success/10 text-success',
    basic: 'bg-buttonPrimary/10 text-buttonPrimary',
    watch: 'bg-warning/10 text-warning',
    risk: 'bg-error/10 text-error',
}[navigationReadiness.value.level] || 'bg-muted text-secondary'))
</script>

<template>
    <article class="rounded-lg border border-border bg-inputBg p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-primary">{{ routeItem.title }}</p>
                <p class="mt-1 text-xs text-secondary">
                    {{ sportLabel(routeItem.sport_type) }} - {{ visibilityLabel(routeItem.visibility) }} - {{ $t('sport_map.routes.cues_count', { count: routeItem.navigation_cues?.length || 0 }) }}
                </p>
            </div>
            <span class="shrink-0 rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary">
                {{ formatDistance(routeItem.distance_meters) }}
            </span>
        </div>

        <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-secondary">
            <span>{{ formatDuration(routeItem.estimated_duration_seconds) }}</span>
            <span>{{ $t('sport_map.elevation_gain_meters', { meters: routeItem.elevation_gain_meters || 0 }) }}</span>
            <span>{{ $t('sport_map.routes.tracks_count', { count: routeItem.tracks_count || 0 }) }}</span>
        </div>

        <div v-if="Object.keys(intelligence).length" class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
            <span class="rounded-lg px-3 py-2 font-bold" :class="readinessClass">{{ readinessLabel }}</span>
            <span class="rounded-lg bg-card px-3 py-2 text-secondary">
                {{ labels.offline }}: {{ offlinePack.estimated_size_mb || 0 }} MB
            </span>
            <span class="rounded-lg bg-card px-3 py-2 text-secondary">
                {{ labels.warnings }}: {{ safetyWarnings.length }}
            </span>
            <span class="rounded-lg bg-card px-3 py-2 text-secondary">
                {{ labels.elevation }}: {{ elevationProfile.length }}
            </span>
        </div>

        <div v-if="routeItem.gpx?.web_export_url || routeItem.gpx?.export_url" class="mt-3 flex flex-wrap gap-2">
            <a
                :href="routeItem.gpx?.web_export_url || routeItem.gpx?.export_url"
                class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs font-bold text-primary hover:border-buttonPrimary hover:text-buttonPrimary"
            >
                <i class="las la-file-download"></i>
                {{ $t('sport_map.gpx.export') }}
            </a>
        </div>
    </article>
</template>

