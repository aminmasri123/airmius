<script setup>
defineProps({
    filterForm: { type: Object, required: true },
    filterPanelOpen: { type: Boolean, default: false },
    activeFilterCount: { type: Number, default: 0 },
    eventTypes: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    filteredFilterTeams: { type: Array, default: () => [] },
    sports: { type: Array, default: () => [] },
    selectedSportsCount: { type: Number, default: 0 },
    typeLabels: { type: Object, default: () => ({}) },
    visibilityLabels: { type: Object, default: () => ({}) },
    applyFilters: { type: Function, required: true },
    resetFilters: { type: Function, required: true },
    saveDefaultFilters: { type: Function, required: true },
    toggleFilterSport: { type: Function, required: true },
})

const emit = defineEmits(['update:filterPanelOpen'])
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-4">
        <form class="space-y-4" @submit.prevent="applyFilters">
            <div class="flex flex-col gap-3 lg:flex-row">
                <label class="relative min-w-0 flex-1" for="event-search">
                    <span class="sr-only">Suche</span>
                    <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-xl text-secondary"></i>
                    <input
                        id="event-search"
                        v-model="filterForm.search"
                        class="h-12 w-full rounded-lg border border-border bg-inputBg pl-10 pr-3 text-sm text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                        placeholder="Suche nach Titel, Ort, Team oder Verein"
                    >
                </label>

                <div class="grid grid-cols-3 gap-2 sm:flex sm:shrink-0">
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                        :class="filterForm.period === 'upcoming' ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-secondary hover:border-borderHover hover:text-primary'"
                        @click="filterForm.period = 'upcoming'; applyFilters()"
                    >
                        Kommend
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                        :class="filterForm.period === 'past' ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-secondary hover:border-borderHover hover:text-primary'"
                        @click="filterForm.period = 'past'; applyFilters()"
                    >
                        Vergangen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                        :class="filterForm.period === 'all' ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-secondary hover:border-borderHover hover:text-primary'"
                        @click="filterForm.period = 'all'; applyFilters()"
                    >
                        Alle
                    </button>
                </div>

                <button
                    type="button"
                    class="inline-flex h-12 items-center justify-center gap-2 rounded-lg border border-border px-4 text-sm font-semibold text-primary transition hover:border-borderHover hover:bg-muted"
                    @click="emit('update:filterPanelOpen', !filterPanelOpen)"
                >
                    <i class="las la-sliders-h text-lg"></i>
                    Filter
                    <span v-if="activeFilterCount" class="rounded-full bg-buttonPrimary px-2 py-0.5 text-xs text-buttonTextPrimary">
                        {{ activeFilterCount }}
                    </span>
                </button>

                <button class="h-12 rounded-lg bg-buttonPrimary px-5 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover">
                    Suchen
                </button>
            </div>

            <div v-if="filterPanelOpen" class="rounded-lg border border-border bg-inputBg p-3">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-type">
                            Typ
                        </label>
                        <select id="event-filter-type" v-model="filterForm.type" class="h-11 w-full rounded-lg border border-border bg-card px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                            <option value="">Alle Typen</option>
                            <option v-for="type in eventTypes" :key="type" :value="type">
                                {{ $t(typeLabels[type] || type) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-visibility">
                            Sichtbarkeit
                        </label>
                        <select id="event-filter-visibility" v-model="filterForm.visibility" class="h-11 w-full rounded-lg border border-border bg-card px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                            <option value="">Alle</option>
                            <option v-for="visibility in visibilities" :key="visibility" :value="visibility">
                                {{ $t(visibilityLabels[visibility] || visibility) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-club">
                            Verein
                        </label>
                        <select id="event-filter-club" v-model="filterForm.club_id" class="h-11 w-full rounded-lg border border-border bg-card px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                            <option value="">Alle Vereine</option>
                            <option v-for="club in clubs" :key="club.id" :value="club.id">
                                {{ club.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-team">
                            Team
                        </label>
                        <select id="event-filter-team" v-model="filterForm.team_id" class="h-11 w-full rounded-lg border border-border bg-card px-3 text-sm text-primary focus:border-borderHover focus:ring-borderHover">
                            <option value="">Alle Teams</option>
                            <option v-for="team in filteredFilterTeams" :key="team.id" :value="team.id">
                                {{ team.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary" for="event-filter-radius">
                            PLZ-/Stadt-Nähe
                        </label>
                        <div class="flex h-11 items-center gap-2 rounded-lg border border-border bg-card px-3">
                            <input
                                id="event-filter-radius"
                                v-model="filterForm.radius_km"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary focus:ring-0"
                                min="1"
                                max="500"
                                placeholder="20"
                                type="number"
                            >
                            <span class="text-sm font-semibold text-secondary">km</span>
                        </div>
                        <p class="mt-1 text-xs text-secondary">
                            Nähe über dein Profil, PLZ und Stadt.
                        </p>
                    </div>

                    <div class="md:col-span-2 xl:col-span-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-secondary">
                                Sportarten
                            </label>
                            <span class="text-xs font-semibold text-secondary">
                                {{ selectedSportsCount }} ausgewählt
                            </span>
                        </div>
                        <div class="flex max-h-32 flex-wrap gap-2 overflow-y-auto rounded-lg border border-border bg-card p-2">
                            <button
                                v-for="sport in sports"
                                :key="sport.id"
                                type="button"
                                class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                :class="(filterForm.sport_ids || []).map(Number).includes(Number(sport.id))
                                    ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                    : 'border-border bg-bg text-secondary hover:border-borderHover hover:text-primary'"
                                @click="toggleFilterSport(sport.id)"
                            >
                                {{ sport.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="h-11 rounded-lg border border-border px-4 text-sm font-semibold text-secondary transition hover:border-borderHover hover:text-primary" @click="resetFilters">
                        Zurücksetzen
                    </button>
                    <button type="button" class="h-11 rounded-lg border border-buttonPrimary px-4 text-sm font-semibold text-buttonPrimary transition hover:bg-buttonPrimary/10" @click="saveDefaultFilters">
                        Als Standard speichern
                    </button>
                    <button class="h-11 rounded-lg bg-buttonPrimary px-5 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover">
                        Filter anwenden
                    </button>
                </div>
            </div>
        </form>
    </section>
</template>


