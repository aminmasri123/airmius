<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    addons: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    outfitPlans: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    myProducts: { type: Array, default: () => [] },
    myCampaigns: { type: Array, default: () => [] },
    websiteRequests: { type: Array, default: () => [] },
    payoutProfile: { type: Object, default: null },
    payoutSummary: { type: Object, default: () => ({}) },
})

const page = usePage()
const selectedClubId = ref(props.clubs[0]?.id || '')
const provider = ref('bank_transfer')
const interval = ref('monthly')
const acceptedTerms = ref(false)
const productForm = useForm({
    club_id: '',
    title: '',
    description: '',
    image_url: '',
    category: 'product',
    price_cents: 0,
})
const campaignForm = useForm({
    club_id: '',
    name: '',
    description: '',
    target_url: '',
    budget_cents: 0,
})
const websiteForm = useForm({
    club_id: '',
    domain: '',
    goals: '',
    notes: '',
})
const payoutForm = useForm({
    account_holder: props.payoutProfile?.account_holder || '',
    iban: props.payoutProfile?.iban || '',
    bic: props.payoutProfile?.bic || '',
    paypal_email: props.payoutProfile?.paypal_email || '',
    tax_number: props.payoutProfile?.tax_number || '',
    notes: props.payoutProfile?.notes || '',
})

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const formatPercent = (value) => `${Number(value || 0).toFixed(2).replace('.', ',')} %`

const ctr = (clicks, impressions) => {
    if (!Number(impressions || 0)) {
        return 0
    }

    return (Number(clicks || 0) / Number(impressions || 0)) * 100
}

const addonPrice = (addon) => interval.value === 'yearly' ? addon.yearly_price_cents : addon.monthly_price_cents

const checkoutAddon = (addon) => {
    router.post(route('auth.commerce.addons.checkout', addon.id), {
        provider: provider.value,
        billing_interval: interval.value,
        club_id: selectedClubId.value || null,
        accepted_terms: acceptedTerms.value,
    })
}

const checkoutProduct = (product) => {
    router.post(route('auth.commerce.products.checkout', product.id), {
        provider: provider.value,
        accepted_terms: acceptedTerms.value,
    })
}

const subscribeOutfitPlan = (plan) => {
    router.post(route('auth.outfit-subscriptions.store', plan.id), {}, { preserveScroll: true })
}

const activeOrders = computed(() => props.orders.filter((order) => ['pending', 'awaiting_transfer', 'completed'].includes(order.status)))

const storeProduct = () => productForm.post(route('auth.commerce.products.store'), {
    preserveScroll: true,
    onSuccess: () => productForm.reset('title', 'description', 'image_url'),
})

const storeCampaign = () => campaignForm.post(route('auth.commerce.campaigns.store'), {
    preserveScroll: true,
    onSuccess: () => campaignForm.reset('name', 'description', 'target_url'),
})

const storeWebsiteRequest = () => websiteForm.post(route('auth.commerce.website-requests.store'), {
    preserveScroll: true,
    onSuccess: () => websiteForm.reset('domain', 'goals', 'notes'),
})

const storePayoutProfile = () => payoutForm.post(route('auth.commerce.payout-profile.store'), {
    preserveScroll: true,
})

const reportIssue = (order) => {
    const note = window.prompt('Was ist das Problem mit dieser Bestellung?')

    if (!note) {
        return
    }

    router.post(route('auth.commerce.orders.issue', order.id), {
        issue_note: note,
    }, { preserveScroll: true })
}
</script>

<template>
    <Head title="Marketplace" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Marketplace</p>
            <h1 class="mt-1 text-2xl font-bold text-primary">Add-ons und Angebote</h1>
            <p class="mt-2 max-w-3xl text-sm text-secondary">
                Buche Airmius Add-ons für deinen Verein oder kaufe Produkte, Kurse und Services aus dem Marketplace.
            </p>
            <div v-if="page.props.flash?.success" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                {{ page.props.flash.success }}
            </div>
        </section>

        <section class="surface-card p-5">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">Verein für Add-ons</label>
                    <select v-model="selectedClubId" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="">Privat / kein Verein</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">Zahlungsart</label>
                    <select v-model="provider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="bank_transfer">Überweisung</option>
                        <option value="stripe">Stripe</option>
                        <option value="paypal">PayPal</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">Add-on Laufzeit</label>
                    <select v-model="interval" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="monthly">Monatlich</option>
                        <option value="yearly">Jährlich</option>
                    </select>
                </div>
            </div>
            <label class="mt-4 flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                <input v-model="acceptedTerms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                <span>
                    Ich akzeptiere AGB, Widerrufshinweise und nehme zur Kenntnis, dass Marketplace-Angebote je nach Produkt durch den jeweiligen Anbieter erbracht werden.
                    <Link :href="route('terms.show')" class="text-air-blue underline">AGB</Link>
                    <span> · </span>
                    <Link :href="route('legal.withdrawal')" class="text-air-blue underline">Widerruf</Link>
                </span>
            </label>
        </section>

        <section class="grid gap-5 lg:grid-cols-3">
            <article v-for="addon in addons" :key="addon.id" class="surface-card flex flex-col p-5">
                <h2 class="text-lg font-semibold text-primary">{{ addon.name }}</h2>
                <p class="mt-2 flex-1 text-sm text-secondary">{{ addon.description }}</p>
                <p class="mt-4 text-2xl font-bold text-primary">{{ formatMoney(addonPrice(addon)) }}</p>
                <button class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="checkoutAddon(addon)">
                    Add-on buchen
                </button>
            </article>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Marketplace-Produkte</h2>
                <p class="mt-1 text-sm text-secondary">Produkte, Kurse, Camps und Services kaufen oder reservieren.</p>
            </div>
            <div class="grid gap-4 p-5 lg:grid-cols-3">
                <article v-for="product in products" :key="product.id" class="overflow-hidden rounded-lg border border-border bg-bg">
                    <div class="aspect-[4/3] bg-inputBg">
                        <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover" />
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
                            <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="checkoutProduct(product)">
                                Kaufen
                            </button>
                        </div>
                    </div>
                    </div>
                </article>
                <p v-if="!products.length" class="text-sm text-secondary">Noch keine öffentlichen Marketplace-Produkte.</p>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Sportkleidung-Abos</h2>
                <p class="mt-1 text-sm text-secondary">Monatliche Outfit-Boxen mit Style-Profil, Lieferuebersicht und optionalem Sponsor-Rabatt.</p>
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
                        <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="subscribeOutfitPlan(plan)">
                            Abo starten
                        </button>
                    </div>
                </article>
                <p v-if="!outfitPlans.length" class="text-sm text-secondary">Noch keine Outfit-Abos freigegeben.</p>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Eigenes Angebot einreichen</h2>
                <form class="mt-4 grid gap-3" @submit.prevent="storeProduct">
                    <select v-model="productForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="">Privat / Anbieter</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <input v-model="productForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                    <input v-model="productForm.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Produktbild URL, empfohlen 1200 x 1200 px">
                    <select v-model="productForm.category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="product">Produkt</option>
                        <option value="course">Kurs</option>
                        <option value="camp">Camp</option>
                        <option value="service">Dienstleistung</option>
                    </select>
                    <input v-model="productForm.price_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in Cent">
                    <textarea v-model="productForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Zur Prüfung einreichen</button>
                </form>
                <p class="mt-4 text-sm text-secondary">{{ myProducts.length }} eigene Angebote</p>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Sponsor-Kampagne vorbereiten</h2>
                <form class="mt-4 grid gap-3" @submit.prevent="storeCampaign">
                    <input v-model="campaignForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kampagnenname">
                    <input v-model="campaignForm.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL">
                    <input v-model="campaignForm.budget_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Budget in Cent">
                    <textarea v-model="campaignForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Kampagne speichern</button>
                </form>
                <p class="mt-4 text-sm text-secondary">{{ myCampaigns.length }} eigene Kampagnen</p>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Vereinswebsite erstellen lassen</h2>
                <form class="mt-4 grid gap-3" @submit.prevent="storeWebsiteRequest">
                    <select v-model="websiteForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="">Verein später klären</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <input v-model="websiteForm.domain" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gewuenschte Domain">
                    <textarea v-model="websiteForm.goals" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Was soll die Website können?"></textarea>
                    <textarea v-model="websiteForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Weitere Hinweise"></textarea>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Anfrage senden</button>
                </form>
                <p class="mt-4 text-sm text-secondary">{{ websiteRequests.length }} Website-Anfragen</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Auszahlungsdaten</h2>
                <p class="mt-1 text-sm text-secondary">Hinterlege IBAN oder PayPal, damit Airmius Marketplace-Erlöse nach Prüfung auszahlen kann.</p>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storePayoutProfile">
                    <input v-model="payoutForm.account_holder" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kontoinhaber">
                    <input v-model="payoutForm.paypal_email" type="email" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PayPal-E-Mail">
                    <input v-model="payoutForm.iban" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="IBAN">
                    <input v-model="payoutForm.bic" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="BIC">
                    <input v-model="payoutForm.tax_number" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Steuernummer optional">
                    <textarea v-model="payoutForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Hinweise für Auszahlung"></textarea>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Auszahlungsdaten speichern</button>
                </form>
                <p v-if="payoutProfile" class="mt-3 text-sm text-secondary">Status: {{ payoutProfile.status }}</p>
            </article>

            <aside class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Offene Auszahlung</h2>
                <div class="mt-4 space-y-3">
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Bestellungen</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ payoutSummary.pending_orders || 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Brutto</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.gross_cents) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Provision</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.commission_cents) }}</p>
                    </div>
                    <div class="rounded-lg border border-success/30 bg-success/10 p-3">
                        <p class="text-xs uppercase text-success">Auszahlbar</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.amount_cents) }}</p>
                    </div>
                </div>
            </aside>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Meine Ads-Kampagnen</h2>
                <p class="mt-1 text-sm text-secondary">Status, Impressionen, Klicks und CTR deiner vorbereiteten oder aktiven Kampagnen.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-border text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Kampagne</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Impressionen</th>
                            <th class="px-5 py-3">Klicks</th>
                            <th class="px-5 py-3">CTR</th>
                            <th class="px-5 py-3">Budget</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="campaign in myCampaigns" :key="campaign.id">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ campaign.name }}</p>
                                <p class="text-xs text-secondary">{{ campaign.target_url || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ campaign.status }}</td>
                            <td class="px-5 py-3 text-secondary">{{ campaign.impressions || 0 }}</td>
                            <td class="px-5 py-3 text-secondary">{{ campaign.clicks || 0 }}</td>
                            <td class="px-5 py-3 text-secondary">{{ formatPercent(ctr(campaign.clicks, campaign.impressions)) }}</td>
                            <td class="px-5 py-3 text-secondary">{{ formatMoney(campaign.budget_cents) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!myCampaigns.length" class="px-5 py-6 text-sm text-secondary">Noch keine eigenen Ads-Kampagnen.</p>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Meine Bestellungen</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="order in activeOrders" :key="order.id">
                            <td class="px-5 py-3 font-semibold text-primary">{{ order.orderable?.name || order.orderable?.title || order.type }}</td>
                            <td class="px-5 py-3 text-secondary">{{ order.status }}</td>
                            <td class="px-5 py-3 text-secondary">{{ formatMoney(order.amount_cents, order.currency) }}</td>
                            <td class="px-5 py-3 text-right">
                                <button v-if="order.status === 'completed'" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="reportIssue(order)">
                                    Problem melden
                                </button>
                                <span v-else-if="order.issue_status && order.issue_status !== 'none'" class="text-xs text-secondary">{{ order.issue_status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!activeOrders.length" class="px-5 py-6 text-sm text-secondary">Noch keine Bestellungen.</p>
            </div>
        </section>
    </div>
</template>
