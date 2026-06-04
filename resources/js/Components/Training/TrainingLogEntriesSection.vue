<script setup>
defineProps({
    selectedType: { type: Object, required: true },
    usesGymSets: { type: Boolean, default: false },
    form: { type: Object, required: true },
    activeGymExerciseIndex: { type: Number, required: true },
    activeGymSetIndex: { type: Number, required: true },
    activeEntryIndex: { type: Number, required: true },
    selectedTemplates: { type: Array, default: () => [] },
    visibleEntries: { type: Array, default: () => [] },
    addEntry: { type: Function, required: true },
    addGymExercise: { type: Function, required: true },
    removeGymExercise: { type: Function, required: true },
    setActiveGymExercise: { type: Function, required: true },
    matchingRecentExercise: { type: Function, required: true },
    recentExerciseLabel: { type: Function, required: true },
    applyRecentExercise: { type: Function, required: true },
    formatShortDate: { type: Function, required: true },
    exerciseHistoryLabel: { type: Function, required: true },
    addGymSet: { type: Function, required: true },
    setActiveGymSet: { type: Function, required: true },
    toggleGymSetDone: { type: Function, required: true },
    removeGymSet: { type: Function, required: true },
    previousActiveGymSet: { type: Function, required: true },
    nextActiveGymSet: { type: Function, required: true },
    finishActiveGymSet: { type: Function, required: true },
    applyDetailTemplate: { type: Function, required: true },
    hasField: { type: Function, required: true },
    fieldLabel: { type: Function, required: true },
    setActiveEntry: { type: Function, required: true },
    removeEntry: { type: Function, required: true },
    setCurrentTrainingStep: { type: Function, required: true },
})
</script>

<template>
    <section class="min-w-0 space-y-3 rounded-2xl border border-border bg-card p-3 sm:space-y-4 sm:p-5 2xl:col-start-1 2xl:row-start-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Details</p>
                <h2 class="text-xl font-semibold text-primary">{{ selectedType.detailTitle }}</h2>
            </div>
            <button v-if="!usesGymSets" type="button" class="rounded-xl border border-border bg-inputBg/40 px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addEntry">
                Zeile hinzufügen
            </button>
        </div>

        <div v-if="usesGymSets" class="space-y-4">
            <div class="rounded-2xl border border-border bg-inputBg/40 p-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Übungen</p>
                        <p class="mt-1 text-sm font-semibold text-primary">{{ form.gym_exercises.length }} Übungen angelegt</p>
                    </div>
                    <button type="button" class="rounded-xl border border-border bg-card px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addGymExercise">
                        Übung hinzufügen
                    </button>
                </div>
                <div class="custom-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="(exercise, exerciseIndex) in form.gym_exercises"
                        :key="`exercise-step-${exerciseIndex}`"
                        type="button"
                        class="min-w-32 rounded-xl border px-3 py-2 text-left text-sm transition"
                        :class="activeGymExerciseIndex === exerciseIndex ? 'border-air-blue bg-air-blue/10 text-primary ring-1 ring-air-blue/30' : 'border-border bg-card text-secondary hover:bg-muted hover:text-primary'"
                        @click="setActiveGymExercise(exerciseIndex)"
                    >
                        <span class="block text-[11px] font-semibold uppercase tracking-wide">Übung {{ exerciseIndex + 1 }}</span>
                        <span class="mt-1 block truncate font-semibold">{{ exercise.title || 'Ohne Namen' }}</span>
                    </button>
                </div>
            </div>

            <div
                v-for="(exercise, exerciseIndex) in form.gym_exercises"
                :key="exerciseIndex"
                v-show="activeGymExerciseIndex === exerciseIndex"
                class="rounded-2xl border border-border bg-inputBg/40 p-3 sm:p-4"
            >
                <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                    <label class="block text-sm font-semibold text-primary">Übung {{ exerciseIndex + 1 }}
                        <input v-model="exercise.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. Kniebeugen" />
                    </label>
                    <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="removeGymExercise(exerciseIndex)">
                        Übung entfernen
                    </button>
                </div>
                <label class="mt-3 hidden text-sm font-semibold text-primary md:block">Notiz zur Übung
                    <input v-model="exercise.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. tief, sauber, letzte Wiederholung schwer" />
                </label>
                <details class="mt-3 rounded-xl border border-border bg-card p-3 md:hidden">
                    <summary class="cursor-pointer list-none text-sm font-semibold text-primary">
                        Übungsdetails
                        <span class="ml-2 text-xs font-normal text-secondary">optional</span>
                    </summary>
                    <label class="mt-3 block text-sm font-semibold text-primary">Notiz zur Übung
                        <input v-model="exercise.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. tief, sauber, letzte Wiederholung schwer" />
                    </label>
                </details>
                <div
                    v-if="matchingRecentExercise(exercise)"
                    class="mt-3 hidden rounded-xl border border-air-blue/30 bg-air-blue/10 p-3 text-sm md:block"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-primary">Letzte Werte gefunden</p>
                            <p class="mt-1 text-xs text-secondary">{{ recentExerciseLabel(matchingRecentExercise(exercise)) }}</p>
                        </div>
                        <button type="button" class="rounded-xl border border-air-blue/40 px-3 py-2 text-xs font-semibold text-primary hover:bg-air-blue/10" @click="applyRecentExercise(exerciseIndex, matchingRecentExercise(exercise))">
                            Letzte Werte übernehmen
                        </button>
                    </div>
                    <div v-if="matchingRecentExercise(exercise).history?.length" class="mt-3 grid gap-2 sm:grid-cols-3">
                        <div
                            v-for="session in matchingRecentExercise(exercise).history.slice(0, 3)"
                            :key="`${session.performed_at}-${exerciseHistoryLabel(session)}`"
                            class="rounded-lg border border-border bg-card/70 px-3 py-2"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">{{ formatShortDate(session.performed_at) }}</p>
                            <p class="mt-1 text-xs font-semibold text-primary">{{ exerciseHistoryLabel(session) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-border bg-card p-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Sätze</p>
                            <p class="mt-1 text-sm font-semibold text-primary">{{ exercise.sets.length }} Sätze in Übung {{ exerciseIndex + 1 }}</p>
                        </div>
                        <button type="button" class="rounded-xl border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="addGymSet(exerciseIndex)">
                            Satz hinzufügen
                        </button>
                    </div>
                    <div class="custom-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="(set, setIndex) in exercise.sets"
                            :key="`set-step-${exerciseIndex}-${setIndex}`"
                            type="button"
                            class="min-w-28 rounded-xl border px-3 py-2 text-left text-sm transition"
                            :class="activeGymExerciseIndex === exerciseIndex && activeGymSetIndex === setIndex ? 'border-air-blue bg-air-blue/10 text-primary ring-1 ring-air-blue/30' : set.completed ? 'border-success/40 bg-success/10 text-success' : 'border-border bg-inputBg/40 text-secondary hover:bg-muted hover:text-primary'"
                            @click="setActiveGymSet(exerciseIndex, setIndex)"
                        >
                            <span class="block text-[11px] font-semibold uppercase tracking-wide">Satz {{ setIndex + 1 }}</span>
                            <span class="mt-1 block truncate font-semibold">{{ set.completed ? 'Erledigt' : 'Offen' }}</span>
                        </button>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div
                        v-for="(set, setIndex) in exercise.sets"
                        :key="setIndex"
                        v-show="activeGymExerciseIndex === exerciseIndex && activeGymSetIndex === setIndex"
                        class="grid gap-3 rounded-2xl border p-3 transition sm:grid-cols-2 lg:grid-cols-6"
                        :class="activeGymExerciseIndex === exerciseIndex && activeGymSetIndex === setIndex ? 'border-air-blue bg-air-blue/10 ring-1 ring-air-blue/30' : set.completed ? 'border-success/40 bg-success/10' : 'border-border bg-card'"
                        @click="setActiveGymSet(exerciseIndex, setIndex)"
                    >
                        <div class="flex items-center justify-between gap-2 lg:block lg:pt-8">
                            <p class="text-sm font-semibold text-primary">Satz {{ setIndex + 1 }}</p>
                            <div class="flex flex-wrap gap-1">
                                <span v-if="activeGymExerciseIndex === exerciseIndex && activeGymSetIndex === setIndex" class="rounded-full bg-air-blue/10 px-2 py-1 text-xs font-semibold text-air-blue">aktiv</span>
                                <span v-if="set.completed" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">erledigt</span>
                            </div>
                        </div>
                        <label class="block text-sm font-semibold text-primary">Wdh.
                            <input v-model="set.reps" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="15" />
                        </label>
                        <label class="block text-sm font-semibold text-primary">Gewicht kg
                            <input v-model="set.weight_kg" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="30" />
                        </label>
                        <label class="hidden text-sm font-semibold text-primary md:block">Zeit min
                            <input v-model="set.duration_minutes" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <div class="grid gap-2 self-end">
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
                                :class="set.completed ? 'border-success/40 text-success hover:bg-success/10' : 'border-border text-primary hover:bg-muted'"
                                @click="toggleGymSetDone(exerciseIndex, setIndex)"
                            >
                                {{ set.completed ? 'Erledigt' : 'Satz erledigt' }}
                            </button>
                            <button type="button" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click.stop="removeGymSet(exerciseIndex, setIndex)">
                                Entfernen
                            </button>
                        </div>
                        <details class="rounded-xl border border-border bg-card/70 p-3 sm:col-span-2 md:hidden">
                            <summary class="cursor-pointer list-none text-sm font-semibold text-primary">
                                Satzdetails
                                <span class="ml-2 text-xs font-normal text-secondary">optional</span>
                            </summary>
                            <div class="mt-3 grid gap-3">
                                <label class="block text-sm font-semibold text-primary">Zeit min
                                    <input v-model="set.duration_minutes" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Kommentar zum Satz
                                    <input v-model="set.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Medien-Link
                                    <input v-model="set.media_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Video oder Bild-Link" />
                                </label>
                                <label class="block text-sm font-semibold text-primary">Datei hochladen
                                    <input type="file" accept="image/*,video/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="set.media_file = $event.target.files?.[0] || null" />
                                </label>
                            </div>
                        </details>
                        <label class="hidden text-sm font-semibold text-primary sm:col-span-2 md:block lg:col-span-6">Kommentar zum Satz
                            <input v-model="set.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                        </label>
                        <label class="hidden text-sm font-semibold text-primary sm:col-span-2 md:block lg:col-span-6">Medien-Link
                            <input v-model="set.media_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Video oder Bild-Link für Technikfeedback" />
                        </label>
                        <label class="hidden text-sm font-semibold text-primary sm:col-span-2 md:block lg:col-span-6">Datei hochladen
                            <input type="file" accept="image/*,video/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="set.media_file = $event.target.files?.[0] || null" />
                        </label>
                    </div>
                </div>
                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <button type="button" class="rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold text-primary hover:bg-muted sm:py-2" @click="previousActiveGymSet">
                        Vorheriger Satz
                    </button>
                    <button type="button" class="rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold text-primary hover:bg-muted sm:py-2" @click="nextActiveGymSet">
                        Nächster Satz
                    </button>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary sm:py-2" @click="finishActiveGymSet">
                        Satz erledigt
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="space-y-3">
            <div v-if="selectedTemplates.length" class="grid gap-2 md:grid-cols-2">
                <button
                    v-for="template in selectedTemplates"
                    :key="template.key"
                    type="button"
                    class="rounded-2xl border border-border bg-inputBg/40 p-4 text-left transition hover:border-air-blue/50 hover:bg-air-blue/10"
                    @click="applyDetailTemplate(template)"
                >
                    <span class="text-sm font-semibold text-primary">{{ template.label }}</span>
                    <span class="mt-1 block text-xs leading-5 text-secondary">{{ template.description }}</span>
                </button>
            </div>
            <p v-if="selectedType.key === 'long_run' && !visibleEntries.length" class="rounded-xl border border-border bg-inputBg/40 p-3 text-sm text-secondary">
                Bei einem Long Run musst du hier nichts eintragen, wenn du nur Gesamtdauer und Distanz dokumentieren willst. Nutze Abschnitte nur für Kilometerblöcke, Tempoanteile oder besondere Phasen.
            </p>
            <div
                v-for="(entry, index) in form.entries"
                :key="index"
                class="grid gap-3 rounded-2xl border p-4 transition lg:grid-cols-6"
                :class="activeEntryIndex === index ? 'border-air-blue bg-air-blue/10 ring-1 ring-air-blue/30' : 'border-border bg-inputBg/40'"
                @click="setActiveEntry(index)"
            >
                <label class="block text-sm font-semibold text-primary lg:col-span-2">{{ selectedType.entryLabel }}
                    <input v-model="entry.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="selectedType.entryPlaceholder" />
                </label>
                <label v-if="hasField('sets')" class="block text-sm font-semibold text-primary">{{ fieldLabel('sets') }}
                    <input v-model="entry.sets" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                </label>
                <label v-if="hasField('reps')" class="block text-sm font-semibold text-primary">{{ fieldLabel('reps') }}
                    <input v-model="entry.reps" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                </label>
                <label v-if="hasField('weight_kg')" class="block text-sm font-semibold text-primary">{{ fieldLabel('weight_kg') }}
                    <input v-model="entry.weight_kg" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                </label>
                <label v-if="hasField('duration_minutes')" class="block text-sm font-semibold text-primary">{{ fieldLabel('duration_minutes') }}
                    <input v-model="entry.duration_minutes" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                </label>
                <label v-if="hasField('distance_km')" class="block text-sm font-semibold text-primary">{{ fieldLabel('distance_km') }}
                    <input v-model="entry.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                </label>
                <label v-if="hasField('intensity')" class="block text-sm font-semibold text-primary">{{ fieldLabel('intensity') }}
                    <input v-model="entry.intensity" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. RPE 7, locker, Zone 2" />
                </label>
                <label v-if="hasField('notes')" class="block text-sm font-semibold text-primary lg:col-span-4">{{ fieldLabel('notes') }}
                    <input v-model="entry.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                </label>
                <label class="block text-sm font-semibold text-primary lg:col-span-4">Medien-Link
                    <input v-model="entry.media_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Video oder Bild-Link" />
                </label>
                <label class="block text-sm font-semibold text-primary lg:col-span-4">Datei hochladen
                    <input type="file" accept="image/*,video/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="entry.media_file = $event.target.files?.[0] || null" />
                </label>
                <button type="button" class="self-end rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="removeEntry(index)">
                    Entfernen
                </button>
            </div>
        </div>
        <div class="flex flex-wrap justify-between gap-2 border-t border-border pt-4">
            <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted" @click="setCurrentTrainingStep(1)">
                Zurück
            </button>
            <button type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary" @click="setCurrentTrainingStep(3)">
                Abschließen
            </button>
        </div>
    </section>
</template>

