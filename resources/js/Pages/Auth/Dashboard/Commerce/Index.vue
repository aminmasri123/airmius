<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { majorToCents, moneyInputAttrs, transformMoneyFields } from '@/utils/currency'

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
const adProvider = ref('bank_transfer')
const interval = ref('monthly')
const acceptedTerms = ref(false)
const adAcceptedTerms = ref(false)
const issueModal = ref({ open: false, order: null, note: '', mode: 'issue' })
const showCartCheckout = ref(false)
const activeTab = ref('marketplace')
const campaignActionError = ref('')
const deleteCampaignModal = ref({ open: false, campaign: null, confirmation: '' })
const productForm = useForm({
    club_id: '',
    title: '',
    description: '',
    attributes_text: '',
    attribute_options: [],
    variants: [],
    image_url: '',
    image_urls_text: '',
    image_upload: null,
    image_uploads: [],
    category: 'product',
    product_type: 'single',
    sku: '',
    is_shippable: true,
    manages_stock: false,
    stock_quantity: '',
    tax_class: 'standard',
    digital_delivery_note: '',
    price_cents: '',
})
const productAttributeRows = ref([{ name: '', values: [] }])
const productVariantRows = ref([])
const campaignForm = useForm({
    club_id: '',
    name: '',
    headline: '',
    description: '',
    primary_text: '',
    target_url: '',
    cta_label: 'Mehr erfahren',
    objective: 'traffic',
    placement: 'marketplace_card',
    creative_format: 'feed_square',
    creative_image_url: '',
    creative_image_upload: null,
    creatives: [],
    audience_locations: '',
    audience_interests: '',
    audience_age_min: '',
    audience_age_max: '',
    budget_cents: '',
    daily_budget_cents: '',
    starts_at: '',
    ends_at: '',
    provider: 'bank_transfer',
    accepted_terms: false,
    client_reference: '',
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

const cartItems = computed(() => props.cart?.items || [])
const cartItemCount = computed(() => cartItems.value.length)

const attributePresets = [
    { name: 'Farbe', values: ['Schwarz', 'Weiß', 'Rot', 'Blau', 'Grün', 'Gelb', 'Orange', 'Grau'] },
    { name: 'Größe', values: ['XS', 'S', 'M', 'L', 'XL', 'XXL', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46'] },
    { name: 'Material', values: ['Baumwolle', 'Polyester', 'Leder', 'Mesh', 'Kunststoff', 'Metall'] },
    { name: 'Dauer', values: ['30 Minuten', '60 Minuten', '90 Minuten', '1 Tag', '2 Tage', 'Wochenende'] },
    { name: 'Lieferart', values: ['E-Mail', 'Download', 'Online-Zugang', 'Vor Ort', 'Versand'] },
]

const adFormats = [
    { key: 'feed_square', label: 'Feed Quadrat', size: '1080 x 1080 px', ratio: '1:1', hint: 'Ideal für Marketplace-Karten und Feed.' },
    { key: 'feed_portrait', label: 'Feed Portrait', size: '1080 x 1350 px', ratio: '4:5', hint: 'Mehr Flaeche im mobilen Feed.' },
    { key: 'story_vertical', label: 'Story/Reel', size: '1080 x 1920 px', ratio: '9:16', hint: 'Vollbildformat für mobile Kampagnen.' },
    { key: 'banner_wide', label: 'Wide Banner', size: '1200 x 628 px', ratio: '1.91:1', hint: 'Gut für breite Sponsor- und Websitebereiche.' },
]
const selectedAdFormat = computed(() => adFormats.find((format) => format.key === campaignForm.creative_format) || adFormats[0])
const campaignCreativeRows = ref([
    { name: 'Variante A', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
    { name: 'Variante B', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
])

const newClientReference = () => {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID()
    }

    return `ads-${Date.now()}-${Math.random().toString(36).slice(2)}`
}

const presetValuesFor = (name) => attributePresets.find((preset) => preset.name === name)?.values || []

const normalizeAttributeRows = (rows) => rows
    .map((row) => ({
        name: String(row.name || '').trim(),
        values: Array.isArray(row.values)
            ? row.values.map((value) => String(value || '').trim()).filter(Boolean)
            : String(row.values || '').split(/[|,]/).map((value) => value.trim()).filter(Boolean),
    }))
    .filter((row) => row.name && row.values.length)

const normalizeVariantRows = (rows) => rows
    .map((row) => ({
        sku: String(row.sku || '').trim(),
        price_cents: row.price_cents === '' || row.price_cents === null ? null : majorToCents(row.price_cents),
        stock_quantity: row.stock_quantity === '' || row.stock_quantity === null ? null : Number(row.stock_quantity),
        image_url: String(row.image_url || '').trim(),
        attributes: Object.entries(row.attributes || {})
            .map(([name, value]) => ({ name, value: String(value || '').trim() }))
            .filter((attribute) => attribute.name && attribute.value),
    }))
    .filter((row) => row.attributes.length)

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
    const stock = Number(item.product?.stock_quantity || 1)
    const nextQuantity = Math.min(Math.max(1, Number(quantity || 1)), stock)

    router.put(route('auth.commerce.cart.items.update', item.id), { quantity: nextQuantity }, { preserveScroll: true })
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

const activeOrders = computed(() => props.orders.filter((order) => ['pending', 'awaiting_transfer', 'completed'].includes(order.status)))
const commerceTabs = computed(() => [
    { key: 'marketplace', label: 'Marketplace', icon: 'las la-store', count: props.products.length + props.addons.length + props.outfitPlans.length },
    { key: 'cart', label: 'Warenkorb', icon: 'las la-shopping-cart', count: cartItemCount.value },
    { key: 'create', label: 'Erstellen', icon: 'las la-plus-circle', count: props.myProducts.length + props.websiteRequests.length },
    { key: 'ads', label: 'Ads', icon: 'las la-bullhorn', count: props.myCampaigns.length },
    { key: 'payouts', label: 'Auszahlung', icon: 'las la-wallet', count: props.payoutSummary.pending_orders || 0 },
    { key: 'orders', label: 'Bestellungen', icon: 'las la-receipt', count: activeOrders.value.length + props.returnRequests.length },
])

const productAttributesText = () => productAttributeRows.value
    .map((row) => ({
        name: String(row.name || '').trim(),
        values: Array.isArray(row.values) ? row.values.join(' | ') : String(row.values || '').trim(),
    }))
    .filter((row) => row.name && row.values)
    .map((row) => `${row.name}: ${row.values}`)
    .join('\n')

const addProductAttributeRow = () => {
    productAttributeRows.value.push({ name: '', values: [] })
}

const removeProductAttributeRow = (index) => {
    productAttributeRows.value.splice(index, 1)
    if (!productAttributeRows.value.length) {
        addProductAttributeRow()
    }
}

const addProductVariantRow = () => {
    productVariantRows.value.push({ sku: '', price_cents: productForm.price_cents || '', stock_quantity: '', image_url: '', attributes: {} })
}

const removeProductVariantRow = (index) => {
    productVariantRows.value.splice(index, 1)
}

const setProductImageUpload = (event) => {
    productForm.image_upload = event.target.files?.[0] || null
}

const setProductGalleryUploads = (event) => {
    productForm.image_uploads = Array.from(event.target.files || [])
}

const setCampaignCreativeUpload = (event) => {
    campaignForm.creative_image_upload = event.target.files?.[0] || null
}

const addCampaignCreativeRow = () => {
    campaignCreativeRows.value.push({
        name: `Variante ${String.fromCharCode(65 + campaignCreativeRows.value.length)}`,
        headline: '',
        primary_text: '',
        description: '',
        target_url: '',
        cta_label: '',
        creative_image_url: '',
        weight: 100,
        is_active: true,
    })
}

const removeCampaignCreativeRow = (index) => {
    campaignCreativeRows.value.splice(index, 1)
    if (!campaignCreativeRows.value.length) {
        addCampaignCreativeRow()
    }
}

const normalizeCampaignCreatives = () => campaignCreativeRows.value
    .map((creative) => ({
        name: String(creative.name || '').trim(),
        headline: String(creative.headline || '').trim(),
        primary_text: String(creative.primary_text || '').trim(),
        description: String(creative.description || '').trim(),
        target_url: String(creative.target_url || '').trim(),
        cta_label: String(creative.cta_label || '').trim(),
        creative_image_url: String(creative.creative_image_url || '').trim(),
        weight: Number(creative.weight || 100),
        is_active: Boolean(creative.is_active),
    }))
    .filter((creative) => creative.headline || creative.primary_text || creative.creative_image_url)

const storeProduct = () => {
    productForm.attributes_text = productAttributesText()
    productForm.attribute_options = normalizeAttributeRows(productAttributeRows.value)
    productForm.variants = normalizeVariantRows(productVariantRows.value)

    productForm
        .transform((data) => transformMoneyFields(data, ['price_cents']))
        .post(route('auth.commerce.products.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            productForm.reset('title', 'description', 'attributes_text', 'attribute_options', 'variants', 'image_url', 'image_urls_text', 'image_upload', 'image_uploads', 'sku', 'digital_delivery_note')
            productAttributeRows.value = [{ name: '', values: [] }]
            productVariantRows.value = []
        },
        })
}

const storeCampaign = () => {
    if (campaignForm.processing) {
        return
    }

    campaignForm.creatives = normalizeCampaignCreatives()
    campaignForm.provider = adProvider.value
    campaignForm.accepted_terms = adAcceptedTerms.value
    campaignForm.client_reference ||= newClientReference()
    campaignActionError.value = ''

    campaignForm
        .transform((data) => transformMoneyFields(data, ['budget_cents', 'daily_budget_cents']))
        .post(route('auth.commerce.campaigns.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            campaignForm.reset('name', 'headline', 'description', 'primary_text', 'target_url', 'creative_image_url', 'creative_image_upload', 'creatives', 'audience_locations', 'audience_interests', 'audience_age_min', 'audience_age_max', 'starts_at', 'ends_at')
            campaignForm.client_reference = ''
            campaignCreativeRows.value = [
                { name: 'Variante A', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
                { name: 'Variante B', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
            ]
        },
        })
}

const updateOwnCampaignStatus = (campaign, status) => {
    campaignActionError.value = ''

    router.put(route('auth.commerce.campaigns.status.update', campaign.id), { status }, {
        preserveScroll: true,
        onError: (errors) => {
            campaignActionError.value = errors.campaign_status || errors.status || 'Kampagne konnte nicht aktualisiert werden.'
        },
    })
}

const deleteOwnCampaign = (campaign) => {
    campaignActionError.value = ''
    deleteCampaignModal.value = { open: true, campaign, confirmation: '' }
}

const closeDeleteCampaignModal = () => {
    deleteCampaignModal.value = { open: false, campaign: null, confirmation: '' }
}

const confirmDeleteOwnCampaign = () => {
    const campaign = deleteCampaignModal.value.campaign

    if (!campaign || deleteCampaignModal.value.confirmation !== 'delete') {
        return
    }

    router.delete(route('auth.commerce.campaigns.destroy', campaign.id), {
        preserveScroll: true,
        data: {
            confirmation: deleteCampaignModal.value.confirmation,
        },
        onSuccess: closeDeleteCampaignModal,
        onError: (errors) => {
            campaignActionError.value = errors.confirmation || errors.campaign_status || 'Kampagne konnte nicht geloescht werden.'
        },
    })
}

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

        <section class="surface-card p-2">
            <div class="flex gap-2 overflow-x-auto">
                <button
                    v-for="tab in commerceTabs"
                    :key="tab.key"
                    type="button"
                    class="flex min-h-11 shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === tab.key ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-muted hover:text-primary'"
                    @click="activeTab = tab.key"
                >
                    <i :class="tab.icon"></i>
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.count"
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="activeTab === tab.key ? 'bg-white/20 text-buttonTextPrimary' : 'bg-bg text-secondary'"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>
        </section>

        <section v-show="activeTab === 'cart'" class="surface-card overflow-hidden">
            <div class="border-b border-border bg-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Warenkorb</p>
                        <h2 class="mt-1 text-2xl font-bold text-primary">Deine ausgewählten Produkte</h2>
                        <p class="mt-1 text-sm text-secondary">Hier erscheinen nur Artikel, die du bewusst in den Einkaufswagen gelegt hast.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary">
                            {{ cartItemCount }} Artikel
                        </span>
                        <button
                            class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                            :disabled="!cartItems.length"
                            @click="showCartCheckout = true"
                        >
                            Zur Kasse
                        </button>
                    </div>
                </div>
            </div>
            <div class="divide-y divide-border">
                <div v-for="item in cartItems" :key="item.id" class="grid gap-4 p-5 md:grid-cols-[5rem_minmax(0,1fr)_8rem_auto] md:items-center">
                    <Link :href="item.product?.show_url || route('auth.commerce.products.show', item.product?.id)" class="block overflow-hidden rounded-lg border border-border bg-inputBg">
                        <img v-if="item.product?.image_url" :src="item.product.image_url" :alt="item.product.title" class="aspect-square h-full w-full object-cover">
                        <div v-else class="flex aspect-square items-center justify-center">
                            <i class="las la-store text-3xl text-air-blue"></i>
                        </div>
                    </Link>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-bg px-2.5 py-1 text-xs font-semibold uppercase text-secondary">{{ item.product?.category || 'Produkt' }}</span>
                            <span v-if="item.product?.sku" class="text-xs text-secondary">Art.-Nr. {{ item.product.sku }}</span>
                        </div>
                        <Link :href="item.product?.show_url || route('auth.commerce.products.show', item.product?.id)" class="mt-2 block break-words font-semibold text-primary hover:text-air-blue">
                            {{ item.product?.title }}
                        </Link>
                        <p class="mt-1 line-clamp-2 text-sm text-secondary">{{ item.product?.description }}</p>
                        <p class="mt-2 text-xs font-semibold text-success">Verfuegbar: {{ item.product?.stock_quantity }} Stueck</p>
                    </div>
                    <input
                        :value="item.quantity"
                        type="number"
                        min="1"
                        :max="item.product?.stock_quantity || 1"
                        class="rounded-lg border-border bg-inputBg text-sm font-semibold text-primary"
                        @change="updateCartItem(item, Number($event.target.value || 1))"
                    >
                    <div class="flex items-center justify-between gap-3 md:block md:text-right">
                        <p class="text-lg font-bold text-primary">{{ formatMoney(item.line_total_cents, item.product?.currency || cart.summary?.currency || 'EUR') }}</p>
                        <button class="mt-0 rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error hover:bg-error/10 md:mt-3" @click="removeCartItem(item)">
                            Entfernen
                        </button>
                    </div>
                </div>
                <div v-if="cartItems.length" class="grid gap-3 bg-bg p-5 text-sm text-secondary sm:grid-cols-4">
                    <p class="rounded-lg border border-border bg-card p-3">Warenwert<br><span class="font-semibold text-primary">{{ formatMoney(cart.summary?.item_gross_cents, cart.summary?.currency) }}</span></p>
                    <p class="rounded-lg border border-border bg-card p-3">Versand<br><span class="font-semibold text-primary">{{ formatMoney(cart.summary?.shipping_cents, cart.summary?.currency) }}</span></p>
                    <p class="rounded-lg border border-border bg-card p-3">Steuer<br><span class="font-semibold text-primary">{{ formatMoney(cart.summary?.tax_cents, cart.summary?.currency) }}</span></p>
                    <p class="rounded-lg border border-border bg-card p-3">Gesamt<br><span class="text-lg font-bold text-primary">{{ formatMoney(cart.summary?.amount_cents, cart.summary?.currency) }}</span></p>
                </div>
                <div v-else class="grid gap-4 p-8 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-border bg-bg">
                        <i class="las la-shopping-bag text-3xl text-air-blue"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-primary">Dein Warenkorb ist leer</h3>
                        <p class="mt-1 text-sm text-secondary">Fuege ein Marketplace-Produkt hinzu, dann erscheint es hier.</p>
                    </div>
                    <Link :href="route('guest.marketplace')" class="mx-auto rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        Marketplace ansehen
                    </Link>
                </div>
            </div>
        </section>

        <section v-show="activeTab === 'marketplace'" class="surface-card p-5">
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

        <section v-show="activeTab === 'marketplace'" class="grid gap-5 lg:grid-cols-3">
            <article v-for="addon in addons" :key="addon.id" class="surface-card flex flex-col p-5">
                <h2 class="text-lg font-semibold text-primary">{{ addon.name }}</h2>
                <p class="mt-2 flex-1 text-sm text-secondary">{{ addon.description }}</p>
                <p class="mt-4 text-2xl font-bold text-primary">{{ formatMoney(addonPrice(addon)) }}</p>
                <button class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="checkoutAddon(addon)">
                    Add-on buchen
                </button>
            </article>
        </section>

        <section v-show="activeTab === 'marketplace'" class="surface-card overflow-hidden">
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

        <section v-show="activeTab === 'marketplace'" class="surface-card overflow-hidden">
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

        <section v-show="activeTab === 'create'" class="grid gap-6 xl:grid-cols-2">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Eigenes Angebot einreichen</h2>
                <form class="mt-4 grid gap-3" @submit.prevent="storeProduct">
                    <select v-model="productForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="">Privat / Anbieter</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <input v-model="productForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                    <div class="grid gap-3 rounded-lg border border-border bg-bg p-3">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Hauptbild per URL</label>
                            <input v-model="productForm.image_url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="https://...">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Hauptbild hochladen</label>
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setProductImageUpload">
                            <p class="mt-1 text-xs text-secondary">JPG, PNG oder WebP. Upload ersetzt die URL.</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Weitere Bild-URLs</label>
                            <textarea v-model="productForm.image_urls_text" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Eine URL pro Zeile"></textarea>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Weitere Bilder hochladen</label>
                            <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setProductGalleryUploads">
                            <p class="mt-1 text-xs text-secondary">Bis zu 8 Dateien, Galerie maximal 12 Bilder.</p>
                        </div>
                    </div>
                    <select v-model="productForm.category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="product">Produkt</option>
                        <option value="course">Kurs</option>
                        <option value="camp">Camp</option>
                        <option value="service">Dienstleistung</option>
                    </select>
                    <input v-model="productForm.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer">
                    <select v-model="productForm.product_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="single">Einfaches Produkt</option>
                        <option value="variable">Variables Produkt</option>
                        <option value="digital">Immaterial / digital</option>
                    </select>
                    <textarea
                        v-if="productForm.product_type === 'digital'"
                        v-model="productForm.digital_delivery_note"
                        rows="2"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Lieferinfo, z. B. Ticketcode oder Zugang wird per E-Mail versendet"
                    ></textarea>
                    <select v-model="productForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="standard">Standardsteuer</option>
                        <option value="reduced">Ermäßigt</option>
                        <option value="zero">Nullsatz</option>
                    </select>
                    <input v-model="productForm.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR, z. B. 10,99">
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="productForm.is_shippable" type="checkbox" class="rounded border-border bg-inputBg">
                        Versandpflichtig
                    </label>
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="productForm.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
                        Lagerbestand verwalten
                    </label>
                    <input v-if="productForm.manages_stock" v-model="productForm.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerbestand">
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Merkmale / Variantenoptionen</h3>
                                <p class="text-xs text-secondary">Ein Merkmal pro Zeile. Werte mit Komma oder | trennen.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addProductAttributeRow">
                                Merkmal hinzufügen
                            </button>
                        </div>
                        <div class="mt-3 space-y-2">
                            <div v-for="(row, index) in productAttributeRows" :key="index" class="grid gap-2">
                                <select v-model="row.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" @change="row.values = []">
                                    <option value="">Merkmal wählen</option>
                                    <option v-for="preset in attributePresets" :key="preset.name" :value="preset.name">{{ preset.name }}</option>
                                </select>
                                <select v-model="row.values" multiple class="min-h-24 rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-for="value in presetValuesFor(row.name)" :key="value" :value="value">{{ value }}</option>
                                </select>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="removeProductAttributeRow(index)">
                                    Entfernen
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="productForm.product_type === 'variable'" class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Varianten</h3>
                                <p class="text-xs text-secondary">Eigener Preis, Bestand und Bild pro Variante.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addProductVariantRow">Variante</button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(variant, index) in productVariantRows" :key="index" class="grid gap-2 rounded-lg border border-border p-3">
                                <select
                                    v-for="attribute in normalizeAttributeRows(productAttributeRows)"
                                    :key="attribute.name"
                                    v-model="variant.attributes[attribute.name]"
                                    class="rounded-lg border-border bg-inputBg text-sm text-primary"
                                >
                                    <option value="">{{ attribute.name }}</option>
                                    <option v-for="value in attribute.values" :key="value" :value="value">{{ value }}</option>
                                </select>
                                <input v-model="variant.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR">
                                <input v-model="variant.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bestand">
                                <input v-model="variant.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer">
                                <input v-model="variant.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="removeProductVariantRow(index)">Variante entfernen</button>
                            </div>
                        </div>
                    </div>
                    <textarea v-model="productForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Zur Prüfung einreichen</button>
                </form>
                <p class="mt-4 text-sm text-secondary">{{ myProducts.length }} eigene Angebote</p>
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

        <section v-show="activeTab === 'payouts'" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
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

        <section v-show="activeTab === 'ads'" class="grid gap-6 xl:grid-cols-[28rem_minmax(0,1fr)]">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Sponsor-Kampagne vorbereiten</h2>
                <form class="mt-4 grid gap-3" @submit.prevent="storeCampaign">
                    <input v-model="campaignForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kampagnenname">
                    <input v-model="campaignForm.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Headline, max. 120 Zeichen">
                    <input v-model="campaignForm.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL">
                    <textarea v-model="campaignForm.primary_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anzeigentext / Primary Text"></textarea>
                    <textarea v-model="campaignForm.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interne Beschreibung"></textarea>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <select v-model="campaignForm.objective" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="traffic">Traffic</option>
                            <option value="awareness">Reichweite</option>
                            <option value="leads">Leads</option>
                            <option value="sales">Sales</option>
                        </select>
                        <select v-model="campaignForm.placement" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="marketplace_card">Marketplace Karte</option>
                            <option value="feed">Feed</option>
                            <option value="sidebar">Sidebar</option>
                            <option value="sponsor_section">Sponsor-Bereich</option>
                        </select>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">Creative Format und Bildmaße</label>
                        <select v-model="campaignForm.creative_format" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="format in adFormats" :key="format.key" :value="format.key">
                                {{ format.label }} - {{ format.size }}
                            </option>
                        </select>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ selectedAdFormat.size }} · {{ selectedAdFormat.ratio }}</p>
                        <p class="text-xs text-secondary">{{ selectedAdFormat.hint }}</p>
                    </div>
                    <input v-model="campaignForm.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL optional">
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">Bild hochladen</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setCampaignCreativeUpload">
                        <p class="mt-1 text-xs text-secondary">JPG, PNG oder WebP bis 8 MB. Empfohlen: {{ selectedAdFormat.size }}.</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">A/B-Test Varianten</label>
                                <p class="mt-1 text-xs text-secondary">Lege mindestens zwei Varianten mit unterschiedlicher Headline, Text oder Bild-URL an.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addCampaignCreativeRow">
                                Variante hinzufügen
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(creative, index) in campaignCreativeRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-card p-3">
                                <div class="grid gap-2 sm:grid-cols-[1fr_6rem_auto]">
                                    <input v-model="creative.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Variante A">
                                    <input v-model.number="creative.weight" type="number" min="1" max="1000" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gewicht">
                                    <label class="flex items-center gap-2 text-xs font-semibold text-primary">
                                        <input v-model="creative.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                                        Aktiv
                                    </label>
                                </div>
                                <input v-model="creative.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Headline dieser Variante">
                                <textarea v-model="creative.primary_text" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anzeigentext dieser Variante"></textarea>
                                <textarea v-model="creative.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung dieser Variante"></textarea>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input v-model="creative.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL optional">
                                    <input v-model="creative.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA optional">
                                </div>
                                <input v-model="creative.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL dieser Variante">
                                <button v-if="campaignCreativeRows.length > 1" type="button" class="justify-self-start rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="removeCampaignCreativeRow(index)">
                                    Variante entfernen
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="campaignForm.budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gesamtbudget in EUR">
                        <input v-model="campaignForm.daily_budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tagesbudget in EUR">
                        <input v-model="campaignForm.starts_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="campaignForm.ends_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="campaignForm.audience_age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von">
                        <input v-model="campaignForm.audience_age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis">
                    </div>
                    <input v-model="campaignForm.audience_locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Regionen, z. B. Berlin, NRW">
                    <input v-model="campaignForm.audience_interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interessen, z. B. Fußball, Fitness">
                    <input v-model="campaignForm.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA, z. B. Jetzt ansehen">
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">Zahlungsart</label>
                        <select v-model="adProvider" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="bank_transfer">Ueberweisung</option>
                            <option value="stripe">Stripe</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                        <input v-model="adAcceptedTerms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            Ich akzeptiere AGB, Widerrufshinweise und nehme zur Kenntnis, dass die Kampagne erst nach Zahlung zur Pruefung eingereicht wird.
                            <Link :href="route('terms.show')" class="text-air-blue underline">AGB</Link>
                            <span> - </span>
                            <Link :href="route('legal.withdrawal')" class="text-air-blue underline">Widerruf</Link>
                        </span>
                    </label>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="campaignForm.processing">
                        Zahlung starten
                    </button>
                </form>
                <p class="mt-4 text-sm text-secondary">{{ myCampaigns.length }} eigene Kampagnen</p>
            </article>

            <article class="surface-card overflow-hidden">
                <div class="border-b border-border p-5">
                    <h2 class="text-lg font-semibold text-primary">Meine Ads-Kampagnen</h2>
                    <p class="mt-1 text-sm text-secondary">Status, Impressionen, Klicks und CTR deiner vorbereiteten oder aktiven Kampagnen.</p>
                    <div v-if="campaignActionError || page.props.errors?.campaign_status" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {{ campaignActionError || page.props.errors.campaign_status }}
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
                                <th class="px-5 py-3 text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template v-for="campaign in myCampaigns" :key="campaign.id">
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-primary">{{ campaign.headline || campaign.name }}</p>
                                    <p class="text-xs text-secondary">{{ campaign.target_url || '-' }}</p>
                                    <p class="text-xs text-secondary">{{ campaign.placement }} · {{ campaign.creative_format }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-secondary">{{ campaign.status }}</p>
                                    <p v-if="campaign.payment_pending" class="mt-1 text-xs font-semibold text-warning">Zahlung offen</p>
                                    <p v-else-if="campaign.payment_completed" class="mt-1 text-xs font-semibold text-success">Bezahlt</p>
                                </td>
                                <td class="px-5 py-3 text-secondary">{{ campaign.impressions || 0 }}</td>
                                <td class="px-5 py-3 text-secondary">{{ campaign.clicks || 0 }}</td>
                                <td class="px-5 py-3 text-secondary">{{ formatPercent(ctr(campaign.clicks, campaign.impressions)) }}</td>
                                <td class="px-5 py-3 text-secondary">{{ formatMoney(campaign.budget_cents) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button
                                            v-if="['active', 'pending_review'].includes(campaign.status)"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="updateOwnCampaignStatus(campaign, 'paused')"
                                        >
                                            Pausieren
                                        </button>
                                        <button
                                            v-if="campaign.status === 'paused' && campaign.payment_completed && campaign.reviewed_at"
                                            type="button"
                                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                                            @click="updateOwnCampaignStatus(campaign, 'active')"
                                        >
                                            Fortsetzen
                                        </button>
                                        <button
                                            v-if="campaign.status === 'paused' && campaign.payment_completed && !campaign.reviewed_at"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="updateOwnCampaignStatus(campaign, 'pending_review')"
                                        >
                                            Zur Pruefung
                                        </button>
                                        <button
                                            v-if="['pending_review', 'paused'].includes(campaign.status)"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="updateOwnCampaignStatus(campaign, 'draft')"
                                        >
                                            Zurueckziehen
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger"
                                            @click="deleteOwnCampaign(campaign)"
                                        >
                                            Loeschen
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="campaign.creatives?.length">
                                <td colspan="7" class="bg-bg px-5 py-3">
                                    <div class="grid gap-2 md:grid-cols-2">
                                        <div v-for="creative in campaign.creatives" :key="creative.id" class="rounded-lg border border-border bg-card p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-semibold text-primary">{{ creative.name }}</p>
                                                    <p class="text-xs text-secondary">{{ creative.headline || campaign.headline || campaign.name }}</p>
                                                </div>
                                                <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">Gewicht {{ creative.weight }}</span>
                                            </div>
                                            <div class="mt-3 grid grid-cols-4 gap-2 text-xs">
                                                <div>
                                                    <p class="text-secondary">Views</p>
                                                    <p class="font-semibold text-primary">{{ creative.impressions || 0 }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-secondary">Klicks</p>
                                                    <p class="font-semibold text-primary">{{ creative.clicks || 0 }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-secondary">CTR</p>
                                                    <p class="font-semibold text-primary">{{ formatPercent(ctr(creative.clicks, creative.impressions)) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-secondary">Kosten</p>
                                                    <p class="font-semibold text-primary">{{ formatMoney(creative.spent_cents) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                    </table>
                    <p v-if="!myCampaigns.length" class="px-5 py-6 text-sm text-secondary">Noch keine eigenen Ads-Kampagnen.</p>
                </div>
            </article>
        </section>

        <section v-show="activeTab === 'orders'" class="surface-card overflow-hidden">
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
                                    Rücksendung
                                </button>
                                <span v-else-if="order.issue_status && order.issue_status !== 'none'" class="text-xs text-secondary">{{ order.issue_status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!activeOrders.length" class="px-5 py-6 text-sm text-secondary">Noch keine Bestellungen.</p>
            </div>
        </section>

        <section v-show="activeTab === 'orders'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Meine Rücksendungen</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="request in returnRequests" :key="request.id">
                            <td class="px-5 py-3 font-semibold text-primary">{{ request.item?.title || request.order?.orderable?.title || 'Rücksendung' }}</td>
                            <td class="px-5 py-3 text-secondary">{{ request.status }}</td>
                            <td class="px-5 py-3 text-secondary">{{ request.reason }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!returnRequests.length" class="px-5 py-6 text-sm text-secondary">Noch keine Rücksendungen.</p>
            </div>
        </section>

        <div v-if="issueModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-semibold text-primary">
                    {{ issueModal.mode === 'return' ? 'Rücksendung anfragen' : 'Problem melden' }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    Beschreibe kurz, was geprüft werden soll.
                </p>
                <textarea v-model="issueModal.note" rows="5" class="mt-4 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Grund eingeben"></textarea>
                <div class="mt-5 flex justify-end gap-3">
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeIssueModal">Abbrechen</button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="submitOrderRequest">Senden</button>
                </div>
            </div>
        </div>

        <div v-if="deleteCampaignModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg rounded-xl border border-danger/30 bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-semibold text-primary">Ads-Kampagne loeschen</h2>
                <p class="mt-2 text-sm text-secondary">
                    Diese Kampagne wird dauerhaft geloescht:
                    <span class="font-semibold text-primary">{{ deleteCampaignModal.campaign?.headline || deleteCampaignModal.campaign?.name }}</span>
                </p>
                <p class="mt-4 text-sm text-secondary">
                    Bitte gib <strong class="text-primary">delete</strong> ein, um die Loeschung zu bestaetigen.
                </p>
                <input
                    v-model="deleteCampaignModal.confirmation"
                    class="mt-3 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                    placeholder="delete"
                    autocomplete="off"
                >
                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary"
                        @click="closeDeleteCampaignModal"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-danger px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="deleteCampaignModal.confirmation !== 'delete'"
                        @click="confirmDeleteOwnCampaign"
                    >
                        Endgueltig loeschen
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showCartCheckout" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <form class="max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="checkoutCart">
                <h2 class="text-lg font-semibold text-primary">Einkaufswagen abschließen</h2>
                <p class="mt-2 text-sm text-secondary">
                    Gesamt: {{ formatMoney(cart.summary?.amount_cents, cart.summary?.currency) }}
                </p>

                <div class="mt-4 rounded-xl border border-border bg-bg p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Ausgewählte Produkte</p>
                    <div class="mt-3 space-y-3">
                        <div v-for="item in cartItems" :key="`checkout-${item.id}`" class="flex items-center gap-3">
                            <img v-if="item.product?.image_url" :src="item.product.image_url" :alt="item.product.title" class="h-12 w-12 rounded-lg object-cover">
                            <div v-else class="flex h-12 w-12 items-center justify-center rounded-lg border border-border bg-inputBg">
                                <i class="las la-store text-xl text-air-blue"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-primary">{{ item.product?.title }}</p>
                                <p class="text-xs text-secondary">{{ item.quantity }} x {{ formatMoney(item.product?.price_cents, item.product?.currency || cart.summary?.currency || 'EUR') }}</p>
                            </div>
                            <p class="text-sm font-bold text-primary">{{ formatMoney(item.line_total_cents, item.product?.currency || cart.summary?.currency || 'EUR') }}</p>
                        </div>
                    </div>
                </div>

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
