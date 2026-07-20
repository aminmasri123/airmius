<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    shopCategoryTabs: { type: Array, default: () => [] },
    shopView: { type: String, default: 'all' },
    selectedClubId: { type: [String, Number], default: '' },
    provider: { type: String, default: 'bank_transfer' },
    interval: { type: String, default: 'monthly' },
    clubs: { type: Array, default: () => [] },
    accountPlans: { type: Array, default: () => [] },
    visibleAddons: { type: Array, default: () => [] },
    visibleShopProducts: { type: Array, default: () => [] },
    visibleShopProductTitle: { type: String, default: 'Kurse und E-Learning' },
    visibleShopProductDescription: { type: String, default: '' },
    outfitPlans: { type: Array, default: () => [] },
    showAccountShop: { type: Boolean, default: false },
    showProductShop: { type: Boolean, default: false },
    showOutfitShop: { type: Boolean, default: false },
    formatMoney: { type: Function, required: true },
    addonPrice: { type: Function, required: true },
})

const emit = defineEmits([
    'update:shopView',
    'update:selectedClubId',
    'update:provider',
    'update:interval',
    'checkout-account-plan',
    'checkout-addon',
    'add-to-cart',
])
</script>

<template>
    <section class="surface-card p-5">
        <div class="mb-5 flex gap-2 overflow-x-auto">
            <button
                v-for="category in shopCategoryTabs"
                :key="category.key"
                type="button"
                class="flex min-h-10 shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition"
                :class="shopView === category.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-bg text-secondary hover:bg-muted hover:text-primary'"
                @click="emit('update:shopView', category.key)"
            >
                <span>{{ category.label }}</span>
                <span v-if="category.count" class="rounded-full bg-white/15 px-2 py-0.5 text-xs">{{ category.count }}</span>
            </button>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="text-xs font-semibold uppercase text-secondary">Verein für Add-ons</label>
                <select :value="selectedClubId" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" @change="emit('update:selectedClubId', $event.target.value)">
                    <option value="">Privat / kein Verein</option>
                    <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-secondary">Zahlungsart</label>
                <select :value="provider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" @change="emit('update:provider', $event.target.value)">
                    <option value="bank_transfer">Überweisung</option>
                    <option value="stripe">Stripe</option>
                    <option value="paypal">PayPal</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-secondary">Add-on Laufzeit</label>
                <select :value="interval" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" @change="emit('update:interval', $event.target.value)">
                    <option value="monthly">Monatlich</option>
                    <option value="yearly">Jährlich</option>
                </select>
            </div>
        </div>
    </section>

    <section v-if="showAccountShop" class="grid gap-5 lg:grid-cols-3">
        <article v-for="plan in accountPlans" :key="plan.id" class="surface-card flex flex-col p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Konto-Abo</p>
            <div class="mt-1 flex items-start justify-between gap-3">
                <h2 class="text-lg font-semibold text-primary">{{ plan.name }}</h2>
                <span v-if="plan.is_owned" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">Aktiv</span>
                <span v-else-if="plan.badge" class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">{{ plan.badge }}</span>
            </div>
            <p class="mt-2 flex-1 text-sm text-secondary">{{ plan.description }}</p>
            <p class="mt-4 text-2xl font-bold text-primary">{{ formatMoney(plan.monthly_price_cents, plan.currency) }}</p>
            <p class="text-sm text-secondary">{{ plan.monthly_price_cents ? 'pro Monat' : 'kostenlos' }}</p>
            <p v-if="plan.yearly_price_cents" class="mt-1 text-xs text-secondary">{{ formatMoney(plan.yearly_price_cents, plan.currency) }} pro Jahr</p>
            <p v-if="plan.localized_price" class="mt-1 text-xs text-air-blue">Lokaler Preis für {{ plan.pricing_country }}</p>
            <dl class="mt-4 border-t border-border pt-3 text-sm text-secondary">
                <div class="flex justify-between gap-3">
                    <dt>Speicher</dt>
                    <dd class="font-semibold text-primary">{{ plan.storage_gb }} GB</dd>
                </div>
            </dl>
            <ul class="mt-4 flex-1 space-y-2 text-sm text-secondary">
                <li v-for="feature in plan.features" :key="feature" class="flex gap-2">
                    <i class="las la-check mt-0.5 text-success"></i>
                    <span>{{ feature }}</span>
                </li>
            </ul>
            <div v-if="plan.is_owned" class="mt-5 rounded-lg border border-success/30 bg-success/10 px-4 py-2 text-center text-sm font-semibold text-success">
                Du besitzt diesen Plan
            </div>
            <div v-else-if="!plan.monthly_price_cents" class="mt-5 rounded-lg border border-border px-4 py-2 text-center text-sm font-semibold text-primary">
                Kostenloser Basisplan
            </div>
            <div v-else class="mt-5 grid gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                    @click="emit('checkout-account-plan', plan, 'stripe')"
                >
                    Mit Stripe zahlen
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    @click="emit('checkout-account-plan', plan, 'paypal')"
                >
                    Mit PayPal zahlen
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    @click="emit('checkout-account-plan', plan, 'bank_transfer')"
                >
                    Per Überweisung zahlen
                </button>
            </div>
        </article>
        <article v-for="addon in visibleAddons" :key="addon.id" class="surface-card flex flex-col p-5">
            <h2 class="text-lg font-semibold text-primary">{{ addon.name }}</h2>
            <p class="mt-2 flex-1 text-sm text-secondary">{{ addon.description }}</p>
            <p class="mt-4 text-2xl font-bold text-primary">{{ formatMoney(addonPrice(addon)) }}</p>
            <button class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('checkout-addon', addon)">
                Add-on buchen
            </button>
        </article>
    </section>

    <section v-if="showProductShop" class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">{{ visibleShopProductTitle }}</h2>
            <p class="mt-1 text-sm text-secondary">{{ visibleShopProductDescription }}</p>
        </div>
        <div class="grid gap-4 p-5 lg:grid-cols-3">
            <article v-for="product in visibleShopProducts" :key="product.id" class="overflow-hidden rounded-lg border border-border bg-bg">
                <div class="aspect-[4/3] bg-inputBg">
                    <img v-if="product.image_url" :src="product.image_url" :alt="product.title" width="480" height="360" loading="lazy" decoding="async" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full items-center justify-center">
                        <i class="las la-store text-5xl text-air-blue"></i>
                    </div>
                </div>
                <div class="p-4">
                    <p class="text-xs uppercase text-secondary">{{ product.category }}</p>
                    <h3 class="mt-1 font-semibold text-primary">{{ product.title }}</h3>
                    <p class="mt-2 min-h-12 text-sm text-secondary">{{ product.description }}</p>
                    <div class="mt-4 flex items-center justify-between gap-3">
                        <span class="text-lg font-bold text-primary">{{ formatMoney(product.price_cents, product.currency) }}</span>
                        <div class="flex gap-2">
                            <Link :href="route('auth.commerce.products.show', product.id)" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary">Details</Link>
                            <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('add-to-cart', product)">
                                In den Warenkorb
                            </button>
                        </div>
                    </div>
                </div>
            </article>
            <p v-if="!visibleShopProducts.length" class="text-sm text-secondary">Noch keine passenden Angebote veröffentlicht.</p>
        </div>
    </section>

    <section v-if="showOutfitShop" class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">Sportkleidung-Abos</h2>
            <p class="mt-1 text-sm text-secondary">Monatliche Outfit-Boxen mit Style-Profil, Lieferübersicht und optionalem Sponsor-Rabatt.</p>
        </div>
        <div class="grid gap-4 p-5 lg:grid-cols-3">
            <article v-for="plan in outfitPlans" :key="plan.id" class="rounded-lg border border-border bg-bg p-4">
                <p class="text-xs uppercase text-secondary">{{ plan.items_per_box }} Teile pro Box</p>
                <h3 class="mt-1 font-semibold text-primary">{{ plan.name }}</h3>
                <p class="mt-2 min-h-12 text-sm text-secondary">{{ plan.description }}</p>
                <p v-if="plan.sponsor" class="mt-2 text-xs font-semibold text-air-blue">Subventioniert von {{ plan.sponsor.name }}</p>
                <div class="mt-4 flex items-center justify-between gap-3">
                    <div>
                        <span class="text-lg font-bold text-primary">{{ formatMoney(plan.effective_monthly_price_cents, plan.currency) }}</span>
                        <p v-if="plan.sponsor_discount_cents" class="text-xs text-secondary">statt {{ formatMoney(plan.monthly_price_cents, plan.currency) }}</p>
                    </div>
                    <Link :href="route('auth.outfit-subscriptions.index')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                        Zum Outfit-Abo
                    </Link>
                </div>
            </article>
            <p v-if="!outfitPlans.length" class="text-sm text-secondary">Noch keine Outfit-Abos freigegeben.</p>
        </div>
    </section>
</template>




