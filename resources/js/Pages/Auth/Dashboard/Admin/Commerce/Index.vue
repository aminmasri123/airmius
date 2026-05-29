<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { centsToMajor, majorToCents, moneyInputAttrs, transformMoneyFields } from '@/utils/currency'
import { confirmDialog, promptDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    coupons: { type: Array, default: () => [] },
    addons: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    sellerApplications: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    adReport: { type: Object, default: () => ({}) },
    adPlacementReport: { type: Array, default: () => [] },
    adDiagnostics: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    websiteRequests: { type: Array, default: () => [] },
    payoutProfiles: { type: Array, default: () => [] },
    payoutCandidates: { type: Array, default: () => [] },
    payouts: { type: Array, default: () => [] },
    marketplaceVisuals: { type: Array, default: () => [] },
    taxRates: { type: Array, default: () => [] },
    shippingRates: { type: Array, default: () => [] },
    shippingCarriers: { type: Array, default: () => [] },
    marketplaceCategoryCommissions: { type: Array, default: () => [] },
    commerceSettings: { type: Object, default: () => ({}) },
    returnRequests: { type: Array, default: () => [] },
    auditLogs: { type: Array, default: () => [] },
    ossReport: { type: Array, default: () => [] },
    sellerReports: { type: Array, default: () => [] },
})

const page = usePage()
const queryTab = new URLSearchParams(String(page.url || '').split('?')[1] || '').get('tab')
const activeTab = ref(queryTab || 'marketplace')
const campaignStatusError = ref('')
const stockAdjustments = ref({})

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
    monthly_price_cents: '',
    yearly_price_cents: '',
    target_actor: 'verein',
    features: [],
    is_active: true,
})

const productForm = useForm({
    title: '',
    description: '',
    features_text: '',
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
    return_policy_type: 'standard',
    return_window_days: 14,
    digital_delivery_note: '',
    price_cents: '',
    currency: 'EUR',
    status: 'draft',
    commission_percent: 10,
})
const productAttributeRows = ref([{ name: '', values: [] }])
const productFeatureRows = ref([''])
const productVariantRows = ref([])

const marketplaceVisualForm = useForm({
    sources: Object.fromEntries(props.marketplaceVisuals.map((visual) => [visual.key, visual.source || ''])),
    dimensions: Object.fromEntries(props.marketplaceVisuals.map((visual) => [visual.key, { width: visual.width, height: visual.height }])),
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
    ads_cpm_cents: centsToMajor(props.commerceSettings.ads_cpm_cents ?? 500),
    ads_cpc_cents: centsToMajor(props.commerceSettings.ads_cpc_cents ?? 30),
    ads_cpl_cents: centsToMajor(props.commerceSettings.ads_cpl_cents ?? 200),
    ads_cpa_percent: props.commerceSettings.ads_cpa_percent ?? 10,
    ads_min_budget_cents: centsToMajor(props.commerceSettings.ads_min_budget_cents ?? 1000),
    ads_frequency_cap_per_day: props.commerceSettings.ads_frequency_cap_per_day ?? 3,
    ads_frequency_cap_feed: props.commerceSettings.ads_frequency_cap_feed ?? 3,
    ads_frequency_cap_sidebar: props.commerceSettings.ads_frequency_cap_sidebar ?? 6,
    ads_frequency_cap_marketplace_card: props.commerceSettings.ads_frequency_cap_marketplace_card ?? 3,
    ads_frequency_cap_sponsor_section: props.commerceSettings.ads_frequency_cap_sponsor_section ?? 4,
})

const marketplaceCommissionForm = useForm({
    default_commission_percent: props.commerceSettings.marketplace_default_commission_percent ?? 10,
    commissions: props.marketplaceCategoryCommissions.map((row) => ({
        category: row.category,
        label: row.label,
        commission_percent: row.commission_percent ?? props.commerceSettings.marketplace_default_commission_percent ?? 10,
    })),
})

const shippingRateForm = useForm({
    name: 'Deutschland Standardversand',
    origin_country_code: '',
    country_code: 'DE',
    postal_code_prefix: '',
    amount_cents: '4,90',
    currency: 'EUR',
    free_from_cents: '100,00',
    is_active: true,
    priority: 10,
})

const campaignForm = useForm({
    is_internal: false,
    force_priority: false,
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
    audience_excluded_locations: '',
    audience_excluded_interests: '',
    audience_devices: '',
    audience_languages: '',
    audience_hours: '',
    audience_age_min: '',
    audience_age_max: '',
    budget_cents: '',
    daily_budget_cents: '',
    spent_cents: '',
    impressions: 0,
    clicks: 0,
    status: 'draft',
    review_note: '',
    starts_at: '',
    ends_at: '',
})

const adFormats = [
    { key: 'feed_square', label: 'Feed Quadrat', size: '1080 x 1080 px' },
    { key: 'feed_portrait', label: 'Feed Portrait', size: '1080 x 1350 px' },
    { key: 'story_vertical', label: 'Story/Reel', size: '1080 x 1920 px' },
    { key: 'banner_wide', label: 'Wide Banner', size: '1200 x 628 px' },
]
const selectedAdFormat = computed(() => adFormats.find((format) => format.key === campaignForm.creative_format) || adFormats[0])
const campaignCreativeRows = ref([
    { name: 'Variante A', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
    { name: 'Variante B', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
])
const rejectionModal = useForm({
    open: false,
    product: null,
    selectedReason: '',
    reason: '',
})
const productDeleteModal = ref({
    open: false,
    product: null,
    confirmation: '',
    processing: false,
})
const rejectionReasons = [
    { value: 'missing_required_info', label: 'Pflichtangaben fehlen', text: 'Bitte ergaenze die fehlenden Pflichtangaben wie Beschreibung, Preis, Kategorie oder Lieferinformationen.' },
    { value: 'unclear_offer', label: 'Angebot ist unklar', text: 'Das Angebot ist für Käufer noch nicht eindeutig genug beschrieben. Bitte erklaere Inhalt, Umfang und Ablauf genauer.' },
    { value: 'invalid_category', label: 'Falsche Kategorie', text: 'Das Angebot passt nicht zur gewählten Kategorie. Bitte wähle die passende Marketplace-Kategorie.' },
    { value: 'bad_images', label: 'Bilder fehlen oder sind ungeeignet', text: 'Bitte lade passende, klare Bilder hoch. Platzhalter, unscharfe oder irreführende Bilder können nicht freigegeben werden.' },
    { value: 'price_or_tax_issue', label: 'Preis, Steuer oder Versand unklar', text: 'Preis, Steuerklasse, Versand oder Lieferbedingungen sind nicht plausibel genug angegeben.' },
    { value: 'prohibited_content', label: 'Nicht erlaubter Inhalt', text: 'Dieses Angebot enthält Inhalte oder Leistungen, die auf Airmius nicht veröffentlicht werden können.' },
    { value: 'quality_review', label: 'Qualitätsprüfung nicht bestanden', text: 'Das Angebot erfüllt aktuell nicht die Qualitätsanforderungen für den Marketplace.' },
    { value: 'duplicate', label: 'Doppeltes Angebot', text: 'Ein sehr aehnliches Angebot existiert bereits. Bitte bearbeite das bestehende Angebot statt ein neues einzureichen.' },
    { value: 'custom', label: 'Eigener Grund', text: '' },
]
const editProductForm = useForm({
    open: false,
    product: null,
    title: '',
    description: '',
    features_text: '',
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
    stock_quantity: 0,
    low_stock_threshold: 0,
    tax_class: 'standard',
    return_policy_type: 'standard',
    return_window_days: 14,
    digital_delivery_note: '',
    price_cents: '',
    currency: 'EUR',
    status: 'draft',
    rejection_reason: '',
    commission_percent: 10,
})
const editAttributeRows = ref([{ name: '', values: [] }])
const editFeatureRows = ref([''])
const editVariantRows = ref([])
const shippingModal = useForm({
    open: false,
    order: null,
    shipping_status: 'open',
    shipping_carrier: '',
    shipping_label_url: '',
    tracking_number: '',
    tracking_url: '',
})
const refundModal = useForm({
    open: false,
    order: null,
    amount_cents: '',
    reason: '',
})
const issueReplyModal = useForm({
    open: false,
    order: null,
    issue_response: '',
    issue_status: 'reviewing',
})
const reportedOrders = computed(() => props.orders.filter((order) => order.issue_status && order.issue_status !== 'none'))

const sellerApplicationStatusLabel = (status) => ({
    pending: 'Wartet auf Prüfung',
    approved: 'Freigegeben',
    rejected: 'Abgelehnt',
}[status] || status || '-')

const formatMoney = (cents) => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
}).format(Number(cents || 0) / 100)

const formatDateTime = (value) => {
    if (!value) {
        return '-'
    }

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const orderPaymentLabel = (order) => {
    if (order.status === 'completed') {
        return order.shipping_status === 'delivered' ? 'Abgeschlossen' : 'Bezahlt'
    }

    return {
        pending: 'Offen',
        awaiting_transfer: 'Wartet auf Überweisung',
        cancelled: 'Storniert',
        refunded: 'Erstattet',
    }[order.status] || order.status
}

const orderPaymentHint = (order) => {
    if (order.status === 'completed') {
        return order.shipping_status === 'delivered'
            ? 'Zahlung und Zustellung erledigt'
            : 'Zahlung eingegangen, Versand läuft noch'
    }

    return {
        pending: 'Zahlung noch offen',
        awaiting_transfer: 'Banküberweisung muss bestätigt werden',
        cancelled: 'Bestellung wurde storniert',
        refunded: 'Betrag wurde erstattet',
    }[order.status] || ''
}

const orderShippingLabel = (status) => ({
    open: 'Offen',
    prepared: 'Wird vorbereitet',
    shipped: 'Versendet',
    delivered: 'Zugestellt',
}[status || 'open'] || status)

const trackingUrlFor = (carrier, trackingNumber) => {
    const number = String(trackingNumber || '').replace(/\s+/g, '').toUpperCase()
    const normalizedCarrier = String(carrier || '').trim().toLowerCase()

    if (!number) return ''
    if (normalizedCarrier === 'dhl') return `https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=${encodeURIComponent(number)}`
    if (normalizedCarrier === 'ups') return `https://www.ups.com/track?tracknum=${encodeURIComponent(number)}`
    if (normalizedCarrier === 'dpd') return `https://tracking.dpd.de/status/de_DE/parcel/${encodeURIComponent(number)}`
    if (normalizedCarrier === 'hermes') return `https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation/#${encodeURIComponent(number)}`
    if (normalizedCarrier === 'gls') return `https://gls-group.com/DE/de/paketverfolgung?match=${encodeURIComponent(number)}`
    if (normalizedCarrier === 'fedex') return `https://www.fedex.com/fedextrack/?trknbr=${encodeURIComponent(number)}`

    return ''
}

const autofillTrackingUrl = () => {
    if (shippingModal.tracking_url) return

    shippingModal.tracking_url = trackingUrlFor(shippingModal.shipping_carrier, shippingModal.tracking_number)
}

const orderIssueLabel = (status) => ({
    reported: 'Problem gemeldet',
    reviewing: 'In Prüfung',
    resolved: 'Gelöst',
    refunded: 'Erstattet',
    cancelled: 'Storniert',
}[status] || status)

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

const tabs = computed(() => [
    { key: 'marketplace', label: 'Marketplace', count: props.products.length + props.sellerApplications.length + props.warehouses.length },
    { key: 'orders', label: 'Bestellungen', count: props.orders.length + props.returnRequests.length + props.websiteRequests.length },
    { key: 'settings', label: 'Steuern & Versand', count: props.taxRates.length + props.shippingRates.length },
    { key: 'ad-prices', label: 'Ads Preise', count: 5 },
    { key: 'ads', label: 'Ads', count: props.campaigns.length },
    { key: 'marketing', label: 'Rabatte & Add-ons', count: props.coupons.length + props.addons.length },
    { key: 'payouts', label: 'Auszahlungen', count: props.payoutCandidates.length + props.payouts.length },
    { key: 'reports', label: 'Reports', count: props.auditLogs.length + props.sellerReports.length },
])

const adPricingCards = computed(() => [
    {
        key: 'ads_cpm_cents',
        label: 'CPM',
        title: 'Preis pro 1.000 Impressionen',
        value: commerceSettingsForm.ads_cpm_cents,
        suffix: 'EUR / 1.000 Views',
        formula: 'Kosten = Impressionen / 1.000 x CPM',
        example: `10.000 Views = ${formatMoney((majorToCents(commerceSettingsForm.ads_cpm_cents) * 10))}`,
    },
    {
        key: 'ads_cpc_cents',
        label: 'CPC',
        title: 'Preis pro Klick',
        value: commerceSettingsForm.ads_cpc_cents,
        suffix: 'EUR / Klick',
        formula: 'Kosten = Klicks x CPC',
        example: `100 Klicks = ${formatMoney((majorToCents(commerceSettingsForm.ads_cpc_cents) * 100))}`,
    },
    {
        key: 'ads_cpl_cents',
        label: 'CPL',
        title: 'Preis pro Lead',
        value: commerceSettingsForm.ads_cpl_cents,
        suffix: 'EUR / Lead',
        formula: 'Kosten = Leads x CPL',
        example: `25 Leads = ${formatMoney((majorToCents(commerceSettingsForm.ads_cpl_cents) * 25))}`,
    },
    {
        key: 'ads_cpa_percent',
        label: 'CPA',
        title: 'Provision pro Verkauf',
        value: commerceSettingsForm.ads_cpa_percent,
        suffix: '% vom Warenwert',
        formula: 'Kosten = Warenwert x CPA-Prozent / 100',
        example: `600 EUR Verkauf = ${formatMoney(60000 * (Number(commerceSettingsForm.ads_cpa_percent || 0) / 100))}`,
    },
])

const attributePresets = [
    { name: 'Farbe', values: ['Schwarz', 'Weiß', 'Rot', 'Blau', 'Grün', 'Gelb', 'Orange', 'Grau'] },
    { name: 'Größe', values: ['XS', 'S', 'M', 'L', 'XL', 'XXL', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46'] },
    { name: 'Material', values: ['Baumwolle', 'Polyester', 'Leder', 'Mesh', 'Kunststoff', 'Metall'] },
    { name: 'Dauer', values: ['30 Minuten', '60 Minuten', '90 Minuten', '1 Tag', '2 Tage', 'Wochenende'] },
    { name: 'Lieferart', values: ['E-Mail', 'Download', 'Online-Zugang', 'Vor Ort', 'Versand'] },
]

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

const productAttributesText = () => productAttributeRows.value
    .map((row) => ({
        name: String(row.name || '').trim(),
        values: Array.isArray(row.values) ? row.values.join(' | ') : String(row.values || '').trim(),
    }))
    .filter((row) => row.name && row.values)
    .map((row) => `${row.name}: ${row.values}`)
    .join('\n')

const productFeaturesText = () => productFeatureRows.value
    .map((feature) => String(feature || '').trim())
    .filter(Boolean)
    .join('\n')

const attributeRowsToText = (rows) => rows
    .map((row) => ({
        name: String(row.name || '').trim(),
        values: Array.isArray(row.values) ? row.values.join(' | ') : String(row.values || '').trim(),
    }))
    .filter((row) => row.name && row.values)
    .map((row) => `${row.name}: ${row.values}`)
    .join('\n')

const featureRowsToText = (rows) => rows
    .map((feature) => String(feature || '').trim())
    .filter(Boolean)
    .join('\n')

const galleryUrlsText = (product) => (product.gallery_images || [])
    .filter((image) => image && image !== product.image_url)
    .join('\n')

const productPayload = (product, overrides = {}) => ({
    title: product.title,
    description: product.description,
    features_text: (product.features || []).join('\n'),
    attributes_text: (product.product_attributes || []).map((attribute) => `${attribute.name}: ${attribute.value}`).join('\n'),
    attribute_options: product.attribute_options || [],
    variants: product.variants || [],
    image_url: product.image_url || '',
    image_urls_text: galleryUrlsText(product),
    category: product.category,
    product_type: product.product_type || 'single',
    price_cents: product.price_cents,
    currency: product.currency || 'EUR',
    status: product.status,
    rejection_reason: product.rejection_reason || null,
    commission_percent: product.commission_percent ?? 10,
    sku: product.sku || '',
    is_shippable: Boolean(product.is_shippable),
    manages_stock: Boolean(product.manages_stock),
    stock_quantity: product.stock_quantity ?? 0,
    low_stock_threshold: product.low_stock_threshold || 0,
    tax_class: product.tax_class || 'standard',
    return_policy_type: product.return_policy_type || 'standard',
    return_window_days: product.return_window_days ?? 14,
    digital_delivery_note: product.digital_delivery_note || '',
    ...overrides,
})

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

const addProductFeatureRow = () => {
    productFeatureRows.value.push('')
}

const removeProductFeatureRow = (index) => {
    productFeatureRows.value.splice(index, 1)
    if (!productFeatureRows.value.length) {
        addProductFeatureRow()
    }
}

const addEditAttributeRow = () => {
    editAttributeRows.value.push({ name: '', values: [] })
}

const removeEditAttributeRow = (index) => {
    editAttributeRows.value.splice(index, 1)
    if (!editAttributeRows.value.length) {
        addEditAttributeRow()
    }
}

const addEditVariantRow = () => {
    editVariantRows.value.push({ sku: '', price_cents: editProductForm.price_cents || '', stock_quantity: '', image_url: '', attributes: {} })
}

const removeEditVariantRow = (index) => {
    editVariantRows.value.splice(index, 1)
}

const addEditFeatureRow = () => {
    editFeatureRows.value.push('')
}

const removeEditFeatureRow = (index) => {
    editFeatureRows.value.splice(index, 1)
    if (!editFeatureRows.value.length) {
        addEditFeatureRow()
    }
}

const setProductImageUpload = (event) => {
    productForm.image_upload = event.target.files?.[0] || null
}

const setProductGalleryUploads = (event) => {
    productForm.image_uploads = Array.from(event.target.files || [])
}

const setEditProductImageUpload = (event) => {
    editProductForm.image_upload = event.target.files?.[0] || null
}

const setEditProductGalleryUploads = (event) => {
    editProductForm.image_uploads = Array.from(event.target.files || [])
}

const storeCoupon = () => couponForm
    .transform((data) => data.type === 'fixed'
        ? transformMoneyFields(data, ['value_cents'])
        : { ...data, value_cents: 0 })
    .post(route('admin.commerce.coupons.store'), {
    preserveScroll: true,
    onSuccess: () => couponForm.reset('code', 'name', 'max_redemptions', 'starts_at', 'ends_at'),
})

const storeAddon = () => addonForm
    .transform((data) => transformMoneyFields(data, ['monthly_price_cents', 'yearly_price_cents']))
    .post(route('admin.commerce.addons.store'), {
    preserveScroll: true,
    onSuccess: () => addonForm.reset('slug', 'name', 'description'),
})

const storeProduct = () => {
    productForm.attributes_text = productAttributesText()
    productForm.features_text = productFeaturesText()
    productForm.attribute_options = normalizeAttributeRows(productAttributeRows.value)
    productForm.variants = normalizeVariantRows(productVariantRows.value)

    productForm
        .transform((data) => transformMoneyFields(data, ['price_cents']))
        .post(route('admin.commerce.products.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            productForm.reset('title', 'description', 'features_text', 'attributes_text', 'attribute_options', 'variants', 'image_url', 'image_urls_text', 'image_upload', 'image_uploads', 'sku', 'digital_delivery_note')
            productAttributeRows.value = [{ name: '', values: [] }]
            productFeatureRows.value = ['']
            productVariantRows.value = []
        },
        })
}

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

const updateCommerceSettings = () => commerceSettingsForm
    .transform((data) => transformMoneyFields(data, ['ads_cpm_cents', 'ads_cpc_cents', 'ads_cpl_cents', 'ads_min_budget_cents']))
    .put(route('admin.commerce.settings.update'), {
    preserveScroll: true,
})

const updateMarketplaceCommissions = () => marketplaceCommissionForm.put(route('admin.commerce.marketplace-commissions.update'), {
    preserveScroll: true,
})

const addMarketplaceCommissionRow = () => {
    marketplaceCommissionForm.commissions.push({
        category: '',
        label: 'Neue Kategorie',
        commission_percent: marketplaceCommissionForm.default_commission_percent || 10,
    })
}

const removeMarketplaceCommissionRow = (index) => {
    marketplaceCommissionForm.commissions.splice(index, 1)
}

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

const storeShippingRate = () => shippingRateForm
    .transform((data) => transformMoneyFields(data, ['amount_cents', 'free_from_cents']))
    .post(route('admin.commerce.shipping-rates.store'), {
    preserveScroll: true,
    onSuccess: () => shippingRateForm.reset('postal_code_prefix'),
})

const updateShippingRate = (rate) => {
    router.put(route('admin.commerce.shipping-rates.update', rate.id), {
        name: rate.name,
        origin_country_code: rate.origin_country_code || '',
        country_code: rate.country_code || '',
        postal_code_prefix: rate.postal_code_prefix || '',
        amount_cents: typeof rate.amount_cents === 'number' ? rate.amount_cents : majorToCents(rate.amount_cents),
        currency: rate.currency || 'EUR',
        free_from_cents: typeof rate.free_from_cents === 'number' ? rate.free_from_cents : majorToCents(rate.free_from_cents),
        is_active: Boolean(rate.is_active),
        priority: rate.priority || 100,
    }, { preserveScroll: true })
}

const updateProductStatus = async (product, status) => {
    if (status === 'rejected') {
        rejectionModal.open = true
        rejectionModal.product = product
        rejectionModal.selectedReason = ''
        rejectionModal.reason = product.rejection_reason || ''
        return
    }

    if (status === 'published' && product.quality_issues?.length) {
        const confirmed = await confirmDialog({
            title: 'Produkt trotzdem freigeben?',
            message: `Dieses Produkt hat noch ${product.quality_issues.length} Qualitätsproblem(e):\n\n${product.quality_issues.join('\n')}\n\nTrotzdem freigeben?`,
            confirmLabel: 'Trotzdem freigeben',
        })

        if (!confirmed) {
            router.reload({ only: ['products'], preserveScroll: true })
            return
        }
    }

    router.put(route('admin.commerce.products.update', product.id), productPayload(product, { status }), { preserveScroll: true })
}

const submitRejection = () => {
    const product = rejectionModal.product
    if (!product || !rejectionModal.reason.trim()) return

    router.put(route('admin.commerce.products.update', product.id), productPayload(product, {
        status: 'rejected',
        rejection_reason: rejectionModal.reason,
    }), {
        preserveScroll: true,
        onSuccess: () => {
            rejectionModal.open = false
            rejectionModal.product = null
            rejectionModal.selectedReason = ''
            rejectionModal.reason = ''
        },
    })
}

const openDeleteProduct = (product) => {
    productDeleteModal.value = {
        open: true,
        product,
        confirmation: '',
        processing: false,
    }
}

const closeDeleteProduct = () => {
    productDeleteModal.value = {
        open: false,
        product: null,
        confirmation: '',
        processing: false,
    }
}

const destroyProduct = () => {
    const product = productDeleteModal.value.product
    if (!product || productDeleteModal.value.confirmation !== 'delete') {
        return
    }

    productDeleteModal.value.processing = true
    router.delete(route('admin.commerce.products.destroy', product.id), {
        preserveScroll: true,
        only: ['products', 'summary', 'auditLogs', 'sellerReports'],
        data: {
            confirmation: productDeleteModal.value.confirmation,
        },
        onSuccess: closeDeleteProduct,
        onFinish: () => {
            productDeleteModal.value.processing = false
        },
    })
}

const selectRejectionReason = () => {
    const selected = rejectionReasons.find((reason) => reason.value === rejectionModal.selectedReason)
    rejectionModal.reason = selected?.text || ''
}

const updateProductStock = (product) => {
    router.put(route('admin.commerce.products.update', product.id), productPayload(product, {
        manages_stock: true,
        stock_quantity: Math.max(0, Number(product.stock_quantity || 0)),
    }), { preserveScroll: true })
}

const inventoryAdjustmentKey = (product, inventory) => `${product.id}:${inventory.id}`

const inventoryAdjustment = (product, inventory) => stockAdjustments.value[inventoryAdjustmentKey(product, inventory)] || { quantity_delta: '', note: '' }

const setInventoryAdjustment = (product, inventory, field, value) => {
    const key = inventoryAdjustmentKey(product, inventory)
    stockAdjustments.value[key] = {
        ...inventoryAdjustment(product, inventory),
        [field]: value,
    }
}

const adjustInventoryStock = (product, inventory) => {
    const adjustment = inventoryAdjustment(product, inventory)
    const quantityDelta = Number(adjustment.quantity_delta || 0)

    if (!quantityDelta) {
        return
    }

    router.post(route('admin.commerce.products.stock.adjust', product.id), {
        marketplace_product_inventory_id: inventory.id,
        quantity_delta: quantityDelta,
        note: adjustment.note || `Admin-Korrektur ${inventory.country_code}`,
    }, {
        preserveScroll: true,
        only: ['products', 'summary', 'auditLogs', 'sellerReports'],
        onSuccess: () => {
            stockAdjustments.value[inventoryAdjustmentKey(product, inventory)] = { quantity_delta: '', note: '' }
        },
    })
}

const adjustGlobalStock = (product) => {
    const key = `${product.id}:global`
    const adjustment = stockAdjustments.value[key] || { quantity_delta: '', note: '' }
    const quantityDelta = Number(adjustment.quantity_delta || 0)

    if (!quantityDelta) {
        return
    }

    router.post(route('admin.commerce.products.stock.adjust', product.id), {
        quantity_delta: quantityDelta,
        note: adjustment.note || 'Admin-Korrektur global',
    }, {
        preserveScroll: true,
        only: ['products', 'summary', 'auditLogs', 'sellerReports'],
        onSuccess: () => {
            stockAdjustments.value[key] = { quantity_delta: '', note: '' }
        },
    })
}

const updateSellerApplication = async (application, status) => {
    const reviewNote = status === 'rejected'
        ? await promptDialog({
            title: 'Shop-Antrag ablehnen',
            message: 'Bitte gib den Ablehnungsgrund ein. Diese Notiz wird für die Prüfung gespeichert.',
            inputLabel: 'Ablehnungsgrund',
            multiline: true,
            required: true,
            confirmLabel: 'Ablehnen',
            danger: true,
        })
        : ''

    if (status === 'rejected' && (!reviewNote || !reviewNote.trim())) {
        return
    }

    router.put(route('admin.commerce.seller-applications.update', application.id), {
        status,
        review_note: reviewNote,
    }, { preserveScroll: true })
}

const openEditProduct = (product) => {
    editProductForm.open = true
    editProductForm.product = product
    editProductForm.title = product.title || ''
    editProductForm.description = product.description || ''
    editProductForm.features_text = (product.features || []).join('\n')
    editProductForm.attributes_text = (product.product_attributes || []).map((attribute) => `${attribute.name}: ${attribute.value}`).join('\n')
    editProductForm.attribute_options = product.attribute_options || []
    editProductForm.variants = product.variants || []
    editProductForm.image_url = product.image_url || ''
    editProductForm.image_urls_text = galleryUrlsText(product)
    editProductForm.image_upload = null
    editProductForm.image_uploads = []
    editProductForm.category = product.category || 'product'
    editProductForm.product_type = product.product_type || 'single'
    editProductForm.sku = product.sku || ''
    editProductForm.is_shippable = Boolean(product.is_shippable)
    editProductForm.manages_stock = Boolean(product.manages_stock)
    editProductForm.stock_quantity = product.stock_quantity ?? 0
    editProductForm.low_stock_threshold = product.low_stock_threshold || 0
    editProductForm.tax_class = product.tax_class || 'standard'
    editProductForm.return_policy_type = product.return_policy_type || 'standard'
    editProductForm.return_window_days = product.return_window_days ?? 14
    editProductForm.digital_delivery_note = product.digital_delivery_note || ''
    editProductForm.price_cents = centsToMajor(product.price_cents || 0)
    editProductForm.currency = product.currency || 'EUR'
    editProductForm.status = product.status || 'draft'
    editProductForm.rejection_reason = product.rejection_reason || ''
    editProductForm.commission_percent = product.commission_percent ?? 10
    editAttributeRows.value = product.attribute_options?.length
        ? product.attribute_options.map((attribute) => ({ name: attribute.name || '', values: attribute.values || [] }))
        : (product.product_attributes?.length
            ? product.product_attributes.map((attribute) => ({ name: attribute.name || '', values: String(attribute.value || '').split(/[|,]/).map((value) => value.trim()).filter(Boolean) }))
            : [{ name: '', values: [] }])
    editFeatureRows.value = product.features?.length ? [...product.features] : ['']
    editVariantRows.value = product.variants?.length
        ? product.variants.map((variant) => ({
            sku: variant.sku || '',
            price_cents: centsToMajor(variant.price_cents ?? product.price_cents ?? 0),
            stock_quantity: variant.stock_quantity ?? '',
            image_url: variant.image_url || '',
            attributes: Object.fromEntries((variant.attributes || []).map((attribute) => [attribute.name, attribute.value])),
        }))
        : []
}

const closeEditProduct = () => {
    editProductForm.open = false
    editProductForm.product = null
}

const submitEditProduct = () => {
    if (!editProductForm.product) return

    editProductForm.attributes_text = attributeRowsToText(editAttributeRows.value)
    editProductForm.features_text = featureRowsToText(editFeatureRows.value)
    editProductForm.attribute_options = normalizeAttributeRows(editAttributeRows.value)
    editProductForm.variants = normalizeVariantRows(editVariantRows.value)

    editProductForm
        .transform((data) => transformMoneyFields(data, ['price_cents']))
        .put(route('admin.commerce.products.update', editProductForm.product.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeEditProduct,
        })
}

const updateReturnRequest = (request, status, restock = false) => {
    router.put(route('admin.commerce.returns.update', request.id), {
        status,
        resolution_note: request.resolution_note || '',
        approved_amount_cents: typeof request.approved_amount_cents === 'number'
            ? request.approved_amount_cents
            : majorToCents(request.approved_amount_cents || centsToMajor(request.requested_amount_cents)),
        restock,
    }, { preserveScroll: true })
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

const resetCampaignCreativeRows = () => {
    campaignCreativeRows.value = [
        { name: 'Variante A', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
        { name: 'Variante B', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
    ]
}

const storeCampaign = () => {
    campaignForm.creatives = normalizeCampaignCreatives()
    if (!campaignForm.is_internal) {
        campaignForm.force_priority = false
    } else if (!campaignForm.budget_cents) {
        campaignForm.budget_cents = '0'
    }

    campaignForm
        .transform((data) => transformMoneyFields(data, ['budget_cents', 'daily_budget_cents', 'spent_cents']))
        .post(route('admin.commerce.campaigns.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            campaignForm.reset('is_internal', 'force_priority', 'name', 'headline', 'description', 'primary_text', 'target_url', 'creative_image_url', 'creative_image_upload', 'creatives', 'audience_locations', 'audience_interests', 'audience_excluded_locations', 'audience_excluded_interests', 'audience_devices', 'audience_languages', 'audience_hours', 'audience_age_min', 'audience_age_max', 'starts_at', 'ends_at')
            resetCampaignCreativeRows()
        },
        })
}

const markOrderPaid = (order) => {
    router.post(route('admin.commerce.orders.mark-paid', order.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            order.status = 'completed'
            order.completed_at = new Date().toISOString()
            router.reload({
                only: ['orders', 'summary', 'payoutCandidates'],
                preserveScroll: true,
                preserveState: true,
            })
        },
    })
}

const updateCampaignStatus = (campaign, status) => {
    campaignStatusError.value = ''

    if (status === 'active' && campaign.user_id && !campaign.payment_completed) {
        campaignStatusError.value = 'Diese Ads-Kampagne kann erst nach Zahlung freigegeben werden.'
        return
    }

    router.put(route('admin.commerce.campaigns.status.update', campaign.id), {
        status,
        review_note: campaign.review_note || '',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            campaignStatusError.value = ''
        },
        onError: (errors) => {
            campaignStatusError.value = errors.campaign_status || errors.status || 'Status konnte nicht aktualisiert werden.'
        },
    })
}

const updateOrderIssue = (order, issueStatus, orderStatus = null) => {
    router.put(route('admin.commerce.orders.issue', order.id), {
        issue_status: issueStatus,
        issue_note: order.issue_note || '',
        order_status: orderStatus,
    }, { preserveScroll: true })
}

const openIssueReplyModal = (order) => {
    issueReplyModal.open = true
    issueReplyModal.order = order
    issueReplyModal.issue_response = order.issue_response || ''
    issueReplyModal.issue_status = order.issue_status === 'resolved' ? 'resolved' : 'reviewing'
}

const closeIssueReplyModal = () => {
    issueReplyModal.open = false
    issueReplyModal.order = null
    issueReplyModal.issue_response = ''
    issueReplyModal.issue_status = 'reviewing'
    issueReplyModal.clearErrors()
}

const submitIssueReply = () => {
    if (!issueReplyModal.order) return

    issueReplyModal.post(route('admin.commerce.orders.issue.reply', issueReplyModal.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            issueReplyModal.order.issue_response = issueReplyModal.issue_response
            issueReplyModal.order.issue_status = issueReplyModal.issue_status
            issueReplyModal.order.issue_responded_at = new Date().toISOString()
            closeIssueReplyModal()
            router.reload({
                only: ['orders'],
                preserveScroll: true,
                preserveState: true,
            })
        },
    })
}

const openShippingModal = (order) => {
    shippingModal.open = true
    shippingModal.order = order
    shippingModal.shipping_status = order.shipping_status || 'open'
    shippingModal.shipping_carrier = order.shipping_carrier || ''
    shippingModal.shipping_label_url = order.shipping_label_url || ''
    shippingModal.tracking_number = order.tracking_number || ''
    shippingModal.tracking_url = order.tracking_url || ''
}

const submitShipping = () => {
    if (!shippingModal.order) return

    router.put(route('admin.commerce.orders.shipping', shippingModal.order.id), {
        shipping_status: shippingModal.shipping_status,
        shipping_carrier: shippingModal.shipping_carrier,
        shipping_label_url: shippingModal.shipping_label_url,
        tracking_number: shippingModal.tracking_number,
        tracking_url: shippingModal.tracking_url,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            shippingModal.order.shipping_status = shippingModal.shipping_status
            shippingModal.order.shipping_carrier = shippingModal.shipping_carrier
            shippingModal.order.shipping_label_url = shippingModal.shipping_label_url
            shippingModal.order.tracking_number = String(shippingModal.tracking_number || '').replace(/\s+/g, '').toUpperCase()
            shippingModal.order.tracking_url = shippingModal.tracking_url || trackingUrlFor(shippingModal.shipping_carrier, shippingModal.tracking_number)
            shippingModal.open = false
            shippingModal.order = null
            router.reload({
                only: ['orders', 'summary', 'payoutCandidates'],
                preserveScroll: true,
                preserveState: true,
            })
        },
    })
}

const openRefundModal = (order) => {
    refundModal.open = true
    refundModal.order = order
    refundModal.amount_cents = centsToMajor(order.amount_cents || 0)
    refundModal.reason = ''
}

const submitRefund = () => {
    if (!refundModal.order) return

    router.post(route('admin.commerce.orders.refund', refundModal.order.id), {
        amount_cents: majorToCents(refundModal.amount_cents),
        reason: refundModal.reason,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            refundModal.open = false
            refundModal.order = null
        },
    })
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
            <div v-if="page.props.errors?.campaign_status" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                {{ page.props.errors.campaign_status }}
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

        <nav class="sticky top-0 z-30 overflow-x-auto rounded-lg border border-border bg-card p-2 shadow-lg">
            <div class="flex min-w-max gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :class="[
                        'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition',
                        activeTab === tab.key
                            ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm'
                            : 'text-secondary hover:bg-muted hover:text-primary'
                    ]"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-xs',
                            activeTab === tab.key ? 'bg-white/20 text-buttonTextPrimary' : 'bg-muted text-secondary'
                        ]"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>
        </nav>

        <section v-if="page.props.flash?.success || page.props.errors?.campaign_status" class="surface-card px-5 py-4">
            <div v-if="page.props.flash?.success" class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.errors?.campaign_status" class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">
                {{ page.props.errors.campaign_status }}
            </div>
        </section>

        <section v-if="activeTab === 'settings'" class="grid gap-6 xl:grid-cols-3">
            <article class="surface-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Export</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">Steuerberater / DATEV-CSV</h2>
                        <p class="mt-1 text-sm text-secondary">Bestellungen, Steuerland, Rechnungsnummern, Versandstatus und Beträge als CSV.</p>
                    </div>
                    <a :href="route('admin.commerce.export.csv')" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">CSV</a>
                </div>
            </article>

            <article class="surface-card p-5 xl:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">OSS</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">EU-Auswertung nach Land</h2>
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    <div v-for="row in ossReport" :key="row.country" class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">{{ row.country }} · {{ row.orders_count }} Orders</p>
                        <p class="mt-1 font-semibold text-primary">{{ formatMoney(row.gross_cents) }}</p>
                        <p class="text-xs text-secondary">Steuer {{ formatMoney(row.tax_cents) }}</p>
                    </div>
                    <p v-if="!ossReport.length" class="text-sm text-secondary">Noch keine OSS-Daten.</p>
                </div>
            </article>
        </section>

        <section v-if="activeTab === 'settings'" class="grid gap-6 xl:grid-cols-2">
            <article class="surface-card p-5 xl:col-span-2">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">EU-Konformität</p>
                    <h2 class="text-lg font-semibold text-primary">Commerce-Steuerlogik</h2>
                    <p class="text-sm text-secondary">Diese Einstellungen steuern Firmenland, OSS-Verhalten, Export und Reverse-Charge.</p>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-5" @submit.prevent="updateCommerceSettings">
                    <input v-model="commerceSettingsForm.company_country" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Firmensitz, z. B. DE">
                    <input v-model="commerceSettingsForm.company_currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                    <select v-model="commerceSettingsForm.export_vat_mode" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="zero">Export außerhalb EU: 0%</option>
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

            <article class="surface-card p-5 xl:col-span-2">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Marketplace</p>
                    <h2 class="text-lg font-semibold text-primary">Provisionen für externe Verkäufer</h2>
                    <p class="text-sm text-secondary">Diese Sätze gelten nur für externe Shop-Verkäufer. Interne Airmius-Angebote und interne Services laufen ohne Marketplace-Provision.</p>
                </div>
                <form class="mt-4 space-y-4" @submit.prevent="updateMarketplaceCommissions">
                    <label class="grid gap-2 rounded-lg border border-border bg-card p-4 md:grid-cols-[1fr_8rem] md:items-center">
                        <span>
                            <span class="block text-sm font-semibold text-primary">Standard-Provision</span>
                            <span class="block text-xs text-secondary">Greift nur, wenn für eine neue Kategorie noch kein eigener Satz hinterlegt ist.</span>
                        </span>
                        <span class="flex items-center gap-2">
                            <input v-model="marketplaceCommissionForm.default_commission_percent" type="number" min="0" max="100" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <span class="text-sm text-secondary">%</span>
                        </span>
                    </label>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            v-for="(row, index) in marketplaceCommissionForm.commissions"
                            :key="row.category"
                            class="rounded-lg border border-border bg-card p-4"
                        >
                            <span class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                                <input v-model="marketplaceCommissionForm.commissions[index].label" class="rounded-lg border-border bg-inputBg text-sm font-semibold text-primary" placeholder="Anzeigename, z. B. Fußballschuhe">
                                <input v-model="marketplaceCommissionForm.commissions[index].category" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="slug, z. B. football_shoes">
                                <button type="button" class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger" @click="removeMarketplaceCommissionRow(index)">
                                    Entfernen
                                </button>
                            </span>
                            <span class="mt-3 flex items-center gap-2">
                                <input v-model="marketplaceCommissionForm.commissions[index].commission_percent" type="number" min="0" max="100" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <span class="text-sm text-secondary">%</span>
                            </span>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="addMarketplaceCommissionRow">
                            Kategorie hinzufügen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="marketplaceCommissionForm.processing">
                            Provisionen speichern
                        </button>
                    </div>
                </form>
            </article>

            <article class="surface-card p-5">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Checkout</p>
                    <h2 class="text-lg font-semibold text-primary">Steuern verwalten</h2>
                    <p class="text-sm text-secondary">Der Checkout wählt den passenden Satz über Lieferland und optional Region.</p>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeTaxRate">
                    <input v-model="taxRateForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <input v-model="taxRateForm.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="DE">
                    <input v-model="taxRateForm.region" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Region optional">
                    <select v-model="taxRateForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="standard">Standard</option>
                        <option value="reduced">Ermäßigt</option>
                        <option value="zero">Nullsatz</option>
                    </select>
                    <input v-model="taxRateForm.tax_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="MwSt.">
                    <input v-model="taxRateForm.rate_percent" type="number" min="0" max="99.99" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="19">
                    <input v-model="taxRateForm.currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                    <input v-model="taxRateForm.priority" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Priorität">
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
                            <option value="reduced">Ermäßigt</option>
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
                    <p v-if="!taxRates.length" class="text-sm text-secondary">Noch keine Steuersätze angelegt.</p>
                </div>
            </article>

            <article class="surface-card p-5">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Checkout</p>
                    <h2 class="text-lg font-semibold text-primary">Versandkosten verwalten</h2>
                    <p class="text-sm text-secondary">Regeln können nach Ursprungslager, Lieferland und PLZ-Prefix greifen, inklusive kostenfrei ab Warenwert.</p>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeShippingRate">
                    <input v-model="shippingRateForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <input v-model="shippingRateForm.origin_country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Von Land, z. B. DE">
                    <input v-model="shippingRateForm.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="DE oder leer">
                    <input v-model="shippingRateForm.postal_code_prefix" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ-Prefix optional">
                    <input v-model="shippingRateForm.amount_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Versand in EUR">
                    <input v-model="shippingRateForm.free_from_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kostenfrei ab EUR">
                    <input v-model="shippingRateForm.currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                    <input v-model="shippingRateForm.priority" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Priorität">
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="shippingRateForm.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                        Aktiv
                    </label>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Versandregel speichern</button>
                </form>

                <div class="mt-5 space-y-3">
                    <div v-for="rate in shippingRates" :key="rate.id" class="grid gap-2 rounded-lg border border-border bg-card p-3 md:grid-cols-[1fr_5rem_5rem_6rem_6rem_6rem_auto] md:items-center">
                        <input v-model="rate.name" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="rate.origin_country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Von">
                        <input v-model="rate.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Alle">
                        <input v-model="rate.postal_code_prefix" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ">
                        <input :value="typeof rate.amount_cents === 'string' ? rate.amount_cents : centsToMajor(rate.amount_cents)" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" @input="rate.amount_cents = $event.target.value">
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

        <section v-if="activeTab === 'ad-prices'" class="grid gap-6 xl:grid-cols-3">
            <article class="surface-card p-5 xl:col-span-2">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Ads Abrechnung</p>
                    <h2 class="text-lg font-semibold text-primary">Kosten und Preise verwalten</h2>
                    <p class="text-sm text-secondary">Diese Werte steuern, wie Kampagnenbudget für Impressionen, Klicks, Leads und Sales verbraucht wird.</p>
                </div>

                <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="updateCommerceSettings">
                    <label class="rounded-lg border border-border bg-card p-4">
                        <span class="text-xs font-semibold uppercase tracking-wide text-secondary">CPM</span>
                        <span class="mt-1 block text-sm font-semibold text-primary">Preis pro 1.000 Impressionen</span>
                        <div class="mt-3 flex items-center gap-2">
                            <input v-model="commerceSettingsForm.ads_cpm_cents" v-bind="moneyInputAttrs" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="5,00">
                            <span class="shrink-0 text-sm text-secondary">EUR</span>
                        </div>
                        <span class="mt-2 block text-xs text-secondary">{{ commerceSettingsForm.ads_cpm_cents || '0,00' }} EUR pro 1.000 Views</span>
                    </label>

                    <label class="rounded-lg border border-border bg-card p-4">
                        <span class="text-xs font-semibold uppercase tracking-wide text-secondary">CPC</span>
                        <span class="mt-1 block text-sm font-semibold text-primary">Preis pro Klick</span>
                        <div class="mt-3 flex items-center gap-2">
                            <input v-model="commerceSettingsForm.ads_cpc_cents" v-bind="moneyInputAttrs" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="0,30">
                            <span class="shrink-0 text-sm text-secondary">EUR</span>
                        </div>
                        <span class="mt-2 block text-xs text-secondary">{{ commerceSettingsForm.ads_cpc_cents || '0,00' }} EUR pro Klick</span>
                    </label>

                    <label class="rounded-lg border border-border bg-card p-4">
                        <span class="text-xs font-semibold uppercase tracking-wide text-secondary">CPL</span>
                        <span class="mt-1 block text-sm font-semibold text-primary">Preis pro Lead</span>
                        <div class="mt-3 flex items-center gap-2">
                            <input v-model="commerceSettingsForm.ads_cpl_cents" v-bind="moneyInputAttrs" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="2,00">
                            <span class="shrink-0 text-sm text-secondary">EUR</span>
                        </div>
                        <span class="mt-2 block text-xs text-secondary">{{ commerceSettingsForm.ads_cpl_cents || '0,00' }} EUR pro Lead</span>
                    </label>

                    <label class="rounded-lg border border-border bg-card p-4">
                        <span class="text-xs font-semibold uppercase tracking-wide text-secondary">CPA</span>
                        <span class="mt-1 block text-sm font-semibold text-primary">Provision pro Verkauf</span>
                        <div class="mt-3 flex items-center gap-2">
                            <input v-model="commerceSettingsForm.ads_cpa_percent" type="number" min="0" max="100" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="10">
                            <span class="shrink-0 text-sm text-secondary">%</span>
                        </div>
                        <span class="mt-2 block text-xs text-secondary">{{ commerceSettingsForm.ads_cpa_percent || 0 }} % vom Warenwert</span>
                    </label>

                    <label class="rounded-lg border border-border bg-card p-4 md:col-span-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-secondary">Mindestbudget</span>
                        <span class="mt-1 block text-sm font-semibold text-primary">Kleinstes Kampagnenbudget für Nutzer</span>
                        <div class="mt-3 flex items-center gap-2">
                            <input v-model="commerceSettingsForm.ads_min_budget_cents" v-bind="moneyInputAttrs" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="10,00">
                            <span class="shrink-0 text-sm text-secondary">EUR</span>
                        </div>
                        <span class="mt-2 block text-xs text-secondary">Aktuell: {{ formatMoney(majorToCents(commerceSettingsForm.ads_min_budget_cents)) }}</span>
                    </label>

                    <label class="rounded-lg border border-border bg-card p-4 md:col-span-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-secondary">Frequency Capping</span>
                        <span class="mt-1 block text-sm font-semibold text-primary">Max. Impressionen pro Kampagne und Tag</span>
                        <div class="mt-3 flex items-center gap-2">
                            <input v-model="commerceSettingsForm.ads_frequency_cap_per_day" type="number" min="0" max="100" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="3">
                            <span class="shrink-0 text-sm text-secondary">pro User/Session</span>
                        </div>
                        <span class="mt-2 block text-xs text-secondary">0 deaktiviert das Cap. Gilt auch für interne priorisierte Ads.</span>
                    </label>

                    <div class="rounded-lg border border-border bg-card p-4 md:col-span-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-secondary">Placement Caps</span>
                        <span class="mt-1 block text-sm font-semibold text-primary">Unterschiedliche Limits je Flaeche</span>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="text-xs font-semibold uppercase text-secondary">
                                Feed
                                <input v-model="commerceSettingsForm.ads_frequency_cap_feed" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            </label>
                            <label class="text-xs font-semibold uppercase text-secondary">
                                Sidebar
                                <input v-model="commerceSettingsForm.ads_frequency_cap_sidebar" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            </label>
                            <label class="text-xs font-semibold uppercase text-secondary">
                                Marketplace Karte
                                <input v-model="commerceSettingsForm.ads_frequency_cap_marketplace_card" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            </label>
                            <label class="text-xs font-semibold uppercase text-secondary">
                                Sponsor-Bereich
                                <input v-model="commerceSettingsForm.ads_frequency_cap_sponsor_section" type="number" min="0" max="100" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            </label>
                        </div>
                    </div>

                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary md:col-span-2" :disabled="commerceSettingsForm.processing">
                        Ads-Preise speichern
                    </button>
                </form>
            </article>

            <aside class="surface-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Kontrolle</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Berechnungsvorschau</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="price in adPricingCards" :key="price.key" class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-primary">{{ price.label }} - {{ price.title }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ price.formula }}</p>
                            </div>
                            <span class="shrink-0 rounded-md border border-border px-2 py-1 text-xs font-semibold text-primary">{{ price.value || 0 }} {{ price.suffix }}</span>
                        </div>
                        <p class="mt-2 text-xs text-air-blue">{{ price.example }}</p>
                    </div>
                </div>
            </aside>
        </section>

        <section v-if="activeTab === 'marketing'" class="grid gap-6 xl:grid-cols-2">
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
                    <input v-else v-model="couponForm.value_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Betrag in EUR">
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
                    <input v-model="addonForm.monthly_price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Monat in EUR">
                    <input v-model="addonForm.yearly_price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Jahr in EUR">
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

        <section v-if="activeTab === 'marketplace'" class="grid gap-6">
            <article class="surface-card overflow-hidden">
                <div class="border-b border-border p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Shop-Zugang</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Verkäufer-Anträge</h2>
                    <p class="mt-1 text-sm text-secondary">Erst freigegebene Nutzer können eigene Marketplace-Produkte erstellen.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <tbody class="divide-y divide-border">
                            <tr v-for="application in sellerApplications" :key="application.id">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-primary">{{ application.user?.name || application.user?.email }}</p>
                                    <p class="text-xs text-secondary">{{ application.user?.email || '-' }} &middot; {{ application.business_name || application.applicant_type }}</p>
                                </td>
                                <td class="px-5 py-3 text-secondary">
                                    <p class="font-semibold text-primary">{{ sellerApplicationStatusLabel(application.status) }}</p>
                                    <p class="text-xs text-secondary">Beantragt: {{ formatDateTime(application.created_at) }}</p>
                                    <p v-if="application.review_note" class="mt-1 text-xs text-warning">{{ application.review_note }}</p>
                                </td>
                                <td class="px-5 py-3 text-secondary">
                                    <p v-if="application.notes">{{ application.notes }}</p>
                                    <p v-else>-</p>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button
                                            v-if="application.status !== 'approved'"
                                            type="button"
                                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                                            @click="updateSellerApplication(application, 'approved')"
                                        >
                                            Freigeben
                                        </button>
                                        <button
                                            v-if="application.status !== 'rejected'"
                                            type="button"
                                            class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning"
                                            @click="updateSellerApplication(application, 'rejected')"
                                        >
                                            Ablehnen
                                        </button>
                                        <button
                                            v-if="application.status !== 'pending'"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="updateSellerApplication(application, 'pending')"
                                        >
                                            Zurück auf Prüfung
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!sellerApplications.length" class="px-5 py-6 text-sm text-secondary">Noch keine Shop-Anträge.</p>
                </div>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Marketplace-Produkt</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeProduct">
                    <input v-model="productForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                    <input v-model="productForm.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR, z. B. 10,99">
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
                    <div class="md:col-span-2 grid gap-3 rounded-lg border border-border bg-bg p-3 md:grid-cols-2">
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
                    <select v-model="productForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="standard">Standardsteuer</option>
                        <option value="reduced">Ermäßigt</option>
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
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Merkmale / Variantenoptionen</h3>
                                <p class="text-xs text-secondary">Ein Merkmal pro Zeile. Werte mit Komma oder | trennen, z. B. Rot | Blau | Schwarz.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addProductAttributeRow">
                                Merkmal hinzufügen
                            </button>
                        </div>
                        <div class="mt-3 space-y-2">
                            <div v-for="(row, index) in productAttributeRows" :key="index" class="grid gap-2 md:grid-cols-[11rem_minmax(0,1fr)_auto]">
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
                    <div v-if="productForm.product_type === 'variable'" class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Varianten</h3>
                                <p class="text-xs text-secondary">Jede Variante kann eigene Merkmale, Preis, Bestand und Bild haben.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addProductVariantRow">
                                Variante hinzufügen
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(variant, index) in productVariantRows" :key="index" class="rounded-lg border border-border p-3">
                                <div class="grid gap-2 md:grid-cols-4">
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
                                    <input v-model="variant.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" placeholder="Bild-URL für Variante">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="removeProductVariantRow(index)">Variante entfernen</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Produkt-Highlights</h3>
                                <p class="text-xs text-secondary">Kurze Bulletpoints, die auf der Produktseite als Merkmale erscheinen.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addProductFeatureRow">
                                Punkt hinzufügen
                            </button>
                        </div>
                        <div class="mt-3 space-y-2">
                            <div v-for="(feature, index) in productFeatureRows" :key="index" class="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto]">
                                <input v-model="productFeatureRows[index]" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Atmungsaktiv">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="removeProductFeatureRow(index)">
                                    Entfernen
                                </button>
                            </div>
                        </div>
                    </div>
                    <textarea v-model="productForm.description" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                </form>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <div v-for="warehouse in warehouses" :key="warehouse.id" class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">{{ warehouse.name }}</p>
                                <p class="text-xs text-secondary">{{ warehouse.country_code }} · {{ warehouse.city || 'Ohne Stadt' }}</p>
                            </div>
                            <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">
                                {{ warehouse.active_inventories_count || 0 }} aktiv
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-secondary">Inventories gesamt: {{ warehouse.inventories_count || 0 }}</p>
                    </div>
                </div>
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
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                    <span class="text-secondary">{{ product.status }} · {{ product.moderation_status }} · {{ product.product_type || 'single' }} · {{ formatMoney(product.price_cents) }}</span>
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 font-semibold',
                                            product.quality_score >= 86 ? 'bg-success/10 text-success' : (product.quality_score >= 72 ? 'bg-warning/10 text-warning' : 'bg-error/10 text-error')
                                        ]"
                                    >
                                        Qualität {{ product.quality_score ?? 0 }}%
                                    </span>
                                </div>
                                <p v-if="product.seller_name" class="mt-1 text-xs text-secondary">
                                    Anbieter {{ product.seller_name }}
                                    <span v-if="product.seller_verified" class="font-semibold text-success">· verifiziert</span>
                                </p>
                                <p class="text-xs text-secondary">
                                    Artikelnummer {{ product.sku || '-' }} · Steuer {{ product.tax_class || 'standard' }} ·
                                    <span v-if="product.manages_stock">Bestand {{ product.stock_quantity ?? 0 }}</span>
                                    <span v-else>Bestand nicht verwaltet</span>
                                </p>
                                <p v-if="product.features?.length" class="mt-1 line-clamp-1 text-xs text-secondary">
                                    Merkmale: {{ product.features.join(' | ') }}
                                </p>
                                <p v-if="product.product_attributes?.length" class="mt-1 line-clamp-1 text-xs text-secondary">
                                    Eigenschaften: {{ product.product_attributes.map((attribute) => `${attribute.name}: ${attribute.value}`).join(' | ') }}
                                </p>
                                <p v-if="product.variants?.length" class="mt-1 line-clamp-1 text-xs text-secondary">
                                    Varianten: {{ product.variants.length }}
                                </p>
                                <div v-if="product.quality_issues?.length" class="mt-2 flex flex-wrap gap-1">
                                    <span
                                        v-for="issue in product.quality_issues"
                                        :key="`${product.id}-${issue}`"
                                        class="rounded bg-warning/10 px-2 py-1 text-[11px] font-semibold text-warning"
                                    >
                                        {{ issue }}
                                    </span>
                                </div>
                                <div v-if="product.inventories?.length" class="mt-3 grid gap-2">
                                    <div
                                        v-for="inventory in product.inventories.filter((row) => row.is_active)"
                                        :key="inventory.id"
                                        class="grid gap-2 rounded-lg border border-border bg-card p-2 lg:grid-cols-[minmax(0,1fr)_5rem_5rem_7rem_7rem_auto]"
                                    >
                                        <div>
                                            <p class="text-xs font-semibold text-primary">{{ inventory.country_code }} · {{ inventory.warehouse?.name || 'Lager' }}</p>
                                            <p class="text-[11px] text-secondary">{{ inventory.warehouse?.city || 'Ort offen' }} · Lieferzeit {{ inventory.lead_time_days ?? '-' }} Tage</p>
                                        </div>
                                        <span class="rounded bg-muted px-2 py-2 text-xs font-semibold text-secondary">Bestand {{ inventory.stock_quantity }}</span>
                                        <span class="rounded bg-muted px-2 py-2 text-xs font-semibold text-secondary">Frei {{ inventory.available_quantity }}</span>
                                        <input
                                            :value="inventoryAdjustment(product, inventory).quantity_delta"
                                            type="number"
                                            class="rounded-lg border-border bg-inputBg text-xs text-primary"
                                            placeholder="+/-"
                                            @input="setInventoryAdjustment(product, inventory, 'quantity_delta', $event.target.value)"
                                        >
                                        <input
                                            :value="inventoryAdjustment(product, inventory).note"
                                            class="rounded-lg border-border bg-inputBg text-xs text-primary"
                                            placeholder="Notiz"
                                            @input="setInventoryAdjustment(product, inventory, 'note', $event.target.value)"
                                        >
                                        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="adjustInventoryStock(product, inventory)">
                                            Buchen
                                        </button>
                                    </div>
                                </div>
                                <p v-if="product.rejection_reason" class="mt-1 text-xs text-warning">{{ product.rejection_reason }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <div v-if="product.manages_stock" class="flex items-center gap-2">
                                    <input v-model.number="product.stock_quantity" type="number" min="0" class="w-24 rounded-lg border-border bg-inputBg text-xs text-primary" placeholder="Bestand">
                                    <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateProductStock(product)">Bestand speichern</button>
                                    <input
                                        :value="(stockAdjustments[`${product.id}:global`] || {}).quantity_delta"
                                        type="number"
                                        class="w-20 rounded-lg border-border bg-inputBg text-xs text-primary"
                                        placeholder="+/-"
                                        @input="stockAdjustments[`${product.id}:global`] = { ...(stockAdjustments[`${product.id}:global`] || {}), quantity_delta: $event.target.value }"
                                    >
                                    <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="adjustGlobalStock(product)">
                                        Global buchen
                                    </button>
                                </div>
                                <select v-model="product.status" class="rounded-lg border-border bg-inputBg text-xs font-semibold text-primary" @change="updateProductStatus(product, product.status)">
                                    <option value="draft">Entwurf</option>
                                    <option value="review">Prüfen</option>
                                    <option value="published">Freigegeben</option>
                                    <option value="archived">Archiviert</option>
                                </select>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="openEditProduct(product)">Bearbeiten</button>
                                <button type="button" class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="updateProductStatus(product, 'rejected')">Ablehnen</button>
                                <button type="button" class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger hover:bg-danger/10" @click="openDeleteProduct(product)">Löschen</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!products.length" class="text-sm text-secondary">Noch keine Produkte vorbereitet.</p>
                </div>
            </article>
        </section>

        <section v-if="activeTab === 'ads'" class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">Ads-Kampagne erstellen</h2>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeCampaign">
                    <div class="md:col-span-2 grid gap-3 rounded-lg border border-air-blue/30 bg-air-blue/10 p-3 sm:grid-cols-2">
                        <label class="flex items-start gap-3 text-sm text-primary">
                            <input v-model="campaignForm.is_internal" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                            <span>
                                <span class="block font-semibold">Interne Airmius Ad</span>
                                <span class="text-xs text-secondary">Nur Admins sehen diese Steuerung. Die Anzeige selbst wird normal ausgespielt.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 text-sm text-primary" :class="{ 'opacity-50': !campaignForm.is_internal }">
                            <input v-model="campaignForm.force_priority" type="checkbox" class="mt-1 rounded border-border bg-inputBg" :disabled="!campaignForm.is_internal">
                            <span>
                                <span class="block font-semibold">Immer priorisieren</span>
                                <span class="text-xs text-secondary">Wird vor bezahlten Ads gewählt, solange aktiv und im Zeitraum.</span>
                            </span>
                        </label>
                    </div>
                    <input v-model="campaignForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                    <input v-model="campaignForm.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Headline">
                    <input v-model="campaignForm.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL">
                    <input v-model="campaignForm.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA">
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
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">Bildformat</label>
                        <select v-model="campaignForm.creative_format" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="format in adFormats" :key="format.key" :value="format.key">{{ format.label }} - {{ format.size }}</option>
                        </select>
                        <p class="mt-2 text-xs text-secondary">Empfohlene Bildmaße: {{ selectedAdFormat.size }}</p>
                    </div>
                    <input v-model="campaignForm.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL">
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setCampaignCreativeUpload">
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">A/B-Test Varianten</label>
                                <p class="mt-1 text-xs text-secondary">Lege mehrere Anzeigenvarianten mit eigener Headline, Text, Bild-URL und Gewichtung an.</p>
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
                    <input v-model="campaignForm.budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gesamtbudget in EUR">
                    <input v-model="campaignForm.daily_budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tagesbudget in EUR">
                    <select v-model="campaignForm.status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="draft">Entwurf</option>
                        <option value="pending_payment">Wartet auf Zahlung</option>
                        <option value="pending_review">Wartet auf Freigabe</option>
                        <option value="active">Aktiv</option>
                        <option value="paused">Pausiert</option>
                        <option value="completed">Abgeschlossen</option>
                        <option value="rejected">Abgelehnt</option>
                    </select>
                    <input v-model="campaignForm.clicks" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Klicks">
                    <input v-model="campaignForm.audience_locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Regionen">
                    <input v-model="campaignForm.audience_interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interessen">
                    <input v-model="campaignForm.audience_excluded_locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Regionen ausschließen">
                    <input v-model="campaignForm.audience_excluded_interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interessen ausschließen">
                    <input v-model="campaignForm.audience_devices" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Geräte: desktop, mobile, tablet">
                    <input v-model="campaignForm.audience_languages" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Sprachen: de, en, fr">
                    <input v-model="campaignForm.audience_hours" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Zeitfenster: 08-22, 18:30-23:00">
                    <input v-model="campaignForm.audience_age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von">
                    <input v-model="campaignForm.audience_age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis">
                    <textarea v-model="campaignForm.primary_text" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anzeigentext"></textarea>
                    <textarea v-model="campaignForm.description" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                    <textarea v-model="campaignForm.review_note" rows="2" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Review-Notiz / Ablehnungsgrund"></textarea>
                    <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
            </form>
            <p class="mt-4 text-sm text-secondary">{{ campaigns.length }} Kampagnen vorbereitet.</p>
        </section>

        <section v-if="activeTab === 'marketplace'" class="surface-card p-5">
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
                    <div
                        class="overflow-hidden rounded-lg border border-border bg-inputBg"
                        :class="visual.key === 'side_banner' ? 'flex h-80 items-center justify-center' : ''"
                    >
                        <img
                            v-if="visual.url"
                            :src="visual.url"
                            :alt="visual.label"
                            :class="visual.key === 'side_banner' ? 'h-full w-auto object-cover' : 'aspect-video w-full object-cover'"
                        />
                        <div
                            v-else
                            class="flex items-center justify-center text-secondary"
                            :class="visual.key === 'side_banner' ? 'h-full w-16' : 'aspect-video w-full'"
                        >
                            <i class="las la-image text-4xl"></i>
                        </div>
                    </div>

                    <h3 class="mt-3 font-semibold text-primary">{{ visual.label }}</h3>
                    <p class="mt-1 text-xs leading-5 text-secondary">{{ visual.description }}</p>
                    <p class="mt-2 text-xs font-semibold text-primary">Empfohlen: {{ visual.recommended_size }}</p>
                    <div v-if="visual.key === 'side_banner'" class="mt-3 rounded border border-air-blue/30 bg-air-blue/10 p-3 text-xs leading-5 text-secondary">
                        <p class="font-semibold text-primary">Seitenbanner-Regel</p>
                        <p>Kein Text, keine Logos, keine Gesichter und keine wichtigen Details am Rand. Das Bild wird gespiegelt und nur dezent als Hintergrund genutzt.</p>
                    </div>
                    <p class="mt-1 text-xs text-secondary">Aktuelle Zielgröße: {{ marketplaceVisualForm.dimensions[visual.key]?.width }} x {{ marketplaceVisualForm.dimensions[visual.key]?.height }} px</p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <label class="block text-xs font-semibold uppercase text-secondary">
                            Breite px
                            <input
                                v-model="marketplaceVisualForm.dimensions[visual.key].width"
                                type="number"
                                min="120"
                                max="3840"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                            />
                        </label>
                        <label class="block text-xs font-semibold uppercase text-secondary">
                            Höhe px
                            <input
                                v-model="marketplaceVisualForm.dimensions[visual.key].height"
                                type="number"
                                min="120"
                                max="3840"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                            />
                        </label>
                    </div>

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

        <section v-if="activeTab === 'ads'" class="surface-card overflow-hidden">
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

            <div v-if="adPlacementReport.length" class="border-b border-border p-5">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Placement Performance</p>
                    <h3 class="font-semibold text-primary">Welche Flächen Ergebnisse liefern</h3>
                </div>
                <div class="mt-3 grid gap-3 lg:grid-cols-4">
                    <article v-for="row in adPlacementReport" :key="row.placement" class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-sm font-semibold text-primary">{{ row.placement }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-secondary">
                            <span>Views</span>
                            <strong class="text-right text-primary">{{ row.impressions }}</strong>
                            <span>Klicks</span>
                            <strong class="text-right text-primary">{{ row.clicks }}</strong>
                            <span>Leads</span>
                            <strong class="text-right text-primary">{{ row.leads }}</strong>
                            <span>Sales</span>
                            <strong class="text-right text-primary">{{ row.sales }}</strong>
                            <span>CTR</span>
                            <strong class="text-right text-primary">{{ row.ctr }}%</strong>
                            <span>Kosten</span>
                            <strong class="text-right text-primary">{{ formatMoney(row.cost_cents) }}</strong>
                        </div>
                    </article>
                </div>
            </div>

            <div v-if="adDiagnostics.length" class="border-b border-border p-5">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Admin-Diagnose</p>
                    <h3 class="font-semibold text-primary">Warum Ads ausgespielt oder gebremst werden</h3>
                </div>
                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <article v-for="diagnostic in adDiagnostics.slice(0, 8)" :key="diagnostic.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-semibold text-primary">{{ diagnostic.name }}</p>
                                <p class="text-xs text-secondary">{{ diagnostic.placement }} · {{ diagnostic.status }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span v-if="diagnostic.is_internal" class="rounded-full border border-air-blue/40 bg-air-blue/10 px-2 py-0.5 text-[11px] font-semibold text-air-blue">Intern</span>
                                <span v-if="diagnostic.force_priority" class="rounded-full border border-warning/40 bg-warning/10 px-2 py-0.5 text-[11px] font-semibold text-warning">Priorisiert</span>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span v-for="reason in diagnostic.reasons" :key="reason" class="rounded-full bg-card px-2 py-1 text-secondary">
                                {{ reason }}
                            </span>
                        </div>
                        <p v-if="diagnostic.daily_budget_cents" class="mt-3 text-xs text-secondary">
                            Heute: {{ formatMoney(diagnostic.today_spent_cents) }} / {{ formatMoney(diagnostic.daily_budget_cents) }}
                            <span v-if="diagnostic.allowed_spend_cents"> · Pacing erlaubt ca. {{ formatMoney(diagnostic.allowed_spend_cents) }}</span>
                        </p>
                    </article>
                </div>
            </div>

            <div
                v-if="campaignStatusError || page.props.errors?.campaign_status"
                class="mx-5 mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"
            >
                {{ campaignStatusError || page.props.errors.campaign_status }}
            </div>

            <div v-if="false && reportedOrders.length" class="border-b border-border bg-warning/10 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-warning">Gemeldete Probleme</p>
                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <article v-for="order in reportedOrders" :key="`reported-${order.id}`" class="rounded-lg border border-warning/30 bg-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">Bestellung #{{ order.id }} · {{ order.orderable?.name || order.orderable?.title || order.type }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ order.user?.email || 'Gastbestellung' }} · {{ formatMoney(order.amount_cents) }}</p>
                                <p v-if="order.issue_note" class="mt-2 text-sm text-primary">{{ order.issue_note }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-warning/15 px-2 py-1 text-xs font-semibold text-warning">{{ orderIssueLabel(order.issue_status) }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button v-if="order.issue_status === 'reported'" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateOrderIssue(order, 'reviewing')">Prüfen</button>
                            <button class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="updateOrderIssue(order, 'resolved')">Gelöst</button>
                            <button class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="updateOrderIssue(order, 'refunded', 'refunded')">Erstattet</button>
                        </div>
                    </article>
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
                        <template v-for="campaign in campaigns" :key="campaign.id">
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-primary">{{ campaign.name }}</p>
                                        <span v-if="campaign.is_internal" class="rounded-full border border-air-blue/40 bg-air-blue/10 px-2 py-0.5 text-[11px] font-semibold text-air-blue">Intern</span>
                                        <span v-if="campaign.force_priority" class="rounded-full border border-warning/40 bg-warning/10 px-2 py-0.5 text-[11px] font-semibold text-warning">Priorisiert</span>
                                    </div>
                                    <p class="text-xs text-secondary">{{ campaign.target_url || '-' }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-secondary">{{ campaign.status }}</p>
                                    <p v-if="campaign.is_internal" class="mt-1 text-xs font-semibold text-air-blue">
                                        Airmius intern
                                    </p>
                                    <p v-else-if="campaign.user_id && !campaign.payment_completed" class="mt-1 text-xs font-semibold text-warning">
                                        {{ campaign.payment_pending ? 'Zahlung offen' : 'Keine Zahlung gefunden' }}
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-secondary">{{ campaign.impressions || 0 }}</td>
                                <td class="px-5 py-3 text-secondary">{{ campaign.clicks || 0 }}</td>
                                <td class="px-5 py-3 text-secondary">{{ formatPercent(ctr(campaign.clicks, campaign.impressions)) }}</td>
                                <td class="px-5 py-3">
                                    <p class="text-secondary">{{ formatMoney(campaign.spent_cents) }} / {{ formatMoney(campaign.budget_cents) }}</p>
                                    <div class="mt-2 h-2 rounded-full bg-muted">
                                        <div class="h-2 rounded-full bg-air-blue" :style="{ width: `${budgetUsage(campaign.spent_cents, campaign.budget_cents)}%` }"></div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <p v-if="campaign.user_id && !campaign.payment_completed" class="w-full text-xs text-warning">
                                            Erst nach Zahlung freigeben.
                                        </p>
                                        <button
                                            v-if="['pending_review', 'paused'].includes(campaign.status)"
                                            type="button"
                                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                                            @click="updateCampaignStatus(campaign, 'active')"
                                        >
                                            Freigeben
                                        </button>
                                        <button
                                            v-if="campaign.status === 'active'"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="updateCampaignStatus(campaign, 'paused')"
                                        >
                                            Pausieren
                                        </button>
                                        <button
                                            v-if="['draft', 'pending_payment', 'pending_review', 'paused', 'active'].includes(campaign.status)"
                                            type="button"
                                            class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger"
                                            @click="updateCampaignStatus(campaign, 'rejected')"
                                        >
                                            Ablehnen
                                        </button>
                                        <button
                                            v-if="campaign.status === 'rejected'"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="updateCampaignStatus(campaign, 'pending_review')"
                                        >
                                            Zur Prüfung
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
                <p v-if="!campaigns.length" class="px-5 py-6 text-sm text-secondary">Noch keine Ads-Kampagnen.</p>
            </div>
        </section>

        <section v-if="activeTab === 'payouts'" class="surface-card overflow-hidden">
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

        <section v-if="activeTab === 'orders'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">After Sales</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Rücksendungen und Erstattungen</h2>
                <p class="mt-1 text-sm text-secondary">Anfragen prüfen, Ware als erhalten markieren, Bestand wieder einbuchen und Erstattung dokumentieren.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="request in returnRequests" :key="request.id">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ request.item?.title || request.order?.orderable?.title || `Rücksendung #${request.id}` }}</p>
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
                <p v-if="!returnRequests.length" class="px-5 py-6 text-sm text-secondary">Noch keine Rücksendungen.</p>
            </div>
        </section>

        <section v-if="activeTab === 'orders'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Commerce-Bestellungen</h2>
                <div v-if="reportedOrders.length" class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-warning">Gemeldete Probleme</p>
                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        <article v-for="order in reportedOrders" :key="`reported-${order.id}`" class="rounded-lg border border-warning/30 bg-card p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-primary">Bestellung #{{ order.id }} · {{ order.orderable?.name || order.orderable?.title || order.type }}</p>
                                    <p class="mt-1 text-xs text-secondary">{{ order.user?.email || 'Gastbestellung' }} · {{ formatMoney(order.amount_cents) }}</p>
                                    <p v-if="order.issue_note" class="mt-2 text-sm text-primary">{{ order.issue_note }}</p>
                                    <div v-if="order.issue_response" class="mt-3 rounded-lg border border-border bg-bg p-3 text-sm">
                                        <p class="text-xs font-semibold uppercase text-secondary">Antwort</p>
                                        <p class="mt-1 text-primary">{{ order.issue_response }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full bg-warning/15 px-2 py-1 text-xs font-semibold text-warning">{{ orderIssueLabel(order.issue_status) }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="openIssueReplyModal(order)">Antworten</button>
                                <button v-if="order.issue_status === 'reported'" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="updateOrderIssue(order, 'reviewing')">Prüfen</button>
                                <button class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="updateOrderIssue(order, 'resolved')">Gelöst</button>
                                <button class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="updateOrderIssue(order, 'refunded', 'refunded')">Erstattet</button>
                            </div>
                        </article>
                    </div>
                </div>
                <p class="mt-1 text-sm text-secondary">Offene Überweisungen für Add-ons und Marketplace manuell bestätigen.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="order in orders" :key="order.id">
                            <td class="px-5 py-3 font-semibold text-primary">{{ order.orderable?.name || order.orderable?.title || order.type }}</td>
                            <td class="px-5 py-3 text-secondary">
                                <p class="text-xs font-semibold uppercase text-secondary">Bestellt</p>
                                <p class="font-semibold text-primary">{{ formatDateTime(order.created_at) }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ order.user?.email || '-' }}</td>
                            <td class="px-5 py-3 text-secondary">{{ formatMoney(order.amount_cents) }}</td>
                            <td class="px-5 py-3 text-secondary">
                                <p class="font-semibold text-primary">{{ orderPaymentLabel(order) }}</p>
                                <p v-if="orderPaymentHint(order)" class="text-xs text-secondary">{{ orderPaymentHint(order) }}</p>
                                <p class="text-xs text-secondary">Versand: {{ orderShippingLabel(order.shipping_status) }}</p>
                                <p v-if="order.tracking_number" class="text-xs text-secondary">{{ order.shipping_carrier }} · {{ order.tracking_number }}</p>
                                <p v-if="order.issue_status && order.issue_status !== 'none'" class="text-xs font-semibold text-warning">{{ orderIssueLabel(order.issue_status) }}</p>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button v-if="['pending', 'awaiting_transfer'].includes(order.status)" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="markOrderPaid(order)">
                                        Bezahlt
                                    </button>
                                    <a v-if="order.invoice_number" :href="route('admin.commerce.orders.invoice', order.id)" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">Rechnung</a>
                                    <a v-if="order.credit_note_number" :href="route('admin.commerce.orders.credit-note', order.id)" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">Gutschrift</a>
                                    <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="openShippingModal(order)">Versand</button>
                                    <button class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="openRefundModal(order)">Teilerstattung</button>
                                    <button v-if="order.issue_status && order.issue_status !== 'none'" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="openIssueReplyModal(order)">Antworten</button>
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

        <section v-if="activeTab === 'reports'" class="grid gap-6 xl:grid-cols-2">
            <article class="surface-card overflow-hidden">
                <div class="border-b border-border p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Verkäufer</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Bestand und Angebote</h2>
                </div>
                <div class="divide-y divide-border">
                    <div v-for="report in sellerReports" :key="report.product_id" class="flex items-center justify-between gap-3 p-4">
                        <div>
                            <p class="font-semibold text-primary">{{ report.title }}</p>
                            <p class="text-xs text-secondary">{{ report.seller || 'Airmius' }} · {{ report.status }}</p>
                        </div>
                        <span :class="['rounded-full px-2 py-1 text-xs font-semibold', report.low_stock ? 'bg-warning/10 text-warning' : 'bg-muted text-secondary']">
                            Bestand {{ report.manages_stock ? report.stock_quantity : 'frei' }}
                        </span>
                        <div v-if="report.inventories?.length" class="mt-2 flex flex-wrap gap-1">
                            <span v-for="inventory in report.inventories" :key="`${report.product_id}-${inventory.country_code}`" class="rounded-full border border-border px-2 py-1 text-[11px] font-semibold text-secondary">
                                {{ inventory.country_code }} {{ inventory.available_quantity }}
                            </span>
                        </div>
                    </div>
                    <p v-if="!sellerReports.length" class="p-5 text-sm text-secondary">Noch keine Verkäuferdaten.</p>
                </div>
            </article>

            <article class="surface-card overflow-hidden">
                <div class="border-b border-border p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Audit</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Commerce-Audit-Log</h2>
                </div>
                <div class="divide-y divide-border">
                    <div v-for="entry in auditLogs" :key="entry.id" class="p-4">
                        <p class="font-semibold text-primary">{{ entry.action }}</p>
                        <p class="text-xs text-secondary">{{ entry.user?.email || 'System' }} · {{ entry.created_at }}</p>
                        <p v-if="entry.note" class="mt-1 text-xs text-secondary">{{ entry.note }}</p>
                    </div>
                <p v-if="!auditLogs.length" class="p-5 text-sm text-secondary">Noch keine Audit-Einträge.</p>
                </div>
            </article>
        </section>

        <section v-if="activeTab === 'orders'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Website-Anfragen</h2>
                <p class="mt-1 text-sm text-secondary">Vereine, die eine Website von Airmius erstellen lassen möchten.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="request in websiteRequests" :key="request.id">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ request.club?.name || request.club_name || 'Ohne Verein' }}</p>
                                <p class="text-xs text-secondary">{{ request.user?.email || request.guest_email || '-' }}</p>
                                <p v-if="request.guest_name" class="text-xs text-secondary">{{ request.guest_name }}</p>
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

        <div v-if="editProductForm.open" class="fixed inset-0 z-50 overflow-y-auto bg-black/60 px-4 py-8">
            <div class="mx-auto w-full max-w-4xl rounded-xl border border-border bg-card p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Produkt bearbeiten</h2>
                        <p class="mt-1 text-sm text-secondary">Name, Texte, Preis, Bilder, Bestand und Variantenoptionen zentral pflegen.</p>
                    </div>
                    <button class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="closeEditProduct">Schließen</button>
                </div>

                <form class="mt-5 grid gap-3 md:grid-cols-2" @submit.prevent="submitEditProduct">
                    <input v-model="editProductForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                    <input v-model="editProductForm.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR">
                    <select v-model="editProductForm.product_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="single">Einfaches Produkt</option>
                        <option value="variable">Variables Produkt</option>
                        <option value="digital">Immaterial / digital</option>
                    </select>
                    <textarea
                        v-if="editProductForm.product_type === 'digital'"
                        v-model="editProductForm.digital_delivery_note"
                        rows="2"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Lieferinfo, z. B. Versand per E-Mail"
                    ></textarea>
                    <select v-model="editProductForm.category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="product">Produkt</option>
                        <option value="course">Kurs</option>
                        <option value="camp">Camp</option>
                        <option value="service">Dienstleistung</option>
                    </select>
                    <input v-model="editProductForm.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer">
                    <select v-model="editProductForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="standard">Standardsteuer</option>
                        <option value="reduced">Ermäßigt</option>
                        <option value="zero">Nullsatz</option>
                    </select>
                    <select v-model="editProductForm.status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="draft">Entwurf</option>
                        <option value="review">Prüfen</option>
                        <option value="published">Freigegeben</option>
                        <option value="archived">Archiviert</option>
                        <option value="rejected">Abgelehnt</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="editProductForm.is_shippable" type="checkbox" class="rounded border-border bg-inputBg">
                        Versandpflichtig
                    </label>
                    <label class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="editProductForm.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
                        Lagerbestand verwalten
                    </label>
                    <input v-if="editProductForm.manages_stock" v-model="editProductForm.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerbestand">
                    <input v-model="editProductForm.low_stock_threshold" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Warnbestand">

                    <div class="md:col-span-2 grid gap-3 rounded-lg border border-border bg-bg p-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Hauptbild per URL</label>
                            <input v-model="editProductForm.image_url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="https://...">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Hauptbild hochladen</label>
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setEditProductImageUpload">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Weitere Bild-URLs</label>
                            <textarea v-model="editProductForm.image_urls_text" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Eine URL pro Zeile"></textarea>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Weitere Bilder hochladen</label>
                            <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setEditProductGalleryUploads">
                            <p class="mt-1 text-xs text-secondary">Neue Uploads werden zur Galerie hinzugefügt.</p>
                        </div>
                    </div>

                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Merkmale / Variantenoptionen</h3>
                                <p class="text-xs text-secondary">Werte mit Komma oder | trennen, z. B. Rot | Blau | Schwarz.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addEditAttributeRow">Merkmal hinzufügen</button>
                        </div>
                        <div class="mt-3 space-y-2">
                            <div v-for="(row, index) in editAttributeRows" :key="index" class="grid gap-2 md:grid-cols-[11rem_minmax(0,1fr)_auto]">
                                <select v-model="row.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" @change="row.values = []">
                                    <option value="">Merkmal wählen</option>
                                    <option v-for="preset in attributePresets" :key="preset.name" :value="preset.name">{{ preset.name }}</option>
                                </select>
                                <select v-model="row.values" multiple class="min-h-24 rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-for="value in presetValuesFor(row.name)" :key="value" :value="value">{{ value }}</option>
                                </select>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="removeEditAttributeRow(index)">Entfernen</button>
                            </div>
                        </div>
                    </div>

                    <div v-if="editProductForm.product_type === 'variable'" class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Varianten</h3>
                                <p class="text-xs text-secondary">Eigene Merkmale, Preis, Bestand und Bild pro Variante.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addEditVariantRow">Variante hinzufügen</button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(variant, index) in editVariantRows" :key="index" class="rounded-lg border border-border p-3">
                                <div class="grid gap-2 md:grid-cols-4">
                                    <select
                                        v-for="attribute in normalizeAttributeRows(editAttributeRows)"
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
                                    <input v-model="variant.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" placeholder="Bild-URL für Variante">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="removeEditVariantRow(index)">Variante entfernen</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Produkt-Highlights</h3>
                                <p class="text-xs text-secondary">Kurze Bulletpoints für die Produktseite.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addEditFeatureRow">Punkt hinzufügen</button>
                        </div>
                        <div class="mt-3 space-y-2">
                            <div v-for="(feature, index) in editFeatureRows" :key="index" class="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto]">
                                <input v-model="editFeatureRows[index]" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Atmungsaktiv">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="removeEditFeatureRow(index)">Entfernen</button>
                            </div>
                        </div>
                    </div>

                    <textarea v-model="editProductForm.description" rows="4" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>

                    <div class="md:col-span-2 flex justify-end gap-3">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeEditProduct">Abbrechen</button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="editProductForm.processing">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="rejectionModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-semibold text-primary">Angebot ablehnen</h2>
                <label class="mt-4 block">
                    <span class="text-xs font-semibold uppercase text-secondary">Grund</span>
                    <select v-model="rejectionModal.selectedReason" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" @change="selectRejectionReason">
                        <option value="">Grund auswählen</option>
                        <option v-for="reason in rejectionReasons" :key="reason.value" :value="reason.value">
                            {{ reason.label }}
                        </option>
                    </select>
                </label>
                <textarea v-model="rejectionModal.reason" rows="5" class="mt-3 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ablehnungsgrund für den Verkäufer"></textarea>
                <div class="mt-5 flex justify-end gap-3">
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="rejectionModal.open = false">Abbrechen</button>
                    <button class="rounded-lg bg-warning px-4 py-2 text-sm font-semibold text-white" :disabled="!rejectionModal.reason.trim()" @click="submitRejection">Ablehnen</button>
                </div>
            </div>
        </div>

        <div v-if="productDeleteModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg rounded-xl border border-danger/30 bg-card p-5 shadow-2xl">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-danger/10 text-danger">
                        <i class="las la-trash text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-danger">Produkt löschen</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">{{ productDeleteModal.product?.title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            Produkte ohne Bestellungen werden endgültig gelöscht. Wenn bereits Bestellungen existieren, wird das Produkt aus Sicherheitsgründen archiviert, damit Rechnungen und Käufe nachvollziehbar bleiben.
                        </p>
                    </div>
                </div>
                <label class="mt-5 block">
                    <span class="text-xs font-semibold uppercase text-secondary">Zur Bestätigung delete eingeben</span>
                    <input v-model="productDeleteModal.confirmation" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="delete">
                </label>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" :disabled="productDeleteModal.processing" @click="closeDeleteProduct">
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-danger px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="productDeleteModal.confirmation !== 'delete' || productDeleteModal.processing"
                        @click="destroyProduct"
                    >
                        Löschen
                    </button>
                </div>
            </div>
        </div>

        <div v-if="issueReplyModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-xl rounded-xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-semibold text-primary">Auf Meldung antworten</h2>
                <div v-if="issueReplyModal.order" class="mt-3 rounded-lg border border-border bg-bg p-3 text-sm">
                    <p class="text-xs font-semibold uppercase text-secondary">Meldung des Käufers</p>
                    <p class="mt-1 text-primary">{{ issueReplyModal.order.issue_note || 'Keine Nachricht hinterlegt.' }}</p>
                </div>
                <div class="mt-4 grid gap-3">
                    <textarea v-model="issueReplyModal.issue_response" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Antwort an den Käufer schreiben"></textarea>
                    <p v-if="issueReplyModal.errors.issue_response" class="text-sm text-danger">{{ issueReplyModal.errors.issue_response }}</p>
                    <select v-model="issueReplyModal.issue_status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="reviewing">In Prüfung</option>
                        <option value="resolved">Gelöst</option>
                        <option value="reported">Weiter offen</option>
                    </select>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeIssueReplyModal">Abbrechen</button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="issueReplyModal.processing" @click="submitIssueReply">
                        Antwort senden
                    </button>
                </div>
            </div>
        </div>

        <div v-if="shippingModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-semibold text-primary">Versand bearbeiten</h2>
                <div class="mt-4 grid gap-3">
                    <select v-model="shippingModal.shipping_status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="open">Offen</option>
                        <option value="prepared">Vorbereitet</option>
                        <option value="shipped">Versendet</option>
                        <option value="delivered">Zugestellt</option>
                    </select>
                    <input
                        v-model="shippingModal.shipping_carrier"
                        list="commerce-shipping-carriers"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="DHL / UPS / Hermes"
                        @change="autofillTrackingUrl"
                    >
                    <datalist id="commerce-shipping-carriers">
                        <option v-for="carrier in shippingCarriers" :key="carrier.value" :value="carrier.value">
                            {{ carrier.label }}
                        </option>
                    </datalist>
                    <input v-model="shippingModal.shipping_label_url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Versandlabel-URL">
                    <input v-model="shippingModal.tracking_number" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Trackingnummer" @blur="autofillTrackingUrl">
                    <input v-model="shippingModal.tracking_url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tracking-URL">
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="shippingModal.open = false">Abbrechen</button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="submitShipping">Speichern</button>
                </div>
            </div>
        </div>

        <div v-if="refundModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-semibold text-primary">Erstattung dokumentieren</h2>
                <div class="mt-4 grid gap-3">
                    <input v-model="refundModal.amount_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Betrag in EUR">
                    <textarea v-model="refundModal.reason" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Grund"></textarea>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="refundModal.open = false">Abbrechen</button>
                    <button class="rounded-lg bg-warning px-4 py-2 text-sm font-semibold text-white" @click="submitRefund">Erstatten</button>
                </div>
            </div>
        </div>
    </div>
</template>
