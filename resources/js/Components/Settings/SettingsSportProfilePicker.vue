<script setup>
import { computed } from 'vue'

const props = defineProps({
    sportProfileNotice: { type: Object, default: null },
    savingSportProfileId: { type: [Number, String], default: null },
    selectedSportProfileId: { type: [Number, String], default: '' },
    sportProfileSearch: { type: String, default: '' },
    sportProfilePickerOpen: { type: Boolean, default: false },
    availableSportProfiles: { type: Array, default: () => [] },
    filteredAvailableSportProfiles: { type: Array, default: () => [] },
    selectedSportProfile: { type: Object, default: null },
    sportProfileText: { type: Function, required: true },
    clearSportProfileChoice: { type: Function, required: true },
    chooseSportProfile: { type: Function, required: true },
    addSelectedSportProfile: { type: Function, required: true },
})

const emit = defineEmits([
    'update:selectedSportProfileId',
    'update:sportProfileSearch',
    'update:sportProfilePickerOpen',
])

const selectedSportProfileIdModel = computed({
    get: () => props.selectedSportProfileId,
    set: (value) => emit('update:selectedSportProfileId', value),
})
const sportProfileSearchModel = computed({
    get: () => props.sportProfileSearch,
    set: (value) => emit('update:sportProfileSearch', value),
})
const sportProfilePickerOpenModel = computed({
    get: () => props.sportProfilePickerOpen,
    set: (value) => emit('update:sportProfilePickerOpen', value),
})
</script>

<template>
    <section class="surface-card p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ sportProfileText('eyebrow', 'KI-Trainingspläne') }}</p>
                <h2 class="mt-1 text-xl font-semibold text-primary">{{ sportProfileText('title', 'Sportprofil & Leistungsdaten') }}</h2>
                <p class="mt-2 max-w-3xl text-sm text-secondary">
                    {{ sportProfileText('subtitle', 'Diese Daten machen KI-Pläne persönlicher und sicherer. Airmius nutzt sie für Pace, Umfang, Regeneration, Verletzungsrisiko und realistische Steigerung.') }}
                </p>
            </div>
            <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                {{ sportProfileText('default_private', 'Standard: privat') }}
            </span>
        </div>

        <div
            v-if="sportProfileNotice"
            class="mt-4 rounded-lg border px-4 py-3 text-sm font-semibold"
            :class="sportProfileNotice.type === 'success'
                ? 'border-success/30 bg-success/10 text-success'
                : 'border-error/30 bg-error/10 text-error'"
        >
            {{ sportProfileNotice.message }}
        </div>

        <div class="mt-5 rounded-2xl border border-border bg-bg p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <label class="block flex-1 text-sm font-semibold text-primary">
                    {{ sportProfileText('add_sport', 'Sportart hinzufügen') }}
                    <div class="relative mt-2">
                        <div class="flex min-h-11 items-center gap-2 rounded-xl border border-border bg-inputBg px-3 focus-within:border-air-blue">
                            <i class="las la-search text-lg text-secondary"></i>
                            <input
                                id="sport-profile-search"
                                v-model="sportProfileSearchModel"
                                type="search"
                                class="min-w-0 flex-1 border-0 bg-transparent py-2 text-sm font-semibold text-primary outline-none placeholder:text-secondary"
                                :placeholder="availableSportProfiles.length ? sportProfileText('search_or_select', 'Sportart suchen oder auswählen') : sportProfileText('all_selected', 'Alle ausgewählten Sportarten sind bereits hinzugefügt')"
                                :disabled="!availableSportProfiles.length"
                                autocomplete="off"
                                @focus="sportProfilePickerOpenModel = true"
                                @input="selectedSportProfileIdModel = ''; sportProfilePickerOpenModel = true"
                                @keydown.escape="sportProfilePickerOpenModel = false"
                            />
                            <button
                                v-if="selectedSportProfile"
                                type="button"
                                class="rounded-full border border-border px-2 py-1 text-xs font-semibold text-secondary hover:bg-muted"
                                @click="clearSportProfileChoice"
                            >
                                {{ sportProfileText('change', 'Ändern') }}
                            </button>
                        </div>

                        <div
                            v-if="sportProfilePickerOpenModel && availableSportProfiles.length"
                            class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-border bg-card p-2 shadow-2xl"
                        >
                            <button
                                v-for="profile in filteredAvailableSportProfiles"
                                :key="profile.sport.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm transition hover:bg-muted"
                                @click="chooseSportProfile(profile)"
                            >
                                <span>
                                    <span class="block font-semibold text-primary">{{ profile.sport.name }}</span>
                                    <span class="text-xs text-secondary">{{ profile.group }}{{ profile.sport.category ? ` · ${profile.sport.category}` : '' }}</span>
                                </span>
                                <i class="las la-plus text-lg text-air-blue"></i>
                            </button>
                            <p v-if="!filteredAvailableSportProfiles.length" class="px-3 py-4 text-sm text-secondary">
                                {{ sportProfileText('no_sport_found', 'Keine Sportart gefunden.') }}
                            </p>
                        </div>
                    </div>
                    <select v-model="selectedSportProfileIdModel" class="hidden" :disabled="!availableSportProfiles.length">
                        <option value="">{{ availableSportProfiles.length ? sportProfileText('select_sport', 'Sportart auswählen') : sportProfileText('all_selected', 'Alle ausgewählten Sportarten sind bereits hinzugefügt') }}</option>
                        <option v-for="profile in availableSportProfiles" :key="profile.sport.id" :value="profile.sport.id">
                            {{ profile.sport.name }}
                        </option>
                    </select>
                </label>
                <button
                    type="button"
                    class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                    :disabled="!selectedSportProfileIdModel || savingSportProfileId"
                    @click="addSelectedSportProfile"
                >
                    {{ sportProfileText('add', 'Hinzufügen') }}
                </button>
            </div>
            <p class="mt-2 text-xs text-secondary">
                {{ sportProfileText('add_hint', 'Es werden nur Sportarten angezeigt, die du hier auswählst und wirklich betreibst.') }}
            </p>
        </div>
    </section>
</template>


