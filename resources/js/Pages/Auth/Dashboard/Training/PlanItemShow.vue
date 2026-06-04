<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    plan: { type: Object, required: true },
    item: { type: Object, required: true },
})

const completedLogs = computed(() => (props.item.logs || []).filter((log) => log.status === 'completed'))
const missedLogs = computed(() => (props.item.logs || []).filter((log) => log.status === 'missed'))
const totalMinutes = computed(() => completedLogs.value.reduce((sum, log) => sum + Number(log.duration_minutes || 0), 0))

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
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
    <Head :title="item.title" />

    <div class="space-y-6">
        <section class="rounded-2xl border border-border bg-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Plan-Einheit</p>
                    <h1 class="mt-2 text-2xl font-semibold text-primary">{{ item.title }}</h1>
                    <p class="mt-2 text-sm text-secondary">{{ plan.title }} · {{ item.scheduled_at ? formatDate(item.scheduled_at) : 'ohne Termin' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('auth.training.index')" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        Zurück
                    </Link>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="documentItem">
                        Dokumentieren
                    </button>
                </div>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-[220px_1fr]">
                <img v-if="item.image_url" :src="item.image_url" alt="" class="h-52 w-full rounded-2xl object-cover" />
                <div v-else class="flex h-52 items-center justify-center rounded-2xl border border-border bg-inputBg text-5xl text-secondary">
                    <i class="las la-running"></i>
                </div>
                <div class="space-y-4">
                    <p class="text-sm leading-6 text-secondary">{{ item.description || 'Keine Beschreibung hinterlegt.' }}</p>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">Dauer</p>
                            <p class="mt-1 text-lg font-semibold text-primary">{{ formatDuration(item.duration_minutes) }}</p>
                        </div>
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">Distanz</p>
                            <p class="mt-1 text-lg font-semibold text-primary">{{ formatDistance(item.distance_meters) }}</p>
                        </div>
                        <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">Belastung</p>
                            <p class="mt-1 text-lg font-semibold text-primary">{{ item.metrics?.Belastung || item.intensity || '-' }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="(value, key) in item.metrics || {}" :key="key" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                            {{ key }}: {{ value }}
                        </span>
                    </div>
                    <a v-if="item.video_url" :href="item.video_url" target="_blank" class="inline-flex rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        Video öffnen
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-2xl font-semibold text-primary">{{ completedLogs.length }}</p>
                <p class="text-xs text-secondary">abgeschlossen</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-2xl font-semibold text-primary">{{ missedLogs.length }}</p>
                <p class="text-xs text-secondary">nicht gemacht</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-2xl font-semibold text-primary">{{ formatDuration(totalMinutes) }}</p>
                <p class="text-xs text-secondary">dokumentierte Zeit</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-4">
                <p class="text-2xl font-semibold text-primary">{{ item.logs?.length || 0 }}</p>
                <p class="text-xs text-secondary">Logs gesamt</p>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card">
            <div class="border-b border-border p-4">
                <h2 class="text-lg font-semibold text-primary">Dokumentationen und Feedback-Kontext</h2>
            </div>
            <div class="divide-y divide-border">
                <article v-for="log in item.logs || []" :key="log.id" class="grid gap-3 p-4 lg:grid-cols-[1fr_160px]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ log.status }}</span>
                            <span class="text-sm font-semibold text-primary">{{ log.athlete?.name || 'Sportler' }}</span>
                            <span class="text-xs text-secondary">{{ formatDate(log.performed_at || log.created_at) }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-secondary">{{ log.notes || 'Keine Notiz.' }}</p>
                        <div v-if="log.entries?.length" class="mt-3 flex flex-wrap gap-2">
                            <span v-for="entry in log.entries.slice(0, 6)" :key="entry.id" class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                                {{ entry.title }}
                            </span>
                        </div>
                    </div>
                    <Link :href="route('auth.training.logs.show', log.id)" class="self-start rounded-xl border border-border px-4 py-2 text-center text-sm font-semibold text-primary hover:bg-muted">
                        Log ansehen
                    </Link>
                </article>
                <p v-if="!item.logs?.length" class="p-4 text-sm text-secondary">Noch keine Dokumentation für diese Einheit.</p>
            </div>
        </section>
    </div>
</template>


