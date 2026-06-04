<script setup>
defineProps({
    selectedType: { type: Object, required: true },
    usesGymSets: { type: Boolean, default: false },
    detailSummary: { type: String, default: '' },
    documentationScore: { type: Number, default: 0 },
    documentationScoreClass: { type: String, default: '' },
    documentationChecklist: { type: Array, default: () => [] },
    isLiveTraining: { type: Boolean, default: false },
    liveElapsedLabel: { type: String, default: '' },
    sessionMinutes: { type: Number, default: 0 },
    sessionDistanceKm: { type: Number, default: 0 },
    sessionPace: { type: String, default: '' },
    sessionSpeedKmh: { type: String, default: '' },
    gymVolumeKg: { type: Number, default: 0 },
    restSeconds: { type: Number, default: 0 },
    restTimerLabel: { type: String, default: '' },
    form: { type: Object, required: true },
    formatNumber: { type: Function, required: true },
})

defineEmits(['set-duration-from-live', 'finish-live', 'stop-rest'])
</script>

<template>
    <aside class="hidden min-w-0 space-y-4 2xl:sticky 2xl:top-20 2xl:col-start-2 2xl:row-start-2 2xl:block 2xl:self-start">
        <section class="rounded-2xl border border-border bg-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Aktive Vorlage</p>
            <h2 class="mt-1 text-lg font-semibold text-primary">{{ selectedType.label }}</h2>
            <p class="mt-2 text-sm leading-6 text-secondary">
                {{ usesGymSets ? 'Erfasse zuerst die Übung und darunter jeden Satz einzeln mit eigenen Wiederholungen und Gewicht.' : 'Die Felder passen sich der Trainingsart an. Bei Long Run sind Abschnitte optional, falls du Tempo- oder KilometerBlöcke dokumentieren willst.' }}
            </p>
            <div class="mt-4 rounded-xl border border-border bg-inputBg/40 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Moment</p>
                <p class="mt-1 text-sm font-semibold text-primary">{{ detailSummary }}</p>
            </div>
            <div class="mt-3 rounded-xl border border-border bg-inputBg/40 p-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Qualitätscheck</p>
                    <p class="text-sm font-semibold" :class="documentationScoreClass">{{ documentationScore }}%</p>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-air-blue" :style="{ width: `${documentationScore}%` }"></div>
                </div>
                <div class="mt-3 grid gap-1.5">
                    <div v-for="item in documentationChecklist" :key="item.label" class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-secondary">{{ item.label }}</span>
                        <span class="font-semibold" :class="item.done ? 'text-success' : 'text-warning'">{{ item.done ? 'ok' : 'offen' }}</span>
                    </div>
                </div>
            </div>
            <div v-if="isLiveTraining" class="mt-3 rounded-xl border border-success/40 bg-success/10 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-success">Läuft gerade</p>
                <p class="mt-1 text-2xl font-semibold text-primary">{{ liveElapsedLabel }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="$emit('set-duration-from-live')">
                        Dauer setzen
                    </button>
                    <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="$emit('finish-live')">
                        Fertig
                    </button>
                </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Dauer</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ sessionMinutes ? `${formatNumber(sessionMinutes)} min` : '-' }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Distanz</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ sessionDistanceKm ? `${formatNumber(sessionDistanceKm, 2)} km` : '-' }}</p>
                </div>
                <div v-if="sessionPace" class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-air-blue">Pace</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ sessionPace }}</p>
                </div>
                <div v-if="sessionSpeedKmh" class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-300">km/h</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ sessionSpeedKmh }}</p>
                </div>
                <div v-if="usesGymSets && gymVolumeKg" class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-air-blue">Volumen</p>
                    <p class="mt-1 text-sm font-semibold text-primary">{{ formatNumber(gymVolumeKg, 0) }} kg</p>
                </div>
            </div>
            <div v-if="restSeconds > 0" class="mt-3 rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Pause läuft</p>
                <div class="mt-1 flex items-center justify-between gap-3">
                    <p class="text-2xl font-semibold text-primary">{{ restTimerLabel }}</p>
                    <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="$emit('stop-rest')">
                        Stop
                    </button>
                </div>
            </div>
        </section>
        <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
            Training speichern
        </button>
    </aside>
</template>


