<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
    plans: { type: Array, default: () => [] },
    sponsors: { type: Array, default: () => [] },
    editingPlanId: { type: [Number, String], default: null },
    editSportQueries: { type: Object, required: true },
    formatMoney: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    sportCategoryLabel: { type: Function, required: true },
    selectedSports: { type: Function, required: true },
    filteredSports: { type: Function, required: true },
    addSport: { type: Function, required: true },
    removeSport: { type: Function, required: true },
    formFor: { type: Function, required: true },
    savePlan: { type: Function, required: true },
    destroyPlan: { type: Function, required: true },
})

const emit = defineEmits(['update:editingPlanId'])

const editingPlanIdModel = computed({
    get: () => props.editingPlanId,
    set: (value) => emit('update:editingPlanId', value),
})

const addEditSport = (plan, sport) => {
    props.addSport(props.formFor(plan), sport)
    props.editSportQueries[plan.id] = ''
}
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-2">
        <article v-for="plan in plans" :key="plan.id" class="rounded-lg border border-border bg-card p-5">
            <template v-if="editingPlanIdModel !== plan.id">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-primary">{{ plan.name }}</h3>
                        <p class="mt-1 text-sm text-secondary">{{ plan.description }}</p>
                        <p v-if="plan.sponsor" class="mt-2 text-sm font-semibold text-accent">Sponsor: {{ plan.sponsor.name }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-inputBg" @click="editingPlanIdModel = plan.id">Bearbeiten</button>
                        <button class="rounded-lg border border-red-500/50 px-3 py-2 text-sm text-red-300 hover:bg-red-500/10" @click="destroyPlan(plan)">{{ t('commerce.ui.remove') }}</button>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">{{ t('outfit_admin.ui.price') }}</p>
                        <p class="font-bold text-primary">{{ formatMoney(plan.monthly_price_cents, plan.currency) }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Rabatt</p>
                        <p class="font-bold text-primary">{{ formatMoney(plan.sponsor_discount_cents, plan.currency) }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Box</p>
                        <p class="font-bold text-primary">{{ plan.items_per_box }} Teile</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Abos</p>
                        <p class="font-bold text-primary">{{ plan.subscriptions_count || 0 }}</p>
                    </div>
                </div>
                <div v-if="plan.sports?.length" class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="sport in plan.sports"
                        :key="sport"
                        class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary"
                    >
                        {{ sportLabel(sport) }}
                    </span>
                </div>
                <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                    <p class="text-xs uppercase text-secondary">Vertrag</p>
                    <p class="mt-1 font-semibold text-primary">{{ plan.contract_title || `Outfit-Abo Vertrag ${plan.name}` }}</p>
                    <p class="mt-2 text-sm text-secondary">
                        Mindestlaufzeit {{ plan.minimum_term_months ?? 3 }} Monate · Pause ab Monat {{ plan.pause_allowed_after_months ?? 3 }} · Kündigungsfrist {{ plan.cancellation_notice_days ?? 14 }} Tage
                    </p>
                    <ul v-if="plan.contract_terms?.length" class="mt-3 space-y-1 text-sm text-secondary">
                        <li v-for="term in plan.contract_terms.slice(0, 3)" :key="term">- {{ term }}</li>
                    </ul>
                </div>
            </template>

            <form v-else class="grid gap-4 md:grid-cols-2" @submit.prevent="savePlan(plan)">
                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold text-primary">{{ t('outfit_ui.name') }}</span>
                    <input v-model="formFor(plan).name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold text-primary">{{ t('outfit_ui.description') }}</span>
                    <textarea v-model="formFor(plan).description" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea>
                </label>
                <div class="grid gap-4 rounded-lg border border-border bg-inputBg p-4 md:col-span-2">
                    <p class="text-sm font-bold text-primary">Personalisierter Vertrag für diesen Plan</p>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Vertragstitel</span>
                        <input v-model="formFor(plan).contract_title" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                    </label>
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Mindestlaufzeit Monate</span>
                            <input v-model="formFor(plan).minimum_term_months" type="number" min="0" max="24" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Pause ab Monat</span>
                            <input v-model="formFor(plan).pause_allowed_after_months" type="number" min="0" max="24" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Kündigungsfrist Tage</span>
                            <input v-model="formFor(plan).cancellation_notice_days" type="number" min="0" max="90" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Vertragsklauseln</span>
                        <textarea v-model="formFor(plan).contract_terms_text" rows="5" class="mt-1 w-full rounded-lg border-border bg-card text-primary"></textarea>
                    </label>
                </div>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Preis EUR</span>
                    <input v-model="formFor(plan).monthly_price_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">{{ t('outfit_admin.ui.discount_eur') }}</span>
                    <input v-model="formFor(plan).sponsor_discount_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sponsor</span>
                    <select v-model="formFor(plan).sponsor_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Kein Sponsor</option>
                        <option v-for="sponsor in sponsors" :key="sponsor.id" :value="sponsor.id">{{ sponsor.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Branding</span>
                    <select v-model="formFor(plan).branding_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="none">Kein Branding</option>
                        <option value="sponsor_logo">Sponsor-Logo</option>
                        <option value="club_logo">Vereinslogo</option>
                        <option value="custom">Individuell</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Grössen</span>
                    <input v-model="formFor(plan).sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">{{ t('commerce.ui.sports') }}</span>
                    <div class="mt-1 rounded-lg border border-border bg-inputBg p-3">
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="sport in selectedSports(formFor(plan))"
                                :key="sport"
                                class="inline-flex items-center gap-2 rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary"
                            >
                                {{ sportLabel(sport) }}
                                <button type="button" class="text-secondary hover:text-error" @click="removeSport(formFor(plan), sport)">
                                    <i class="las la-times"></i>
                                </button>
                            </span>
                            <span v-if="!selectedSports(formFor(plan)).length" class="text-sm text-secondary">Noch keine Sportart gewählt.</span>
                        </div>

                        <div class="mt-3">
                            <div class="relative">
                                <i class="las la-search absolute start-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                                <input
                                    v-model="editSportQueries[plan.id]"
                                    type="text"
                                    class="w-full rounded-lg border-border bg-card py-2 pe-3 ps-10 text-sm text-primary"
                                    placeholder="Sportart filtern und aus Liste wählen"
                                />
                            </div>
                            <div class="mt-2 grid max-h-56 gap-2 overflow-y-auto pe-1">
                                <button
                                    v-for="sport in filteredSports(editSportQueries[plan.id], formFor(plan))"
                                    :key="sport.slug"
                                    type="button"
                                    class="rounded-lg border border-border bg-card px-3 py-2 text-start text-sm transition hover:border-borderHover hover:bg-muted"
                                    @click="addEditSport(plan, sport)"
                                >
                                    <span class="block font-semibold text-primary">{{ sportLabel(sport.slug) }}</span>
                                    <span v-if="sportCategoryLabel(sport)" class="block text-xs text-secondary">{{ sportCategoryLabel(sport) }}</span>
                                </button>
                                <p v-if="!filteredSports(editSportQueries[plan.id], formFor(plan)).length" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary">
                                    Keine weitere Sportart gefunden.
                                </p>
                            </div>
                        </div>
                    </div>
                </label>
                <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                    <input v-model="formFor(plan).is_public" type="checkbox" class="rounded border-border bg-card" />
                    <span class="text-sm text-primary">Öffentlich</span>
                </label>
                <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                    <input v-model="formFor(plan).is_active" type="checkbox" class="rounded border-border bg-card" />
                    <span class="text-sm text-primary">Aktiv</span>
                </label>
                <div class="flex gap-2 md:col-span-2">
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm text-primary" @click="editingPlanIdModel = null">Abbrechen</button>
                </div>
            </form>
        </article>
    </section>
</template>
