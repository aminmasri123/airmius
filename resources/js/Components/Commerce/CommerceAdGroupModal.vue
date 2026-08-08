<script setup>
import { computed } from 'vue'

const props = defineProps({
    modal: { type: Object, required: true },
    form: { type: Object, required: true },
    adPlacements: { type: Array, default: () => [] },
    selectedAdGroupSports: { type: Array, default: () => [] },
    filteredAdGroupSports: { type: Array, default: () => [] },
    adGroupSportQuery: { type: String, default: '' },
    moneyInputAttrs: { type: Object, default: () => ({}) },
    sportLabel: { type: Function, required: true },
})

const emit = defineEmits([
    'add-sport',
    'close',
    'remove-sport',
    'submit',
    'update:adGroupSportQuery',
])

const sportQuery = computed({
    get: () => props.adGroupSportQuery,
    set: (value) => emit('update:adGroupSportQuery', value),
})
</script>

<template>
    <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <form
            class="relative max-h-[90dvh] w-full max-w-2xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl"
            @submit.prevent="emit('submit')"
        >
            <button
                type="button"
                class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                aria-label="Anzeigegruppe schließen"
                @click="emit('close')"
            >
                <i class="las la-times text-xl"></i>
            </button>
            <div class="pr-12">
                <p class="text-xs font-semibold uppercase text-air-blue">Schritt 2</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Anzeigegruppe erstellen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Kampagne: {{ modal.campaign?.name }}. Zielgruppe und Placement werden hier definiert.
                </p>
            </div>

            <div class="mt-5 grid gap-3">
                <input
                    v-model="form.name"
                    class="rounded-lg border-border bg-inputBg text-sm text-primary"
                    placeholder="Name der Anzeigegruppe"
                >
                <p v-if="form.errors.name" class="text-sm text-error">{{ form.errors.name }}</p>

                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">Placement</label>
                    <select v-model="form.placement" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="placement in adPlacements" :key="placement.key" :value="placement.key">
                            {{ placement.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">{{ $t('Sportarten') }}</label>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="sport in selectedAdGroupSports"
                                :key="sport"
                                class="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1 text-xs font-semibold text-primary"
                            >
                                {{ sportLabel(sport) }}
                                <button type="button" class="text-secondary hover:text-danger" @click="emit('remove-sport', sport)">
                                    <i class="las la-times"></i>
                                </button>
                            </span>
                            <span v-if="!selectedAdGroupSports.length" class="text-sm text-secondary">
                                Noch keine Sportart gewählt.
                            </span>
                        </div>

                        <input
                            v-model="sportQuery"
                            class="mt-3 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                            placeholder="Sportart suchen und aus Liste wählen"
                        >

                        <div class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-border bg-card">
                            <button
                                v-for="sport in filteredAdGroupSports"
                                :key="sport.slug"
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm transition hover:bg-muted"
                                @click="emit('add-sport', sport)"
                            >
                                <span class="block font-semibold text-primary">{{ sport.name }}</span>
                                <span v-if="sport.category" class="block text-xs text-secondary">{{ sport.category }}</span>
                            </button>
                            <p v-if="!filteredAdGroupSports.length" class="px-3 py-3 text-sm text-secondary">
                                Keine weitere Sportart gefunden.
                            </p>
                        </div>
                        <p v-if="form.errors.sports" class="mt-2 text-sm text-error">{{ form.errors.sports }}</p>
                    </div>

                    <input
                        v-model="form.interests"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Interessen, z. B. Fitness, Ausrüstung"
                    >
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <select v-model="form.gender" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="all">Alle Geschlechter</option>
                        <option value="female">Frauen</option>
                        <option value="male">Männer</option>
                        <option value="diverse">Divers</option>
                    </select>
                    <input
                        v-model="form.age_min"
                        type="number"
                        min="13"
                        max="100"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Alter von"
                    >
                    <input
                        v-model="form.age_max"
                        type="number"
                        min="13"
                        max="100"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Alter bis"
                    >
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <input
                        v-model="form.locations"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Ort/Region, z. B. Saarland, Berlin"
                    >
                    <input
                        v-model="form.zones"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Zone, z. B. 10 km um Saarbrücken"
                    >
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <input
                        v-model="form.daily_budget_cents"
                        v-bind="moneyInputAttrs"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Tagesbudget in EUR"
                    >
                    <input
                        v-model="form.starts_at"
                        type="datetime-local"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                    >
                    <input
                        v-model="form.ends_at"
                        type="datetime-local"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                    >
                </div>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary"
                    @click="emit('close')"
                >
                    Abbrechen
                </button>
                <button
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Anzeigegruppe speichern
                </button>
            </div>
        </form>
    </div>
</template>
