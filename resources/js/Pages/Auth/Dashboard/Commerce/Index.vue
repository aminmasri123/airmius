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
    returnRequests: { type: Array, default: () => [] },
    cart: { type: Object, default: () => ({ items: [], summary: {} }) },
    pricingCountries: { type: Array, default: () => [] },
    checkoutAddress: { type: Object, default: () => ({}) },
})

const page = usePage()
const selectedClubId = ref(props.clubs[0]?.id || '')
const provider = ref('bank_transfer')
const interval = ref('monthly')
const acceptedTerms = ref(false)
const issueModal = ref({ open: false, order: null, note: '', mode: 'issue' })
const showCartCheckout = ref(false)
const productForm = useForm({
    club_id: '',
    title: '',
    description: '',
    image_url: '',
    category: 'product',
    sku: '',
    is_shippable: true,
    manages_stock: false,
    stock_quantity: '',
    tax_class: 'standard',
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
const cartCheckoutForm = useForm({
    provider: 'bank_transfer',
    accepted_terms: false,
    shipping_country: props.checkoutAddress.country || 'DE',
    shipping_state: props.checkoutAddress.state || '',
    shipping_postal_code: props.checkoutAddress.postal_code || '',
    shipping_city: props.checkoutAddress.city || '',
    shipping_street: props.checkoutAddress.street || '',
    shipping_house_number: props.checkoutAddress.house_number || '',
    customer_type: 'consumer',
    customer_company: '',
    customer_vat_id: '',
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

const addToCart = (product, quantity = 1) => {
    router.post(route('auth.commerce.cart.items.store', product.id), { quantity }, { preserveScroll: true })
}

const updateCartItem = (item, quantity) => {
    router.put(route('auth.commerce.cart.items.update', item.id), { quantity }, { preserveScroll: true })
}

const removeCartItem = (item) => {
    router.delete(route('auth.commerce.cart.items.destroy', item.id), { preserveScroll: true })
}

const checkoutCart = () => {
    cartCheckoutForm.post(route('auth.commerce.cart.checkout'), {
        preserveScroll: true,
        onSuccess: () => {
            showCartCheckout.value = false
        },
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

const openIssueModal = (order, mode = 'issue') => {
    issueModal.value = { open: true, order, note: '', mode }
}

const closeIssueModal = () => {
    issueModal.value = { open: false, order: null, note: '', mode: 'issue' }
}

const submitOrderRequest = () => {
    if (!issueModal.value.order || !issueModal.value.note.trim()) {
        return
    }

    const url = issueModal.value.mode === 'return'
        ? route('auth.commerce.orders.returns.store', issueModal.value.order.id)
        : route('auth.commerce.orders.issue', issueModal.value.order.id)

    router.post(url, {
        reason: issueModal.value.note,
        issue_note: issueModal.value.note,
    }, {
        preserveScroll: true,
        onSuccess: closeIssueModal,
    })
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

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Einkaufswagen</h2>
                        <p class="mt-1 text-sm text-secondary">Sammle mehrere Marketplace-Artikel und schließe sie gemeinsam ab.</p>
                    </div>
                    <button
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                        :disabled="!cart.items?.length"
                        @click="showCartCheckout = true"
                    >
                        Zur Kasse
                    </button>
                </div>
            </div>
            <div class="divide-y divide-border">
                <div v-for="item in cart.items" :key="item.id" class="grid gap-3 p-5 sm:grid-cols-[1fr_7rem_auto] sm:items-center">
                    <div>
                        <p class="font-semibold text-primary">{{ item.product?.title }}</p>
                        <p class="text-sm text-secondary">{{ formatMoney(item.line_total_cents, item.product?.currency || cart.summary?.currency || 'EUR') }}</p>
                    </div>
                    <input
                        :value="item.quantity"
                        type="number"
                        min="1"
                        max="99"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        @change="updateCartItem(item, Number($event.target.value || 1))"
                    >
                    <button class="rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error" @click="removeCartItem(item)">
                        Entfernen
                    </button>
                </div>
                <div v-if="cart.items?.length" class="grid gap-2 bg-bg p-5 text-sm text-secondary sm:grid-cols-4">
                    <p>Warenwert: <span class="font-semibold text-primary">{{ formatMoney(cart.summary?.item_gross_cents, cart.summary?.currency) }}</span></p>
                    <p>Versand: <span class="font-semibold text-primary">{{ formatMoney(cart.summary?.shipping_cents, cart.summary?.currency) }}</span></p>
                    <p>Steuer: <span class="font-semibold text-primary">{{ formatMoney(cart.summary?.tax_cents, cart.summary?.currency) }}</span></p>
                    <p>Gesamt: <span class="font-semibold text-primary">{{ formatMoney(cart.summary?.amount_cents, cart.summary?.currency) }}</span></p>
                </div>
                <p v-else class="p-5 text-sm text-secondary">Der Einkaufswagen ist leer.</p>
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
                            <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="addToCart(product)">
                                In den Warenkorb
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
                    <input v-model="productForm.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="SKU / Artikelnummer">
                    <select v-model="productForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="standard">Standardsteuer</option>
                        <option value="reduced">Ermaessigt</option>
                        <option value="zero">Nullsatz</option>
                    </select>
                    <input v-model="productForm.price_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in Cent">
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="productForm.is_shippable" type="checkbox" class="rounded border-border bg-inputBg">
                        Versandpflichtig
                    </label>
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="productForm.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
                        Lagerbestand verwalten
                    </label>
                    <input v-if="productForm.manages_stock" v-model="productForm.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerbestand">
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
                                <a v-if="order.invoice_number" :href="route('auth.commerce.orders.invoice', order.id)" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                                    Rechnung
                                </a>
                                <a v-if="order.credit_note_number" :href="route('auth.commerce.orders.credit-note', order.id)" class="ml-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                                    Gutschrift
                                </a>
                                <button v-if="order.status === 'completed'" class="ml-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="openIssueModal(order, 'issue')">
                                    Problem melden
                                </button>
                                <button v-if="order.status === 'completed'" class="ml-2 rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="openIssueModal(order, 'return')">
                                    Ruecksendung
                                </button>
                                <span v-else-if="order.issue_status && order.issue_status !== 'none'" class="text-xs text-secondary">{{ order.issue_status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!activeOrders.length" class="px-5 py-6 text-sm text-secondary">Noch keine Bestellungen.</p>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Meine Ruecksendungen</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="request in returnRequests" :key="request.id">
                            <td class="px-5 py-3 font-semibold text-primary">{{ request.item?.title || request.order?.orderable?.title || 'Ruecksendung' }}</td>
                            <td class="px-5 py-3 text-secondary">{{ request.status }}</td>
                            <td class="px-5 py-3 text-secondary">{{ request.reason }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!returnRequests.length" class="px-5 py-6 text-sm text-secondary">Noch keine Ruecksendungen.</p>
            </div>
        </section>

        <div v-if="issueModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-semibold text-primary">
                    {{ issueModal.mode === 'return' ? 'Ruecksendung anfragen' : 'Problem melden' }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    Beschreibe kurz, was geprueft werden soll.
                </p>
                <textarea v-model="issueModal.note" rows="5" class="mt-4 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Grund eingeben"></textarea>
                <div class="mt-5 flex justify-end gap-3">
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeIssueModal">Abbrechen</button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="submitOrderRequest">Senden</button>
                </div>
            </div>
        </div>

        <div v-if="showCartCheckout" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <form class="max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="checkoutCart">
                <h2 class="text-lg font-semibold text-primary">Einkaufswagen abschließen</h2>
                <p class="mt-2 text-sm text-secondary">
                    Gesamt: {{ formatMoney(cart.summary?.amount_cents, cart.summary?.currency) }}
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <select v-model="cartCheckoutForm.shipping_country" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="country in pricingCountries" :key="country.country" :value="country.country">{{ country.label }}</option>
                    </select>
                    <select v-model="cartCheckoutForm.provider" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="bank_transfer">Überweisung</option>
                        <option value="stripe">Stripe</option>
                        <option value="paypal">PayPal</option>
                    </select>
                    <select v-model="cartCheckoutForm.customer_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="consumer">Privatkunde</option>
                        <option value="business">Firma / Verein</option>
                    </select>
                    <input v-if="cartCheckoutForm.customer_type === 'business'" v-model="cartCheckoutForm.customer_vat_id" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="USt-IdNr.">
                    <input v-if="cartCheckoutForm.customer_type === 'business'" v-model="cartCheckoutForm.customer_company" class="rounded-lg border-border bg-inputBg text-sm text-primary sm:col-span-2" placeholder="Firma / Verein">
                    <input v-model="cartCheckoutForm.shipping_street" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Straße">
                    <input v-model="cartCheckoutForm.shipping_house_number" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Nr.">
                    <input v-model="cartCheckoutForm.shipping_postal_code" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ">
                    <input v-model="cartCheckoutForm.shipping_city" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ort">
                </div>

                <label class="mt-4 flex items-start gap-3 text-sm text-secondary">
                    <input v-model="cartCheckoutForm.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>Ich akzeptiere AGB und Widerrufshinweise.</span>
                </label>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showCartCheckout = false">Abbrechen</button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Kaufen</button>
                </div>
            </form>
        </div>
    </div>
</template>
