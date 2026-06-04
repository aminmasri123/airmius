<script setup>
defineProps({
    visibleLogs: { type: Array, default: () => [] },
    sportAccent: { type: Function, required: true },
    sportIcon: { type: Function, required: true },
    logStatusLabel: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    formatTime: { type: Function, required: true },
    formatDuration: { type: Function, required: true },
    formatDistance: { type: Function, required: true },
})

const emit = defineEmits(['open-log-page'])
</script>

<template>
    <section class="rounded-2xl border border-border bg-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Trainingsdokumentation</p>
                    <h2 class="text-xl font-semibold text-primary">Ist-Einheiten</h2>
                </div>
                <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('open-log-page')">
                    Training dokumentieren
                </button>
            </div>
            <div class="divide-y divide-border">
                <article v-for="log in visibleLogs.slice(0, 8)" :key="log.id" class="grid gap-3 p-4 lg:grid-cols-[1.2fr_1fr_auto] lg:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl text-white" :class="sportAccent(log.sport_type)">
                                <i :class="sportIcon(log.sport_type)"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate text-base font-semibold text-primary">{{ log.title }}</h3>
                                    <span
                                        v-if="log.status === 'draft'"
                                        class="rounded-full border border-success/40 bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success"
                                    >
                                        {{ logStatusLabel(log.status) }}
                                    </span>
                                </div>
                                <p class="text-xs text-secondary">
                                    {{ sportLabel(log.sport_type) }} · {{ formatDate(log.performed_at) }} {{ formatTime(log.performed_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs text-secondary">
                        <span class="rounded-lg border border-border px-2 py-1">{{ formatDuration(log.duration_minutes) }}</span>
                        <span class="rounded-lg border border-border px-2 py-1">{{ formatDistance(log.distance_meters) }}</span>
                        <span class="rounded-lg border border-border px-2 py-1">{{ log.entries?.length || 0 }} Übungen</span>
                    </div>
                    <div class="text-sm text-secondary lg:text-right">
                        <p class="font-semibold text-primary">{{ log.athlete?.name || 'Ich' }}</p>
                        <p v-if="log.trainer">durch {{ log.trainer.name }}</p>
                        <p v-else>{{ log.plan_item ? `Plan: ${log.plan_item.title}` : 'Spontan' }}</p>
                        <button v-if="log.status === 'draft'" type="button" class="mt-2 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="emit('open-log-page')">
                            Weiter bearbeiten
                        </button>
                    </div>
                </article>
                <p v-if="!visibleLogs.length" class="p-4 text-sm text-secondary">Noch keine Trainings dokumentiert.</p>
            </div>
        </section>
</template>


