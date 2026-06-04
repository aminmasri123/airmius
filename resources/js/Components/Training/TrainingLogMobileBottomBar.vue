<script setup>
import { computed } from 'vue'

const props = defineProps({
    usesGymSets: { type: Boolean, default: false },
    activeGymSet: { type: Object, default: null },
    mobileLivePanelOpen: { type: Boolean, default: false },
    activeGymExercise: { type: Object, default: null },
    activeGymSetIndex: { type: Number, default: 0 },
    restSeconds: { type: Number, default: 0 },
    restTimerLabel: { type: String, default: '' },
    form: { type: Object, required: true },
    selectedType: { type: Object, required: true },
    isLiveTraining: { type: Boolean, default: false },
    liveElapsedLabel: { type: String, default: '' },
    detailSummary: { type: String, default: '' },
    showQuickDistanceAction: { type: Boolean, default: false },
    activeEntry: { type: Object, default: null },
    hasField: { type: Function, required: true },
    stopRestTimer: { type: Function, required: true },
    adjustActiveGymSet: { type: Function, required: true },
    finishActiveGymSet: { type: Function, required: true },
    nextActiveGymSet: { type: Function, required: true },
    startLiveTraining: { type: Function, required: true },
    finishLiveTraining: { type: Function, required: true },
    adjustSessionNumber: { type: Function, required: true },
    nextActiveEntry: { type: Function, required: true },
    setDurationFromLive: { type: Function, required: true },
    adjustActiveEntry: { type: Function, required: true },
})

const emit = defineEmits(['update:mobileLivePanelOpen'])

const panelOpen = computed({
    get: () => props.mobileLivePanelOpen,
    set: (value) => emit('update:mobileLivePanelOpen', value),
})
</script>

<template>
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-bg/95 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-2xl backdrop-blur lg:hidden">
        <div v-if="usesGymSets && activeGymSet" class="mx-auto max-w-4xl">
            <button
                v-if="!panelOpen"
                type="button"
                class="flex w-full items-center gap-3 rounded-2xl border border-border bg-card p-3 text-left"
                @click="panelOpen = true"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-air-blue/15 text-air-blue">
                    <i class="las la-dumbbell text-xl"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-primary">{{ activeGymExercise?.title || 'Aktive Übung' }}</span>
                    <span class="block truncate text-xs text-secondary">
                        Satz {{ activeGymSetIndex + 1 }} - {{ activeGymSet.reps || 0 }} Wdh. - {{ activeGymSet.weight_kg || 0 }} kg
                    </span>
                </span>
                <span class="rounded-xl bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary">
                    Öffnen
                </span>
            </button>

            <div v-else class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-primary">{{ activeGymExercise?.title || 'Aktive Übung' }}</p>
                        <p class="text-xs text-secondary">
                            Satz {{ activeGymSetIndex + 1 }}{{ restSeconds > 0 ? ` - Pause ${restTimerLabel}` : '' }}
                        </p>
                    </div>
                    <button v-if="restSeconds > 0" type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-semibold text-primary" @click="stopRestTimer">
                        Pause stop
                    </button>
                    <button type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-semibold text-primary" @click="panelOpen = false">
                        Minimieren
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div class="rounded-xl border border-border bg-card p-2">
                        <p class="text-[11px] font-semibold uppercase text-secondary">Wdh.</p>
                        <div class="mt-1 flex items-center justify-between gap-1">
                            <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('reps', -1)">-</button>
                            <span class="text-sm font-semibold text-primary">{{ activeGymSet.reps || 0 }}</span>
                            <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('reps', 1)">+</button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-2">
                        <p class="text-[11px] font-semibold uppercase text-secondary">kg</p>
                        <div class="mt-1 flex items-center justify-between gap-1">
                            <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('weight_kg', -2.5, 0.5)">-</button>
                            <span class="text-sm font-semibold text-primary">{{ activeGymSet.weight_kg || 0 }}</span>
                            <button type="button" class="h-8 w-8 rounded-lg border border-border text-primary" @click="adjustActiveGymSet('weight_kg', 2.5, 0.5)">+</button>
                        </div>
                    </div>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="finishActiveGymSet">
                        Satz fertig
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="rounded-xl border border-border px-3 py-2 text-xs font-semibold text-primary" @click="nextActiveGymSet">
                        Nächster Satz
                    </button>
                    <button type="submit" class="rounded-xl border border-border bg-card px-3 py-2 text-xs font-semibold text-primary disabled:opacity-60" :disabled="form.processing">
                        Speichern
                    </button>
                </div>
            </div>
        </div>
        <div v-else class="mx-auto max-w-4xl space-y-2">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-primary">{{ form.title || selectedType.label }}</p>
                    <p class="truncate text-xs text-secondary">
                        {{ isLiveTraining ? `Läuft ${liveElapsedLabel}` : detailSummary }}
                    </p>
                </div>
                <button v-if="!isLiveTraining" type="button" class="rounded-xl border border-border px-3 py-3 text-xs font-semibold text-primary" @click="startLiveTraining">
                    Start
                </button>
                <button v-else type="button" class="rounded-xl border border-border px-3 py-3 text-xs font-semibold text-primary" @click="finishLiveTraining">
                    Fertig
                </button>
                <button type="submit" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                    Speichern
                </button>
            </div>
            <div class="grid grid-cols-4 gap-2">
                <button type="button" class="rounded-xl border border-border bg-card px-2 py-2 text-xs font-semibold text-primary" @click="adjustSessionNumber('duration_minutes', 5)">
                    +5 min
                </button>
                <button v-if="showQuickDistanceAction" type="button" class="rounded-xl border border-border bg-card px-2 py-2 text-xs font-semibold text-primary" @click="adjustSessionNumber('distance_km', 0.5, 0.1)">
                    +0,5 km
                </button>
                <button type="button" class="rounded-xl border border-border bg-card px-2 py-2 text-xs font-semibold text-primary" @click="nextActiveEntry">
                    Abschnitt +
                </button>
                <button type="button" class="rounded-xl border border-border bg-card px-2 py-2 text-xs font-semibold text-primary" @click="setDurationFromLive">
                    Zeit
                </button>
            </div>
            <div v-if="activeEntry" class="grid grid-cols-3 gap-2">
                <button
                    v-if="hasField('duration_minutes')"
                    type="button"
                    class="rounded-xl border border-air-blue/30 bg-air-blue/10 px-2 py-2 text-xs font-semibold text-primary"
                    @click="adjustActiveEntry('duration_minutes', 1)"
                >
                    Abschnitt +1 min
                </button>
                <button
                    v-if="hasField('distance_km')"
                    type="button"
                    class="rounded-xl border border-air-blue/30 bg-air-blue/10 px-2 py-2 text-xs font-semibold text-primary"
                    @click="adjustActiveEntry('distance_km', 0.1, 0.1)"
                >
                    Abschnitt +0,1 km
                </button>
                <button
                    v-if="hasField('reps')"
                    type="button"
                    class="rounded-xl border border-air-blue/30 bg-air-blue/10 px-2 py-2 text-xs font-semibold text-primary"
                    @click="adjustActiveEntry('reps', 1)"
                >
                    Wiederholung +
                </button>
            </div>
        </div>
    </div>
</template>


