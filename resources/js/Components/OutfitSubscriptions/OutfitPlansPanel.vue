<script setup>
defineProps({
    plans: { type: Array, default: () => [] },
    formatMoney: { type: Function, required: true },
    sponsorLogoUrl: { type: Function, required: true },
    initials: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    brandingLabel: { type: Function, required: true },
    subscribe: { type: Function, required: true },
})
</script>

<template>
    <section id="outfit-plans">
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-primary">Pläne wählen</h2>
                <p class="mt-1 text-sm text-secondary">Sponsor-Subventionen werden direkt vom Monatsbetrag abgezogen.</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="plan in plans" :key="plan.id" class="flex flex-col rounded-lg border border-border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-borderHover hover:shadow-lg">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-primary">{{ plan.name }}</h3>
                        <div v-if="plan.sponsor" class="mt-2 flex items-center gap-2">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-inputBg text-xs font-black text-primary">
                                <img v-if="sponsorLogoUrl(plan.sponsor)" :src="sponsorLogoUrl(plan.sponsor)" :alt="plan.sponsor.name" class="h-full w-full object-contain p-1">
                                <span v-else>{{ initials(plan.sponsor.name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-secondary">Subventioniert von</p>
                                <p class="truncate text-sm font-semibold text-accent">{{ plan.sponsor.name }}</p>
                            </div>
                        </div>
                    </div>
                    <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-primary">{{ plan.items_per_box }} Teile</span>
                </div>
                <p class="mt-4 min-h-16 text-sm leading-6 text-secondary">{{ plan.description }}</p>
                <div class="mt-4 rounded-lg bg-inputBg p-4">
                    <p v-if="plan.sponsor_discount_cents" class="text-xs text-secondary">
                        Statt {{ formatMoney(plan.monthly_price_cents, plan.currency) }} - Rabatt {{ formatMoney(plan.sponsor_discount_cents, plan.currency) }}
                    </p>
                    <p class="text-2xl font-bold text-primary">{{ formatMoney(plan.effective_monthly_price_cents, plan.currency) }}</p>
                    <p class="text-xs text-secondary">pro Monat</p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span v-for="sport in plan.sports" :key="sport" class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">{{ sportLabel(sport) }}</span>
                    <span v-if="plan.branding_type !== 'none'" class="rounded-full bg-accent/15 px-3 py-1 text-xs font-semibold text-accent">{{ brandingLabel(plan.branding_type) }}</span>
                </div>
                <button type="button" class="mt-5 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90" @click="subscribe(plan)">
                    Plan auswählen
                </button>
            </article>
        </div>
    </section>
</template>

