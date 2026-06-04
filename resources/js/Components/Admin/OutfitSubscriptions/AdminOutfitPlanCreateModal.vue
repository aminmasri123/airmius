<script setup>
import { computed } from 'vue'

const props = defineProps({
    activeTab: { type: String, required: true },
    createPlanModalOpen: { type: Boolean, default: false },
    newPlan: { type: Object, required: true },
    newSportQuery: { type: String, default: '' },
    sponsors: { type: Array, default: () => [] },
    selectedSports: { type: Function, required: true },
    filteredSports: { type: Function, required: true },
    addSport: { type: Function, required: true },
    removeSport: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    sportCategoryLabel: { type: Function, required: true },
    storePlan: { type: Function, required: true },
    openCreatePlanModal: { type: Function, required: true },
    closeCreatePlanModal: { type: Function, required: true },
})

const emit = defineEmits(['update:newSportQuery'])

const sportQuery = computed({
    get: () => props.newSportQuery,
    set: (value) => emit('update:newSportQuery', value),
})

const selectSport = (sport) => {
    props.addSport(props.newPlan, sport)
    emit('update:newSportQuery', '')
}
</script>

<template>
    <section v-if="activeTab === 'plans'" class="rounded-lg border border-border bg-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-bold text-primary">Pläne verwalten</h2>
                <p class="mt-1 text-sm text-secondary">Erstelle neue Outfit-Abo-Pläne in einem fokussierten Dialog.</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90"
                @click="openCreatePlanModal"
            >
                <i class="las la-plus text-lg"></i>
                Neuen Plan erstellen
            </button>
        </div>
    </section>

    <Teleport to="body">
        <div v-if="createPlanModalOpen" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
            <div class="w-full max-w-5xl overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-border p-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-accent">Outfit-Abo Plan</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">Neuen Plan erstellen</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                            Lege Preis, Sponsor, Branding, Sportarten und Box-Inhalt für einen neuen Outfit-Abo-Plan fest.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeCreatePlanModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <form class="max-h-[75vh] overflow-y-auto p-5" @submit.prevent="storePlan">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label class="block xl:col-span-2">
                            <span class="text-sm font-semibold text-primary">Name</span>
                            <input v-model="newPlan.name" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Runner Box" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Preis EUR</span>
                            <input v-model="newPlan.monthly_price_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Sponsor-Rabatt EUR</span>
                            <input v-model="newPlan.sponsor_discount_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                        </label>
                        <label class="block xl:col-span-2">
                            <span class="text-sm font-semibold text-primary">Beschreibung</span>
                            <textarea v-model="newPlan.description" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea>
                        </label>
                        <div class="grid gap-4 rounded-lg border border-border bg-inputBg p-4 xl:col-span-4">
                            <div>
                                <p class="text-sm font-bold text-primary">Personalisierter Vertrag für diesen Plan</p>
                                <p class="mt-1 text-xs leading-5 text-secondary">
                                    Diese Werte werden beim Abschluss mit Kundendaten, Preis und Plan als Vertrags-Snapshot gespeichert.
                                </p>
                            </div>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Vertragstitel</span>
                                <input v-model="newPlan.contract_title" class="mt-1 w-full rounded-lg border-border bg-card text-primary" placeholder="Outfit-Abo Vertrag Runner Box" />
                            </label>
                            <div class="grid gap-4 md:grid-cols-3">
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Mindestlaufzeit Monate</span>
                                    <input v-model="newPlan.minimum_term_months" type="number" min="0" max="24" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Pause ab Monat</span>
                                    <input v-model="newPlan.pause_allowed_after_months" type="number" min="0" max="24" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Kündigungsfrist Tage</span>
                                    <input v-model="newPlan.cancellation_notice_days" type="number" min="0" max="90" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                                </label>
                            </div>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Vertragsklauseln</span>
                                <textarea
                                    v-model="newPlan.contract_terms_text"
                                    rows="5"
                                    class="mt-1 w-full rounded-lg border-border bg-card text-primary"
                                    placeholder="Eine Klausel pro Zeile, z.B. Pause und Kündigung gelten nur für zukünftige Lieferungen."
                                ></textarea>
                            </label>
                        </div>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Sponsor</span>
                            <select v-model="newPlan.sponsor_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="">Kein Sponsor</option>
                                <option v-for="sponsor in sponsors" :key="sponsor.id" :value="sponsor.id">{{ sponsor.name }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Branding</span>
                            <select v-model="newPlan.branding_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="none">Kein Branding</option>
                                <option value="sponsor_logo">Sponsor-Logo</option>
                                <option value="club_logo">Vereinslogo</option>
                                <option value="custom">Individuell</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Zielgruppe</span>
                            <select v-model="newPlan.target_gender" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="unisex">Unisex</option>
                                <option value="women">Damen</option>
                                <option value="men">Herren</option>
                                <option value="kids">Kinder</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Teile pro Box</span>
                            <input v-model="newPlan.items_per_box" type="number" min="1" max="12" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Grössen</span>
                            <input v-model="newPlan.sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Sportarten</span>
                            <div class="mt-1 rounded-lg border border-border bg-inputBg p-3">
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="sport in selectedSports(newPlan)"
                                        :key="sport"
                                        class="inline-flex items-center gap-2 rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary"
                                    >
                                        {{ sportLabel(sport) }}
                                        <button type="button" class="text-secondary hover:text-error" @click="removeSport(newPlan, sport)">
                                            <i class="las la-times"></i>
                                        </button>
                                    </span>
                                    <span v-if="!selectedSports(newPlan).length" class="text-sm text-secondary">Noch keine Sportart gewählt.</span>
                                </div>

                                <div class="mt-3">
                                    <div class="relative">
                                        <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                                        <input
                                            v-model="sportQuery"
                                            type="text"
                                            class="w-full rounded-lg border-border bg-card py-2 pl-10 pr-3 text-sm text-primary"
                                            placeholder="Sportart filtern und aus Liste wählen"
                                        />
                                    </div>
                                    <div class="mt-2 grid max-h-56 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                                        <button
                                            v-for="sport in filteredSports(sportQuery, newPlan)"
                                            :key="sport.slug"
                                            type="button"
                                            class="rounded-lg border border-border bg-card px-3 py-2 text-left text-sm transition hover:border-borderHover hover:bg-muted"
                                            @click="selectSport(sport)"
                                        >
                                            <span class="block font-semibold text-primary">{{ sportLabel(sport.slug) }}</span>
                                            <span v-if="sportCategoryLabel(sport)" class="block text-xs text-secondary">{{ sportCategoryLabel(sport) }}</span>
                                        </button>
                                        <p v-if="!filteredSports(sportQuery, newPlan).length" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary sm:col-span-2">
                                            Keine weitere Sportart gefunden.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                            <input v-model="newPlan.is_public" type="checkbox" class="rounded border-border bg-card" />
                            <span class="text-sm text-primary">Öffentlich</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                            <input v-model="newPlan.is_active" type="checkbox" class="rounded border-border bg-card" />
                            <span class="text-sm text-primary">Aktiv</span>
                        </label>
                    </div>
                    <div class="sticky bottom-0 -mx-5 mt-6 flex flex-col-reverse gap-2 border-t border-border bg-card px-5 py-4 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeCreatePlanModal">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="newPlan.processing">
                            Plan erstellen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

