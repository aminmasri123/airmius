<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    coupons: { type: Array, default: () => [] },
    addons: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    adReport: { type: Object, default: () => ({}) },
    orders: { type: Array, default: () => [] },
    websiteRequests: { type: Array, default: () => [] },
    payoutProfiles: { type: Array, default: () => [] },
    payoutCandidates: { type: Array, default: () => [] },
    payouts: { type: Array, default: () => [] },
    marketplaceVisuals: { type: Array, default: () => [] },
    taxRates: { type: Array, default: () => [] },
    shippingRates: { type: Array, default: () => [] },
    commerceSettings: { type: Object, default: () => ({}) },
    returnRequests: { type: Array, default: () => [] },
})

const page = usePage()

const couponForm = useForm({
    code: '',
    name: '',
    type: 'percent',
    percent_off: 50,
    value_cents: '',
    max_redemptions: '',
    starts_at: '',
    ends_at: '',
    is_active: true,
})

const addonForm = useForm({
    slug: '',
    name: '',
    description: '',
    monthly_price_cents: 0,
    yearly_price_cents: 0,
    target_actor: 'verein',
    features: [],
    is_active: true,
})

const productForm = useForm({
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
    currency: 'EUR',
    status: 'draft',
    commission_percent: 10,
})

const marketplaceVisualForm = useForm({
    sources: Object.fromEntries(props.marketplaceVisuals.map((visual) => [visual.key, visual.source || ''])),
    uploads: {},
})

const taxRateForm = useForm({
    name: 'Deutschland Standard',
    country_code: 'DE',
    region: '',
    tax_class: 'standard',
    tax_label: 'MwSt.',
    rate_percent: 19,
    currency: 'EUR',
    is_default: true,
    is_active: true,
    priority: 10,
})

const commerceSettingsForm = useForm({
    company_country: props.commerceSettings.company_country || 'DE',
    company_currency: props.commerceSettings.company_currency || 'EUR',
    enable_oss: props.commerceSettings.enable_oss ?? true,
    export_vat_mode: props.commerceSettings.export_vat_mode || 'zero',
    reverse_charge_enabled: props.commerceSettings.reverse_charge_enabled ?? true,
})

const shippingRateForm = useForm({
    name: 'Deutschland Standardversand',
    country_code: 'DE',
    postal_code_prefix: '',
    amount_cents: 490,
    currency: 'EUR',
    free_from_cents: 10000,
    is_active: true,
    priority: 10,
})

const campaignForm = useForm({
    name: '',
    description: '',
    target_url: '',
    budget_cents: 0,
    spent_cents: 0,
    impressions: 0,
    clicks: 0,
    status: 'draft',
    starts_at: '',
    ends_at: '',
})

const formatMoney = (cents) => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
}).format(Number(cents || 0) / 100)

const formatPercent = (value) => `${Number(value || 0).toFixed(2).replace('.', ',')} %`

const ctr = (clicks, impressions) => {
    if (!Number(impressions || 0)) {
        return 0
    }

    return (Number(clicks || 0) / Number(impressions || 0)) * 100
}

const budgetUsage = (spent, budget) => {
    if (!Number(budget || 0)) {
        return 0
    }

    return Math.min(100, (Number(spent || 0) / Number(budget || 0)) * 100)
}

const storeCoupon = () => couponForm.post(route('admin.commerce.coupons.store'), {
    preserveScroll: true,
    onSuccess: () => couponForm.reset('code', 'name', 'max_redemptions', 'starts_at', 'ends_at'),
})

const storeAddon = () => addonForm.post(route('admin.commerce.addons.store'), {
    preserveScroll: true,
    onSuccess: () => addonForm.reset('slug', 'name', 'description'),
})

const storeProduct = () => productForm.post(route('admin.commerce.products.store'), {
    preserveScroll: true,
    onSuccess: () => productForm.reset('title', 'description', 'image_url'),
})

const setMarketplaceVisualUpload = (key, event) => {
    marketplaceVisualForm.uploads[key] = event.target.files?.[0] || null
}

const updateMarketplaceVisuals = () => marketplaceVisualForm.post(route('admin.commerce.marketplace-visuals.update'), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
        marketplaceVisualForm.uploads = {}
    },
})

const storeTaxRate = () => taxRateForm.post(route('admin.commerce.tax-rates.store'), {
    preserveScroll: true,
    onSuccess: () => taxRateForm.reset('region'),
})

const updateCommerceSettings = () => commerceSettingsForm.put(route('admin.commerce.settings.update'), {
    preserveScroll: true,
})

const updateTaxRate = (rate) => {
    router.put(route('admin.commerce.tax-rates.update', rate.id), {
        name: rate.name,
        country_code: rate.country_code,
        region: rate.region || '',
        tax_class: rate.tax_class || 'standard',
        tax_label: rate.tax_label || 'MwSt.',
        rate_percent: rate.rate_percent,
        currency: rate.currency || 'EUR',
        is_default: Boolean(rate.is_default),
        is_active: Boolean(rate.is_active),
        priority: rate.priority || 100,
    }, { preserveScroll: true })
}

const storeShippingRate = () => shippingRateForm.post(route('admin.commerce.shipping-rates.store'), {
    preserveScroll: true,
    onSuccess: () => shippingRateForm.reset('postal_code_prefix'),
})

const updateShippingRate = (rate) => {
    router.put(route('admin.commerce.shipping-rates.update', rate.id), {
        name: rate.name,
        country_code: rate.country_code || '',
        postal_code_prefix: rate.postal_code_prefix || '',
        amount_cents: rate.amount_cents,
        currency: rate.currency || 'EUR',
        free_from_cents: rate.free_from_cents,
        is_active: Boolean(rate.is_active),
        priority: rate.priority || 100,
    }, { preserveScroll: true })
}

const updateProductStatus = (product, status) => {
    const rejectionReason = status === 'rejected'
        ? window.prompt('Warum wird das Angebot abgelehnt?')
        : product.rejection_reason

    if (status === 'rejected' && !rejectionReason) {
        return
    }

    router.put(route('admin.commerce.products.update', product.id), {
        title: product.title,
        description: product.description,
        image_url: product.image_url,
        category: product.category,
        price_cents: product.price_cents,
        currency: product.currency,
        status,
        rejection_reason: rejectionReason || null,
        commission_percent: product.commission_percent,
    }, { preserveScroll: true })
}

const adjustProductStock = (product, quantityDelta) => {
    router.post(route('admin.commerce.products.stock.adjust', product.id), {
        quantity_delta: quantityDelta,
        note: 'Admin-Anpassung',
    }, { preserveScroll: true })
}

const updateReturnRequest = (request, status, restock = false) => {
    router.put(route('admin.commerce.returns.update', request.id), {
        status,
        resolution_note: request.resolution_note || '',
        approved_amount_cents: request.approved_amount_cents || request.requested_amount_cents,
        restock,
    }, { preserveScroll: true })
}

const storeCampaign = () => campaignForm.post(route('admin.commerce.campaigns.store'), {
    preserveScroll: true,
    onSuccess: () => campaignForm.reset('name', 'description', 'target_url', 'starts_at', 'ends_at'),
})

const markOrderPaid = (order) => {
    router.post(route('admin.commerce.orders.mark-paid', order.id), {}, { preserveScroll: true })
}

const updateOrderIssue = (order, issueStatus, orderStatus = null) => {
    router.put(route('admin.commerce.orders.issue', order.id), {
        issue_status: issueStatus,
        issue_note: order.issue_note || '',
        order_status: orderStatus,
    }, { preserveScroll: true })
}

const updateWebsiteRequest = (request, status) => {
    router.put(route('admin.commerce.website-requests.update', request.id), {
        status,
        notes: request.notes || '',
    }, { preserveScroll: true })
}

const createPayout = (candidate, method = 'bank_transfer') => {
    router.post(route('admin.commerce.payouts.create', candidate.user_id), {
        method,
        notes: '',
    }, { preserveScroll: true })
}

const markPayoutPaid = (payout) => {
    router.put(route('admin.commerce.payouts.paid', payout.id), {
        notes: payout.notes || '',
    }, { preserveScroll: true })
}

const updatePayoutProfile = (profile, status) => {
    router.put(route('admin.commerce.payout-profiles.update', profile.id), {
        status,
        notes: profile.notes || '',
    }, { preserveScroll: true })
}
</script>

<template>
    <Head title="Commerce" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Business</p>
            <h1 class="mt-1 text-2xl font-bold text-primary">Commerce-Zentrale</h1>
            <p class="mt-2 max-w-3xl text-sm text-secondary">
                Coupons, Add-ons, Marketplace, Ads und Umsatzkennzahlen für die Beta zentral vorbereiten.
            </p>
            <div v-if="page.props.flash?.success" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                {{ page.props.flash.success }}
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            <div v-for="item in [
                ['Umsatz', formatMoney(summary.revenue_cents)],
                ['Offen', formatMoney(summary.open_cents)],
                ['Coupons', summary.coupons || 0],
                ['Add-ons', summary.addons || 0],
                ['Produkte', summary.products || 0],
                ['Kampagnen', summary.campaigns || 0],
                ['Orders', summary.orders || 0],
                ['Provision', formatMoney(summary.commission_cents)],
                ['Auszahlung', formatMoney(summary.payout_cents)],
                ['Websites', summary.website_requests || 0],
            ]" :key="item[0]" class="rounded-lg border border-border bg-card p-4">
                <p class="text-xs uppercase text-secondary">{{ item[0] }}</p>
                <p class="mt-2 text-xl font-bold text-primary">{{ item[1] }}</p>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="surface-card p-5 xl:col-span-2">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">EU-Konformitaet</p>
                    <h2 class="text-lg font-semibold text-primary">Commerce-Steuerlogik</h2>
                    <p class="text-sm text-secondary">Diese Einstellungen steuern Firmenland, OSS-Verhalten, Export und Reverse-Charge.</p>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-5" @submit.prevent="updateCommerceSettings">
                    <input v-model="commerceSettingsForm.company_country" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Firmensitz, z. B. DE">
                    <input v-model="commerceSettingsForm.company_currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                    <select v-model="commerceSettingsForm.export_vat_mode" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="zero">Export ausserhalb EU: 0%</option>
                        <option value="domestic">Export: Inlandssatz</option>
                    </select>
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary">
                        <input v-model="commerceSettingsForm.enable_oss" type="checkbox" class="rounded border-border bg-inputBg">
                        OSS aktiv
                    </label>
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary">
                        <input v-model="commerceSettingsForm.reverse_charge_enabled" type="checkbox" class="rounded border-border bg-inputBg">
                        Reverse-Charge
                    </label>
                    <button class="md:col-span-5 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Steuerlogik speichern</button>
                </form>
            </article>

            <article class="surface-card p-5">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Checkout</p>
                    <h2 class="text-lg font-semibold text-primary">Steuern verwalten</h2>
                    <p class="text-sm text-secondary">Der Checkout waehlt den passenden Satz ueber Lieferland und optional Region.</p>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeTaxRate">
                    <input v-model="taxRateForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <input v-model="taxRateForm.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="DE">
                    <input v-model="taxRateForm.region" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Region optional">
                    <select v-model="taxRateForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="standard">Standard</option>
                        <option value="reduced">Ermaessigt</option>
                        <option value="zero">Nullsatz</option>
                    </select>
                    <input v-model="taxRateForm.tax_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="MwSt.">
                    <input v-model="taxRateForm.rate_percent" type="number" min="0" max="99.99" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="19">
                    <input v-model="taxRateForm.currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                    <input v-model="taxRateForm.priority" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Prioritaet">
                    <div class="flex flex-wrap items-center gap-4 text-sm text-primary">
                        <label class="flex items-center gap-2">
                            <input v-model="taxRateForm.is_default" type="checkbox" class="rounded border-border bg-inputBg">
                            Standard
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="taxRateForm.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                            Aktiv
                        </label>
                    </div>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Steuersatz speichern</button>
                </form>

                <div class="mt-5 space-y-3">
                    <div v-for="rate in taxRates" :key="rate.id" class="grid gap-2 rounded-lg border border-border bg-card p-3 md:grid-cols-[1fr_5rem_7rem_5rem_6rem_6rem_auto] md:items-center">
                        <input v-model="rate.name" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="rate.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary">
                        <select v-model="rate.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="standard">Standard</option>
                            <option value="reduced">Ermaessigt</option>
                            <option value="zero">Nullsatz</option>
                        </select>
                        <input v-model="rate.tax_label" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="rate.rate_percent" type="number" min="0" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <label class="flex items-center gap-2 text-sm text-primary">
                            <input v-model="rate.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                            Aktiv
                        </label>
                        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateTaxRate(rate)">Speichern</button>
                    </div>
                    <p v-if="!taxRates.length" class="text-sm text-secondary">Noch keine Steuersaetze angelegt.</p>
                </div>
            </article>

            <article class="surface-card p-5">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Checkout</p>
                    <h2 class="text-lg font-semibold text-primary">Versandkosten verwalten</h2>
                    <p class="text-sm text-secondary">Regeln koennen nach Lieferland und PLZ-Prefix greifen, inklusive kostenfrei ab Warenwert.</p>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeShippingRate">
                    <input v-model="shippingRateForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <input v-model="shippingRateForm.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="DE oder leer">
                    <input v-model="shippingRateForm.postal_code_prefix" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ-Prefix optional">
                    <input v-model="shippingRateForm.amount_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Cent">
                    <input v-model="shippingRateForm.free_from_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kostenfrei ab Cent">
                    <input v-model="shippingRateForm.currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                    <input v-model="shippingRateForm.priority" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Prioritaet">
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="shippingRateForm.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                        Aktiv
                    </label>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Versandregel speichern</button>
                </form>

                <div class="mt-5 space-y-3">
                    <div v-for="rate in shippingRates" :key="rate.id" class="grid gap-2 rounded-lg border border-border bg-card p-3 md:grid-cols-[1fr_5rem_6rem_6rem_6rem_auto] md:items-center">
                        <input v-model="rate.name" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="rate.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Alle">
                        <input v-model="rate.postal_code_prefix" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ">
                        <input v-model="rate.amount_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <label class="flex items-center gap-2 text-sm text-primary">
                            <input v-model="rate.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                            Aktiv
                        </label>
                        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateShippingRate(rate)">Speichern</button>
                    </div>
                    <p v-if="!shippingRates.length" class="text-sm text-secondary">Noch keine Versandregeln angelegt.</p>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Rabattcode erstellen</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeCoupon">
                    <input v-model="couponForm.code" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Code">
                    <input v-model="couponForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <select v-model="couponForm.type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="percent">Prozent</option>
                        <option value="fixed">Festbetrag</option>
                    </select>
                    <input v-if="couponForm.type === 'percent'" v-model="couponForm.percent_off" type="number" min="1" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Prozent">
                    <input v-else v-model="couponForm.value_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Cent">
                    <input v-model="couponForm.max_redemptions" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Max. Nutzungen">
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="couponForm.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                        Aktiv
                    </label>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                </form>

                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <tbody class="divide-y divide-border">
                            <tr v-for="coupon in coupons" :key="coupon.id">
                                <td class="py-3 font-semibold text-primary">{{ coupon.code }}</td>
                                <td class="py-3 text-secondary">{{ coupon.type === 'percent' ? `${coupon.percent_off}%` : formatMoney(coupon.value_cents) }}</td>
                                <td class="py-3 text-secondary">{{ coupon.redeemed_count }} genutzt</td>
                                <td class="py-3 text-right" :class="coupon.is_active ? 'text-success' : 'text-secondary'">{{ coupon.is_active ? 'Aktiv' : 'Inaktiv' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Add-on erstellen</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeAddon">
                    <input v-model="addonForm.slug" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="slug">
                    <input v-model="addonForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <input v-model="addonForm.monthly_price_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Monat Cent">
                    <input v-model="addonForm.yearly_price_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Jahr Cent">
                    <textarea v-model="addonForm.description" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                </form>
                <div class="mt-5 grid gap-3">
                    <div v-for="addon in addons" :key="addon.id" class="rounded-lg border border-border p-3">
                        <p class="font-semibold text-primary">{{ addon.name }}</p>
                        <p class="text-sm text-secondary">{{ formatMoney(addon.monthly_price_cents) }} / Monat · {{ addon.purchases_count }} Käufe</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Marketplace-Produkt</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeProduct">
                    <input v-model="productForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                    <input v-model="productForm.price_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis Cent">
                    <input v-model="productForm.image_url" type="url" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Produktbild URL, empfohlen 1200 x 1200 px">
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
                    <select v-model="productForm.status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="draft">Entwurf</option>
                        <option value="review">Prüfung</option>
                        <option value="published">Öffentlich</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="productForm.is_shippable" type="checkbox" class="rounded border-border bg-inputBg">
                        Versandpflichtig
                    </label>
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="productForm.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
                        Lagerbestand verwalten
                    </label>
                    <input v-if="productForm.manages_stock" v-model="productForm.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerbestand">
                    <textarea v-model="productForm.description" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                </form>
                <div class="mt-5 space-y-3">
                    <div v-for="product in products" :key="product.id" class="rounded-lg border border-border p-3">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-inputBg">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover" />
                                <div v-else class="flex h-full items-center justify-center">
                                    <i class="las la-store text-2xl text-air-blue"></i>
                                </div>
                            </div>
                            <div>
                                <p class="font-semibold text-primary">{{ product.title }}</p>
                                <p class="text-xs text-secondary">{{ product.status }} · {{ product.moderation_status }} · {{ formatMoney(product.price_cents) }}</p>
                                <p class="text-xs text-secondary">
                                    SKU {{ product.sku || '-' }} · Steuer {{ product.tax_class || 'standard' }} ·
                                    <span v-if="product.manages_stock">Bestand {{ product.stock_quantity ?? 0 }}</span>
                                    <span v-else>Bestand nicht verwaltet</span>
                                </p>
                                <p v-if="product.rejection_reason" class="mt-1 text-xs text-warning">{{ product.rejection_reason }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-if="product.manages_stock" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="adjustProductStock(product, 1)">+ Bestand</button>
                                <button v-if="product.manages_stock" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="adjustProductStock(product, -1)">- Bestand</button>
                                <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateProductStatus(product, 'published')">Freigeben</button>
                                <button class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="updateProductStatus(product, 'rejected')">Ablehnen</button>
                                <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateProductStatus(product, 'archived')">Archivieren</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!products.length" class="text-sm text-secondary">Noch keine Produkte vorbereitet.</p>
                </div>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Ads-Kampagne</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeCampaign">
                    <input v-model="campaignForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <input v-model="campaignForm.budget_cents" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Budget Cent">
                    <input v-model="campaignForm.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL">
                    <select v-model="campaignForm.status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="draft">Entwurf</option>
                        <option value="active">Aktiv</option>
                        <option value="paused">Pausiert</option>
                        <option value="completed">Abgeschlossen</option>
                    </select>
                    <input v-model="campaignForm.clicks" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Klicks">
                    <textarea v-model="campaignForm.description" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                </form>
                <p class="mt-4 text-sm text-secondary">{{ campaigns.length }} Kampagnen vorbereitet.</p>
            </article>
        </section>

        <section class="surface-card p-5">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Marketplace</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Statische Bilder verwalten</h2>
                    <p class="mt-1 max-w-3xl text-sm text-secondary">
                        Diese Bilder steuern die festen Marketplace-Flächen wie Seitenbanner, Hero-Banner und Sale-Kachel. Du kannst eine URL eintragen oder direkt ein Bild hochladen.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                    :disabled="marketplaceVisualForm.processing"
                    @click="updateMarketplaceVisuals"
                >
                    Bilder speichern
                </button>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                <article
                    v-for="visual in marketplaceVisuals"
                    :key="visual.key"
                    class="rounded-lg border border-border bg-card p-4"
                >
                    <div class="overflow-hidden rounded-lg border border-border bg-inputBg">
                        <img
                            v-if="visual.url"
                            :src="visual.url"
                            :alt="visual.label"
                            class="aspect-video w-full object-cover"
                        />
                        <div v-else class="flex aspect-video items-center justify-center text-secondary">
                            <i class="las la-image text-4xl"></i>
                        </div>
                    </div>

                    <h3 class="mt-3 font-semibold text-primary">{{ visual.label }}</h3>
                    <p class="mt-1 text-xs leading-5 text-secondary">{{ visual.description }}</p>
                    <p class="mt-2 text-xs font-semibold text-primary">Empfohlen: {{ visual.recommended_size }}</p>

                    <label class="mt-4 block text-xs font-semibold uppercase text-secondary">Bild-URL oder gespeicherter Pfad</label>
                    <input
                        v-model="marketplaceVisualForm.sources[visual.key]"
                        class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="https://... oder marketplace/visuals/..."
                    />

                    <label class="mt-3 block text-xs font-semibold uppercase text-secondary">Bild hochladen</label>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        @change="setMarketplaceVisualUpload(visual.key, $event)"
                    />
                </article>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Ads Reporting</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Kampagnenleistung</h2>
                <p class="mt-1 text-sm text-secondary">Impressionen, Klicks, CTR und Budgetverbrauch für aktive Sponsor- und Ads-Kampagnen.</p>
            </div>

            <div class="grid gap-4 border-b border-border p-5 md:grid-cols-5">
                <div class="rounded-lg border border-border bg-bg p-4">
                    <p class="text-xs uppercase text-secondary">Aktiv</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ adReport.active || 0 }}</p>
                </div>
                <div class="rounded-lg border border-border bg-bg p-4">
                    <p class="text-xs uppercase text-secondary">Impressionen</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ adReport.impressions || 0 }}</p>
                </div>
                <div class="rounded-lg border border-border bg-bg p-4">
                    <p class="text-xs uppercase text-secondary">Klicks</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ adReport.clicks || 0 }}</p>
                </div>
                <div class="rounded-lg border border-border bg-bg p-4">
                    <p class="text-xs uppercase text-secondary">CTR</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatPercent(ctr(adReport.clicks, adReport.impressions)) }}</p>
                </div>
                <div class="rounded-lg border border-border bg-bg p-4">
                    <p class="text-xs uppercase text-secondary">Budget</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(adReport.budget_cents) }}</p>
                </div>
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
                        <tr v-for="campaign in campaigns" :key="campaign.id">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ campaign.name }}</p>
                                <p class="text-xs text-secondary">{{ campaign.target_url || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ campaign.status }}</td>
                            <td class="px-5 py-3 text-secondary">{{ campaign.impressions || 0 }}</td>
                            <td class="px-5 py-3 text-secondary">{{ campaign.clicks || 0 }}</td>
                            <td class="px-5 py-3 text-secondary">{{ formatPercent(ctr(campaign.clicks, campaign.impressions)) }}</td>
                            <td class="px-5 py-3">
                                <p class="text-secondary">{{ formatMoney(campaign.spent_cents) }} / {{ formatMoney(campaign.budget_cents) }}</p>
                                <div class="mt-2 h-2 rounded-full bg-muted">
                                    <div class="h-2 rounded-full bg-air-blue" :style="{ width: `${budgetUsage(campaign.spent_cents, campaign.budget_cents)}%` }"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!campaigns.length" class="px-5 py-6 text-sm text-secondary">Noch keine Ads-Kampagnen.</p>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Marketplace</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Auszahlungen</h2>
                <p class="mt-1 text-sm text-secondary">Offene Marketplace-Erlöse sammeln, Provision abziehen und Auszahlung vorbereiten.</p>
            </div>

            <div class="grid gap-6 p-5 xl:grid-cols-2">
                <div>
                    <h3 class="font-semibold text-primary">Offene Auszahlungsbeträge</h3>
                    <div class="mt-3 overflow-x-auto rounded-lg border border-border">
                        <table class="min-w-full text-left text-sm">
                            <tbody class="divide-y divide-border">
                                <tr v-for="candidate in payoutCandidates" :key="candidate.user_id">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-primary">{{ candidate.name || candidate.email }}</p>
                                        <p class="text-xs text-secondary">{{ candidate.orders_count }} Bestellungen</p>
                                    </td>
                                    <td class="px-4 py-3 text-secondary">
                                        <p>Brutto {{ formatMoney(candidate.gross_cents) }}</p>
                                        <p>Provision {{ formatMoney(candidate.commission_cents) }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-primary">{{ formatMoney(candidate.amount_cents) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="createPayout(candidate)">
                                            Vorbereiten
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="!payoutCandidates.length" class="px-4 py-6 text-sm text-secondary">Keine offenen Auszahlungen.</p>
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold text-primary">Auszahlungsdaten prüfen</h3>
                    <div class="mt-3 overflow-x-auto rounded-lg border border-border">
                        <table class="min-w-full text-left text-sm">
                            <tbody class="divide-y divide-border">
                                <tr v-for="profile in payoutProfiles" :key="profile.id">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-primary">{{ profile.user?.name || profile.user?.email }}</p>
                                        <p class="text-xs text-secondary">{{ profile.paypal_email || profile.iban || 'Keine Zahlungsdaten' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-secondary">{{ profile.status }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updatePayoutProfile(profile, 'approved')">Freigeben</button>
                                            <button class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger" @click="updatePayoutProfile(profile, 'blocked')">Sperren</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="!payoutProfiles.length" class="px-4 py-6 text-sm text-secondary">Noch keine Auszahlungsprofile.</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-border p-5">
                <h3 class="font-semibold text-primary">Auszahlungshistorie</h3>
                <div class="mt-3 overflow-x-auto rounded-lg border border-border">
                    <table class="min-w-full text-left text-sm">
                        <tbody class="divide-y divide-border">
                            <tr v-for="payout in payouts" :key="payout.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-primary">{{ payout.reference || `Auszahlung #${payout.id}` }}</p>
                                    <p class="text-xs text-secondary">{{ payout.user?.email || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-secondary">
                                    <p>Brutto {{ formatMoney(payout.gross_cents) }}</p>
                                    <p>Provision {{ formatMoney(payout.commission_cents) }}</p>
                                </td>
                                <td class="px-4 py-3 font-semibold text-primary">{{ formatMoney(payout.amount_cents) }}</td>
                                <td class="px-4 py-3 text-secondary">{{ payout.status }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button v-if="payout.status !== 'paid'" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="markPayoutPaid(payout)">
                                        Ausgezahlt
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!payouts.length" class="px-4 py-6 text-sm text-secondary">Noch keine Auszahlungen vorbereitet.</p>
                </div>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">After Sales</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Ruecksendungen und Erstattungen</h2>
                <p class="mt-1 text-sm text-secondary">Anfragen pruefen, Ware als erhalten markieren, Bestand wieder einbuchen und Erstattung dokumentieren.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="request in returnRequests" :key="request.id">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ request.item?.title || request.order?.orderable?.title || `Ruecksendung #${request.id}` }}</p>
                                <p class="text-xs text-secondary">{{ request.order?.user?.email || request.guest_email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ request.status }}</td>
                            <td class="px-5 py-3 text-secondary">{{ request.reason }}</td>
                            <td class="px-5 py-3 text-secondary">{{ formatMoney(request.requested_amount_cents) }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateReturnRequest(request, 'approved')">Freigeben</button>
                                    <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateReturnRequest(request, 'received', true)">Erhalten + Bestand</button>
                                    <button class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="updateReturnRequest(request, 'refunded')">Erstattet</button>
                                    <button class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="updateReturnRequest(request, 'rejected')">Ablehnen</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!returnRequests.length" class="px-5 py-6 text-sm text-secondary">Noch keine Ruecksendungen.</p>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Commerce-Bestellungen</h2>
                <p class="mt-1 text-sm text-secondary">Offene Überweisungen für Add-ons und Marketplace manuell bestätigen.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="order in orders" :key="order.id">
                            <td class="px-5 py-3 font-semibold text-primary">{{ order.orderable?.name || order.orderable?.title || order.type }}</td>
                            <td class="px-5 py-3 text-secondary">{{ order.user?.email || '-' }}</td>
                            <td class="px-5 py-3 text-secondary">{{ formatMoney(order.amount_cents) }}</td>
                            <td class="px-5 py-3 text-secondary">
                                <p>{{ order.status }}</p>
                                <p v-if="order.issue_status && order.issue_status !== 'none'" class="text-xs text-warning">{{ order.issue_status }}</p>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button v-if="order.status === 'awaiting_transfer'" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="markOrderPaid(order)">
                                        Bezahlt
                                    </button>
                                    <button v-if="order.issue_status === 'reported'" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateOrderIssue(order, 'reviewing')">Prüfen</button>
                                    <button v-if="order.issue_status && order.issue_status !== 'none'" class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="updateOrderIssue(order, 'resolved')">Gelöst</button>
                                    <button v-if="order.issue_status && order.issue_status !== 'none'" class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="updateOrderIssue(order, 'refunded', 'refunded')">Erstattet</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!orders.length" class="px-5 py-6 text-sm text-secondary">Noch keine Commerce-Bestellungen.</p>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Website-Anfragen</h2>
                <p class="mt-1 text-sm text-secondary">Vereine, die eine Website von Airmius erstellen lassen möchten.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="request in websiteRequests" :key="request.id">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ request.club?.name || 'Ohne Verein' }}</p>
                                <p class="text-xs text-secondary">{{ request.user?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ request.domain || '-' }}</td>
                            <td class="px-5 py-3 text-secondary">{{ request.status }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateWebsiteRequest(request, 'contacted')">Kontaktiert</button>
                                    <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="updateWebsiteRequest(request, 'quoted')">Angebot</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!websiteRequests.length" class="px-5 py-6 text-sm text-secondary">Noch keine Website-Anfragen.</p>
            </div>
        </section>
    </div>
</template>
