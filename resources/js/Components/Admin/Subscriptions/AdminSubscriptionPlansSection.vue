<script setup>
const props = defineProps({
    filteredPlans: { type: Array, default: () => [] },
    actorLabels: { type: Object, required: true },
    editingPlanId: { type: [Number, String], default: null },
    moneyInputAttrs: { type: Object, required: true },
    formatPrice: { type: Function, required: true },
    limitLabel: { type: Function, required: true },
    formForPlan: { type: Function, required: true },
    addCountryPrice: { type: Function, required: true },
    removeCountryPrice: { type: Function, required: true },
    savePlan: { type: Function, required: true },
})

const emit = defineEmits(['update:editingPlanId'])

const togglePlan = (plan) => {
    emit('update:editingPlanId', props.editingPlanId === plan.id ? null : plan.id)
}
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-3">
        <article v-for="plan in filteredPlans" :key="plan.id" class="surface-card overflow-hidden">
            <div class="border-b border-border p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-primary">{{ plan.name }}</h2>
                            <span v-if="plan.badge" class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">{{ plan.badge }}</span>
                        </div>
                        <p class="mt-1 text-xs text-secondary">{{ actorLabels[plan.target_actor] || plan.target_actor }} - {{ plan.slug }}</p>
                    </div>
                    <button type="button" class="min-h-10 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:border-borderHover" @click="togglePlan(plan)">
                        {{ editingPlanId === plan.id ? 'Schließen' : 'Bearbeiten' }}
                    </button>
                </div>
            </div>

            <div v-if="editingPlanId !== plan.id" class="space-y-4 p-4">
                <p class="min-h-12 text-sm leading-relaxed text-secondary">{{ plan.description }}</p>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Monat</p>
                        <p class="mt-1 font-bold text-primary">{{ formatPrice(plan.monthly_price_cents, plan.currency) }}</p>
                    </div>
                    <div class="rounded-lg bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Jahr</p>
                        <p class="mt-1 font-bold text-primary">{{ formatPrice(plan.yearly_price_cents, plan.currency) }}</p>
                    </div>
                    <div class="rounded-lg bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Mitglieder</p>
                        <p class="mt-1 font-bold text-primary">{{ limitLabel(plan.member_limit) }}</p>
                    </div>
                    <div class="rounded-lg bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Speicher</p>
                        <p class="mt-1 font-bold text-primary">{{ plan.storage_gb }} GB</p>
                    </div>
                    <div class="rounded-lg bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Mindestlaufzeit</p>
                        <p class="mt-1 font-bold text-primary">{{ Number(plan.minimum_term_months || 0) }} Monate</p>
                    </div>
                    <div class="rounded-lg bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Kündigungsfrist</p>
                        <p class="mt-1 font-bold text-primary">{{ Number(plan.cancellation_notice_days || 0) }} Tage</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full px-2 py-1 font-semibold" :class="plan.is_active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">
                        {{ plan.is_active ? 'Aktiv' : 'Inaktiv' }}
                    </span>
                    <span class="rounded-full px-2 py-1 font-semibold" :class="plan.is_public ? 'bg-air-blue/15 text-air-blue' : 'bg-muted text-secondary'">
                        {{ plan.is_public ? 'Öffentlich' : 'Privat' }}
                    </span>
                    <span class="rounded-full bg-muted px-2 py-1 font-semibold text-secondary">
                        {{ plan.club_subscriptions_count }} Vereine
                    </span>
                    <span class="rounded-full bg-muted px-2 py-1 font-semibold text-secondary">
                        {{ plan.user_subscriptions_count }} Nutzer
                    </span>
                    <span class="rounded-full bg-muted px-2 py-1 font-semibold text-secondary">
                        {{ plan.country_prices?.length || 0 }} Länderpreise
                    </span>
                </div>
            </div>

            <form v-else class="space-y-4 p-4" @submit.prevent="savePlan(plan)">
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">Zielgruppe</label>
                    <select v-model="formForPlan(plan).target_actor" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="sportler">Sportler</option>
                        <option value="trainer">Trainer</option>
                        <option value="verein">Verein</option>
                        <option value="eltern">Eltern</option>
                        <option value="sponsor">Sponsor</option>
                        <option value="anbieter">Anbieter</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t('Beschreibung') }}</label>
                    <textarea v-model="formForPlan(plan).description" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Monat</label>
                        <input v-model="formForPlan(plan).monthly_price_cents" v-bind="moneyInputAttrs" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="10,99">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Jahr</label>
                        <input v-model="formForPlan(plan).yearly_price_cents" v-bind="moneyInputAttrs" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="99,00">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Mitglieder</label>
                        <input v-model="formForPlan(plan).member_limit" type="number" min="1" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="leer = unbegrenzt">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Teams</label>
                        <input v-model="formForPlan(plan).team_limit" type="number" min="1" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="leer = unbegrenzt">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Speicher GB</label>
                        <input v-model="formForPlan(plan).storage_gb" type="number" min="1" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Mindestlaufzeit Monate</label>
                        <input v-model="formForPlan(plan).minimum_term_months" type="number" min="0" max="60" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Kündigungsfrist Tage</label>
                        <input v-model="formForPlan(plan).cancellation_notice_days" type="number" min="0" max="365" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Badge</label>
                        <input v-model="formForPlan(plan).badge" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-semibold uppercase text-secondary">CTA</label>
                        <input v-model="formForPlan(plan).cta_label" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                </div>

                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="formForPlan(plan).is_public" type="checkbox" class="rounded border-border bg-inputBg">
                        Öffentlich
                    </label>
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="formForPlan(plan).is_active" type="checkbox" class="rounded border-border bg-inputBg">
                        Aktiv
                    </label>
                </div>

                <div class="rounded-lg border border-border bg-bg p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-secondary">Länderpreise</p>
                            <p class="mt-1 text-xs text-secondary">Land, Währung und Preis pro Plan steuern.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="addCountryPrice(plan)">
                            + Land
                        </button>
                    </div>

                    <div class="mt-3 space-y-3">
                        <div
                            v-for="(price, index) in formForPlan(plan).country_prices"
                            :key="`${plan.id}-${index}`"
                            class="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-[4rem_5rem_1fr_1fr_auto]"
                        >
                            <input v-model="price.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="DE">
                            <input v-model="price.currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                            <input v-model="price.monthly_price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Monat">
                            <input v-model="price.yearly_price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Jahr">
                            <div class="flex items-center gap-2">
                                <label class="flex items-center gap-1 text-xs text-primary">
                                    <input v-model="price.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                                    Aktiv
                                </label>
                                <button type="button" class="rounded-lg border border-border px-2 py-1 text-xs text-primary hover:bg-muted" @click="removeCountryPrice(plan, index)">
                                    Entfernen
                                </button>
                            </div>
                        </div>
                        <p v-if="!formForPlan(plan).country_prices.length" class="text-xs text-secondary">
                            Ohne Länderpreis wird der Standardpreis des Plans verwendet.
                        </p>
                    </div>
                </div>

                <button class="w-full rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="formForPlan(plan).processing">
                    Speichern
                </button>
            </form>
        </article>
    </section>
</template>
