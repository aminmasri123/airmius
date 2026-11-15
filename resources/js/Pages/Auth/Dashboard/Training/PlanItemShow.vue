<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { exerciseLanguages, localizedExercise, useExerciseCopy } from '@/support/exerciseContent'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n()
const tx = (value, params = {}) => t(value, params)
const ec = useExerciseCopy()

const props = defineProps({
    plan: { type: Object, required: true },
    item: { type: Object, required: true },
})
const displayItem = computed(() => localizedExercise(props.item, locale.value))

const completedLogs = computed(() => (props.item.logs || []).filter((log) => log.status === 'completed'))
const missedLogs = computed(() => (props.item.logs || []).filter((log) => log.status === 'missed'))
const totalMinutes = computed(() => completedLogs.value.reduce((sum, log) => sum + Number(log.duration_minutes || 0), 0))
const structuredMetricKeys = [
    'Abschnitt',
    'Trainingsziel',
    'Niveau',
    'Equipment',
    'Woche',
    'Belastung',
    'Fokus',
    '_training_type',
    'training_type',
    'Trainingstyp',
]

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
}

const formatDuration = (minutes) => {
    const value = Number(minutes || 0)
    if (!value) return '-'
    if (value < 60) return `${value} min`
    const hours = Math.floor(value / 60)
    const rest = value % 60
    return rest ? `${hours} h ${rest} min` : `${hours} h`
}

const formatDistance = (meters) => {
    if (!meters) return '-'
    return `${(Number(meters) / 1000).toFixed(2).replace('.', ',')} km`
}

const documentItem = () => {
    router.visit(route('auth.training.logs.create', { plan_item_id: props.item.id }))
}
</script>

<template>
    <Head :title="displayItem.title" />

    <div class="space-y-6">
        <section class="rounded-2xl border border-border bg-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('Plan-Einheit') }}</p>
                    <h1 class="mt-2 text-2xl font-semibold text-primary">{{ displayItem.title }}</h1>
                    <p class="mt-2 text-sm text-secondary">{{ plan.title }} · {{ displayItem.scheduled_at ? formatDate(displayItem.scheduled_at) : tx('ohne Termin') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="plan.can_write" :href="route('auth.training.plans.items.edit', [plan.id, item.id])" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">{{ ec('edit') }}</Link>
                    <Link :href="route('auth.training.index')" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        {{ tx('Zurück') }}
                    </Link>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="documentItem">
                        {{ tx('Dokumentieren') }}
                    </button>
                </div>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-[220px_1fr]">
                <img v-if="displayItem.image_url" :src="displayItem.image_url" alt="" class="h-52 w-full rounded-2xl object-cover" />
                <div v-else class="flex h-52 items-center justify-center rounded-2xl border border-border bg-inputBg text-5xl text-secondary">
                    <i class="las la-running"></i>
                </div>
                <div class="space-y-4">
                    <p v-if="displayItem.uses_original_fallback" class="rounded-xl bg-muted p-3 text-sm text-secondary">{{ ec('fallback') }} {{ exerciseLanguages[displayItem.original_locale] || displayItem.original_locale }}</p>
                    <p class="whitespace-pre-line text-sm leading-6 text-secondary">{{ displayItem.description || tx('Keine Beschreibung hinterlegt.') }}</p>
                    <div v-for="field in ['instructions', 'notes']" v-show="displayItem[field]" :key="field" class="space-y-2"><h2 class="font-semibold text-primary">{{ ec(field) }}</h2><p class="whitespace-pre-line text-sm leading-6 text-secondary">{{ displayItem[field] }}</p></div>
                    <div v-if="displayItem.todos?.length" class="space-y-2"><h2 class="font-semibold text-primary">{{ ec('todos') }}</h2><ul class="list-inside list-disc text-sm text-secondary"><li v-for="(todo, index) in displayItem.todos" :key="index">{{ todo }}</li></ul></div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('Dauer') }}</p>
                            <p class="mt-1 text-lg font-semibold text-primary">{{ formatDuration(displayItem.duration_minutes) }}</p>
                        </div>
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('Distanz') }}</p>
                            <p class="mt-1 text-lg font-semibold text-primary">{{ formatDistance(displayItem.distance_meters) }}</p>
                        </div>
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tx('Belastung') }}</p>
                            <p class="mt-1 text-lg font-semibold text-primary">{{ displayItem.metrics?.Belastung || displayItem.intensity || '-' }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span v-if="displayItem.metrics?.Abschnitt" class="rounded-full border border-air-blue/30 bg-air-blue/10 px-3 py-1 text-xs font-semibold text-air-blue">{{ displayItem.metrics.Abschnitt }}</span>
                        <span v-if="displayItem.metrics?.Trainingsziel" class="rounded-full border border-success/30 bg-success/10 px-3 py-1 text-xs font-semibold text-success">{{ displayItem.metrics.Trainingsziel }}</span>
                        <span v-if="displayItem.metrics?.Niveau" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ displayItem.metrics.Niveau }}</span>
                        <span v-if="displayItem.metrics?.Equipment" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ displayItem.metrics.Equipment }}</span>
                        <span v-for="(value, key) in displayItem.metrics || {}" v-show="!structuredMetricKeys.includes(key)" :key="key" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                            {{ key }}: {{ value }}
                        </span>
                    </div>
                    <a v-if="displayItem.video_url" :href="displayItem.video_url" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        {{ tx('Video öffnen') }}
                    </a>
                    <Link v-if="displayItem.sport_route" :href="displayItem.sport_route.navigation_url" class="flex items-center justify-between gap-3 rounded-2xl border border-air-blue/35 bg-air-blue/10 p-4 text-primary hover:border-air-blue">
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('training_workspace.route_link.label') }}</span>
                            <span class="mt-1 block truncate font-semibold">{{ displayItem.sport_route.title }}</span>
                            <span class="mt-1 block text-xs text-secondary">{{ formatDistance(displayItem.sport_route.distance_meters) }}</span>
                        </span>
                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-air-blue">{{ tx('training_workspace.route_link.navigate') }} <i class="las la-arrow-right"></i></span>
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="displayItem.explanation_images?.length" class="rounded-2xl border border-border bg-card p-5">
            <h2 class="mb-4 text-lg font-semibold text-primary">{{ ec('images') }}</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <figure v-for="image in displayItem.explanation_images" :key="image.id" class="space-y-2">
                    <a :href="image.url" target="_blank" rel="noopener noreferrer"><img :src="image.url" :alt="image.alt || image.caption || ''" loading="lazy" class="max-h-96 w-full rounded-xl bg-inputBg object-contain" /></a>
                    <figcaption v-if="image.caption" class="text-sm text-secondary">{{ image.caption }}</figcaption>
                </figure>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-2xl font-semibold text-primary">{{ completedLogs.length }}</p>
                <p class="text-xs text-secondary">{{ tx('abgeschlossen') }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-2xl font-semibold text-primary">{{ missedLogs.length }}</p>
                <p class="text-xs text-secondary">{{ tx('nicht gemacht') }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-2xl font-semibold text-primary">{{ formatDuration(totalMinutes) }}</p>
                <p class="text-xs text-secondary">{{ tx('dokumentierte Zeit') }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-2xl font-semibold text-primary">{{ displayItem.logs?.length || 0 }}</p>
                <p class="text-xs text-secondary">{{ tx('Logs gesamt') }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card">
            <div class="border-b border-border p-4">
                <h2 class="text-lg font-semibold text-primary">{{ tx('Dokumentationen und Feedback-Kontext') }}</h2>
            </div>
            <div class="divide-y divide-border">
                <article v-for="log in displayItem.logs || []" :key="log.id" class="grid gap-3 p-4 lg:grid-cols-[1fr_160px]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ log.status }}</span>
                            <span class="text-sm font-semibold text-primary">{{ log.athlete?.name || tx('Sportler') }}</span>
                            <span class="text-xs text-secondary">{{ formatDate(log.performed_at || log.created_at) }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-secondary">{{ log.notes || tx('Keine Notiz.') }}</p>
                        <div v-if="log.entries?.length" class="mt-3 flex flex-wrap gap-2">
                            <span v-for="entry in log.entries.slice(0, 6)" :key="entry.id" class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                                {{ entry.title }}
                            </span>
                        </div>
                    </div>
                    <Link :href="route('auth.training.logs.show', log.id)" class="self-start rounded-xl border border-border px-4 py-2 text-center text-sm font-semibold text-primary hover:bg-muted">
                        {{ tx('Log ansehen') }}
                    </Link>
                </article>
                <p v-if="!displayItem.logs?.length" class="p-4 text-sm text-secondary">{{ tx('Noch keine Dokumentation für diese Einheit.') }}</p>
            </div>
        </section>
    </div>
</template>
