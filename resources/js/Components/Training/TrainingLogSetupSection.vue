<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue'

defineProps({
    detailSummary: { type: String, default: '' },
    selectedType: { type: Object, required: true },
    trainingTypes: { type: Array, default: () => [] },
    trainingTypeTheme: { type: Function, required: true },
    trainingTypeButtonClass: { type: Function, required: true },
    selectTrainingType: { type: Function, required: true },
    athleteOptions: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    plannedItems: { type: Array, default: () => [] },
    formatDate: { type: Function, required: true },
    applySelectedPlanItem: { type: Function, required: true },
    sportChoices: { type: Array, default: () => [] },
    recentSportChoices: { type: Array, default: () => [] },
    setStatusDefaults: { type: Function, required: true },
    showSessionDistance: { type: Boolean, default: false },
    isLiveTraining: { type: Boolean, default: false },
    liveElapsedLabel: { type: String, default: '' },
    startLiveTraining: { type: Function, required: true },
    setDurationFromLive: { type: Function, required: true },
    finishLiveTraining: { type: Function, required: true },
    setMobileTrainingTypeSheetOpen: { type: Function, required: true },
    setCurrentTrainingStep: { type: Function, required: true },
})
</script>

<template>
    <section class="min-w-0 space-y-4 rounded-2xl border border-border bg-card p-3 sm:space-y-5 sm:p-5 2xl:col-start-1 2xl:row-start-2">
        <div class="grid gap-3 md:grid-cols-2 md:gap-4">
            <div class="md:col-span-2">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-primary">Trainingsart</p>
                        <p class="mt-1 hidden text-xs text-secondary sm:block">Wische auf dem Handy seitlich durch die Vorlagen.</p>
                    </div>
                    <span class="hidden rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary sm:inline-flex">{{ detailSummary }}</span>
                </div>
                <button
                    type="button"
                    class="mt-3 flex w-full items-center gap-3 rounded-2xl border border-border bg-inputBg/40 p-3 text-left sm:hidden"
                    @click="setMobileTrainingTypeSheetOpen(true)"
                >
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="trainingTypeTheme(selectedType.key).icon">
                        <i :class="selectedType.icon" class="text-xl"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-secondary">Ausgewählt</span>
                        <span class="mt-0.5 block truncate text-base font-semibold text-primary">{{ selectedType.label }}</span>
                    </span>
                    <span class="rounded-xl bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary">
                        Ändern
                    </span>
                </button>
                <div class="mt-3 hidden grid-cols-3 gap-2 sm:grid sm:grid-cols-4 xl:grid-cols-7">
                    <button
                        v-for="type in trainingTypes"
                        :key="type.key"
                        type="button"
                        class="flex min-h-11 min-w-0 items-center gap-2 rounded-2xl border px-2.5 py-2 text-left transition sm:min-h-12 sm:px-3 sm:py-2.5"
                        :class="trainingTypeButtonClass(type)"
                        @click="selectTrainingType(type.key)"
                    >
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-xl sm:h-8 sm:w-8" :class="trainingTypeTheme(type.key).icon">
                            <i :class="type.icon" class="text-base sm:text-lg"></i>
                        </span>
                        <span class="min-w-0 truncate text-xs font-semibold sm:text-sm">{{ type.shortLabel }}</span>
                    </button>
                </div>
            </div>
            <label v-if="athleteOptions.length > 1" class="block text-sm font-semibold text-primary">Sportler
                <select v-model="form.user_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option v-for="athlete in athleteOptions" :key="athlete.id || 'self'" :value="athlete.id">{{ athlete.name }}</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">Geplante Einheit
                <select v-model="form.training_plan_item_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="applySelectedPlanItem">
                    <option value="">Spontanes Training</option>
                    <option v-for="item in plannedItems" :key="item.id" :value="item.id">
                        {{ item.plan.title }} - {{ item.title }}{{ item.scheduled_at ? ` - ${formatDate(item.scheduled_at)}` : '' }}
                    </option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">Sportart
                <SearchableSelect
                    v-model="form.sport_type"
                    class="mt-2"
                    :options="sportChoices"
                    label-key="label"
                    value-key="key"
                    placeholder="Sportart suchen"
                />
                <div v-if="recentSportChoices.length" class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="sport in recentSportChoices"
                        :key="sport.key"
                        type="button"
                        class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                        :class="form.sport_type === sport.key ? 'border-air-blue bg-air-blue/10 text-primary' : 'border-border text-secondary hover:bg-muted hover:text-primary'"
                        @click="form.sport_type = sport.key"
                    >
                        {{ sport.label }}
                    </button>
                </div>
            </label>
            <details class="rounded-2xl border border-border bg-inputBg/30 p-3 md:hidden">
                <summary class="cursor-pointer list-none text-sm font-semibold text-primary">
                    Weitere Angaben
                    <span class="ml-2 text-xs font-normal text-secondary">optional</span>
                </summary>
                <div class="mt-3 grid gap-3">
                    <label class="block text-sm font-semibold text-primary">Titel
                        <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Status
                        <select v-model="form.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="setStatusDefaults">
                            <option value="completed">Abgeschlossen</option>
                            <option value="in_progress">Läuft gerade</option>
                            <option value="planned">Geplant</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-primary">Zeitpunkt
                        <input v-model="form.performed_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Dauer in Minuten
                        <input v-model="form.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label v-if="showSessionDistance" class="block text-sm font-semibold text-primary">Distanz in km
                        <input v-model="form.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Notizen
                        <textarea v-model="form.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Gefühl, Technik, Schmerzen, Besonderheiten" />
                    </label>
                </div>
            </details>
            <label class="hidden text-sm font-semibold text-primary md:col-span-2 md:block">Titel
                <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
            </label>
            <label class="hidden text-sm font-semibold text-primary md:block">Status
                <select v-model="form.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" @change="setStatusDefaults">
                    <option value="completed">Abgeschlossen</option>
                    <option value="in_progress">Läuft gerade</option>
                    <option value="planned">Geplant</option>
                </select>
            </label>
            <label class="hidden text-sm font-semibold text-primary md:block">Zeitpunkt
                <input v-model="form.performed_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <div class="hidden rounded-xl border border-border bg-inputBg/40 p-3 md:col-span-2 md:block">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Live-Modus</p>
                        <p class="mt-1 text-sm font-semibold text-primary">
                            <span v-if="isLiveTraining">Training läuft seit {{ liveElapsedLabel }}</span>
                            <span v-else>Schnellstart für Training auf dem Platz, im Gym oder unterwegs.</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="!isLiveTraining"
                            type="button"
                            class="rounded-xl border border-air-blue/40 bg-air-blue/10 px-3 py-2 text-sm font-semibold text-primary hover:bg-air-blue/20"
                            @click="startLiveTraining"
                        >
                            Läuft gerade starten
                        </button>
                        <button
                            v-if="isLiveTraining"
                            type="button"
                            class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                            @click="setDurationFromLive"
                        >
                            Zeit übernehmen
                        </button>
                        <button
                            v-if="isLiveTraining"
                            type="button"
                            class="rounded-xl bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                            @click="finishLiveTraining"
                        >
                            Abschließen
                        </button>
                    </div>
                </div>
            </div>
            <label class="hidden text-sm font-semibold text-primary md:block">Dauer in Minuten
                <input v-model="form.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label v-if="showSessionDistance" class="hidden text-sm font-semibold text-primary md:block">Distanz in km
                <input v-model="form.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="hidden text-sm font-semibold text-primary md:col-span-2 md:block">Notizen
                <textarea v-model="form.notes" rows="4" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Gefühl, Technik, Schmerzen, Besonderheiten" />
            </label>
            <label v-if="form.user_id" class="hidden text-sm font-semibold text-primary md:col-span-2 md:block">Trainer-Hinweis
                <textarea v-model="form.trainer_feedback" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Hinweise, Korrekturen oder Fokus für die nächste Einheit" />
            </label>
            <label class="hidden text-sm font-semibold text-primary md:col-span-2 md:block">Sichtbarkeit
                <select v-model="form.privacy_scope" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="trainer">Trainer und berechtigte Betreuer</option>
                    <option value="private">Nur ich</option>
                    <option value="team">Team</option>
                </select>
            </label>
            <label class="hidden items-start gap-3 rounded-xl border border-border bg-inputBg/40 p-3 text-sm font-semibold text-primary md:col-span-2 md:flex">
                <input v-model="form.notify_people" type="checkbox" class="mt-1 rounded border-border bg-inputBg" />
                <span>
                    <span>{{ form.user_id ? 'Sportler beim Speichern informieren' : 'Trainer beim Speichern informieren' }}</span>
                    <span class="mt-1 block text-xs font-normal leading-5 text-secondary">
                        Standard ist aktiv. Wenn du es deaktivierst, wird keine Benachrichtigung verschickt; berechtigte Personen können die Einheit weiterhin sehen.
                    </span>
                </span>
            </label>
            <div class="flex justify-end md:col-span-2">
                <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary" @click="setCurrentTrainingStep(2)">
                    <span class="sm:hidden">Weiter</span>
                    <span class="hidden sm:inline">Weiter dokumentieren</span>
                    <i class="las la-arrow-right text-lg sm:hidden"></i>
                </button>
            </div>
        </div>
    </section>
</template>

