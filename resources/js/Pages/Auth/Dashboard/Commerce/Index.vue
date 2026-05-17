<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { centsToMajor, majorToCents, moneyInputAttrs, transformMoneyFields } from '@/utils/currency'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    addons: { type: Array, default: () => [] },
    accountPlans: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    outfitPlans: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    purchaseHistory: { type: Array, default: () => [] },
    myProducts: { type: Array, default: () => [] },
    sellerApplication: { type: Object, default: null },
    sellerCanSell: { type: Boolean, default: false },
    myCampaigns: { type: Array, default: () => [] },
    websiteRequests: { type: Array, default: () => [] },
    payoutProfile: { type: Object, default: null },
    payoutSummary: { type: Object, default: () => ({}) },
    myPayouts: { type: Array, default: () => [] },
    returnRequests: { type: Array, default: () => [] },
    cart: { type: Object, default: () => ({ items: [], summary: {} }) },
    pricingCountries: { type: Array, default: () => [] },
    sports: { type: Array, default: () => [] },
    learningCourses: { type: Array, default: () => [] },
    checkoutAddress: { type: Object, default: () => ({}) },
    marketplaceCategoryCommissions: { type: Array, default: () => [] },
})

const page = usePage()
const selectedClubId = ref(props.clubs[0]?.id || '')
const provider = ref('bank_transfer')
const adProvider = ref('bank_transfer')
const interval = ref('monthly')
const adAcceptedTerms = ref(false)
const issueModal = ref({ open: false, order: null, note: '', mode: 'issue' })
const showCartCheckout = ref(false)
const checkoutConfirmation = ref({ open: false, type: null, item: null, provider: 'bank_transfer', accepted: false })
const productCreateModal = ref(false)
const websiteRequestModal = ref(false)
const queryTab = new URLSearchParams(String(page.url || '').split('?')[1] || '').get('tab')
const queryOrderId = new URLSearchParams(String(page.url || '').split('?')[1] || '').get('order')
const activeTab = ref(queryTab === 'marketplace' || !queryTab ? 'shop' : queryTab)
const focusedOrderId = ref(queryOrderId || '')
const shopView = ref('all')
const campaignActionError = ref('')
const campaignCreateError = ref('')
const deleteCampaignModal = ref({ open: false, campaign: null, confirmation: '' })
const editCampaignModal = ref({ open: false, campaign: null })
const adGroupModal = ref({ open: false, campaign: null })
const adCreativeModal = ref({ open: false, campaign: null, group: null })
const editCampaignUploadPreviewUrl = ref('')
const adGroupSportQuery = ref('')
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
    learning_course_id: '',
    offer_type: 'physical_product',
    category: 'equipment',
    product_type: 'single',
    sku: '',
    is_shippable: true,
    manages_stock: true,
    stock_quantity: 1,
    inventories: [
        { country_code: 'DE', stock_quantity: 1, low_stock_threshold: 0, lead_time_days: 2, city: '', postal_code: '' },
    ],
    tax_class: 'standard',
    digital_delivery_note: '',
    course_outline_text: '',
    learning_goals_text: '',
    coaching_enabled: false,
    coach_feedback_instructions: '',
    price_cents: '',
})
const productImportForm = useForm({
    import_file: null,
})
const sellerApplicationForm = useForm({
    applicant_type: props.sellerApplication?.applicant_type || 'private',
    business_name: props.sellerApplication?.business_name || '',
    notes: props.sellerApplication?.notes || '',
    rule_product_truth: false,
    rule_rights: false,
    rule_shipping_returns: false,
    rule_commission: false,
    rule_data_privacy: false,
})
const productAttributeRows = ref([{ name: '', values: [] }])
const productVariantRows = ref([])
const editProductModal = ref({ open: false, product: null })
const deleteProductModal = ref({ open: false, product: null, confirmation: '' })
const editProductForm = useForm({
    title: '',
    description: '',
    image_url: '',
    image_upload: null,
    sku: '',
    price_cents: '',
    manages_stock: false,
    stock_quantity: '',
    inventories: [],
})
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
    start_payment: false,
})
const adGroupForm = useForm({
    name: '',
    placement: 'feed',
    sports: [],
    interests: '',
    gender: 'all',
    age_min: '',
    age_max: '',
    locations: '',
    zones: '',
    daily_budget_cents: '',
    starts_at: '',
    ends_at: '',
})
const adCreativeForm = useForm({
    ad_name: '',
    creatives: [],
})
const editCampaignForm = useForm({
    name: '',
    headline: '',
    description: '',
    primary_text: '',
    target_url: '',
    cta_label: '',
    objective: 'traffic',
    placement: 'feed',
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
const payoutRequestForm = useForm({
    method: 'bank_transfer',
    notes: '',
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

const formatDateTime = (value) => value
    ? new Intl.DateTimeFormat('de-DE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
    : '-'

const productStatusLabel = (status) => ({
    draft: 'Entwurf',
    review: 'In Pruefung',
    published: 'Online',
    rejected: 'Abgelehnt',
    archived: 'Archiviert',
}[status] || status || 'Unbekannt')

const sellerApplicationStatusLabel = (status) => ({
    pending: 'Wartet auf Pruefung',
    approved: 'Freigegeben',
    rejected: 'Abgelehnt',
}[status] || 'Noch kein Antrag')

const offerTypeLabel = (offerType, category) => ({
    physical_product: 'Produkt',
    online_course: 'Kurs',
    training_plan: 'Trainingsplan',
    camp: 'Camp',
    service: 'Service',
}[offerType] || ({
    product: 'Produkt',
    course: 'Kurs',
    camp: 'Camp',
    service: 'Service',
    outfit_subscription: 'Outfit-Abo',
}[category] || 'Angebot'))

const isLearningOffer = computed(() => ['online_course', 'training_plan'].includes(productForm.offer_type))
const productStockRequired = computed(() => productForm.offer_type === 'physical_product' && productForm.product_type !== 'digital')
const productCommissionCategory = computed(() => productForm.category || 'product')
const sellerMarketplaceCategories = computed(() => props.marketplaceCategoryCommissions
    .filter((row) => !['service', 'outfit_subscription'].includes(row.category))
    .filter((row) => productForm.offer_type === 'physical_product'
        ? !['course', 'camp'].includes(row.category)
        : productForm.offer_type === 'camp'
            ? row.category === 'camp'
            : ['online_course', 'training_plan'].includes(productForm.offer_type)
                ? row.category === 'course' || row.category === 'digital_products'
                : false))
const selectedProductCommission = computed(() => props.marketplaceCategoryCommissions.find((row) => row.category === productCommissionCategory.value) || {
    category: productCommissionCategory.value,
    label: offerTypeLabel(productForm.offer_type, productForm.category),
    commission_percent: 10,
})
const productPricePreviewCents = computed(() => majorToCents(productForm.price_cents))
const productCommissionPreviewCents = computed(() => Math.floor(productPricePreviewCents.value * (Number(selectedProductCommission.value.commission_percent || 0) / 100)))
const productSellerPayoutPreviewCents = computed(() => Math.max(0, productPricePreviewCents.value - productCommissionPreviewCents.value))
const inventoryCountries = computed(() => {
    const countries = props.pricingCountries.map((country) => country.country).filter(Boolean)
    return [...new Set(['DE', ...countries])]
})
const inventoryTotalStock = computed(() => normalizeInventoryRows(productForm.inventories).reduce((sum, row) => sum + Number(row.stock_quantity || 0), 0))

watch(() => productForm.offer_type, (offerType) => {
    const map = {
        physical_product: [sellerMarketplaceCategories.value[0]?.category || 'equipment', 'single', true],
        online_course: ['course', 'digital', false],
        training_plan: ['course', 'digital', false],
        camp: ['camp', 'single', false],
        service: ['service', 'digital', false],
    }
    const [category, productType, shippable] = map[offerType] || map.physical_product
    productForm.category = category
    productForm.product_type = productType
    productForm.is_shippable = shippable
    if (offerType === 'physical_product' && productType !== 'digital') {
        productForm.manages_stock = true
        productForm.stock_quantity = productForm.stock_quantity || 1
        if (!productForm.inventories.length) {
            productForm.inventories = [{ country_code: 'DE', stock_quantity: productForm.stock_quantity || 1, low_stock_threshold: 0, lead_time_days: 2, city: '', postal_code: '' }]
        }
    }
    if (productType === 'digital') {
        productForm.manages_stock = false
        productForm.stock_quantity = ''
        productForm.inventories = []
    }
    if (offerType === 'training_plan') {
        productForm.coaching_enabled = true
    }
    if (offerType !== 'online_course') {
        productForm.learning_course_id = ''
    }
})

watch(sellerMarketplaceCategories, (categories) => {
    if (categories.length && !categories.some((category) => category.category === productForm.category)) {
        productForm.category = categories[0].category
    }
})

watch(() => productForm.product_type, (productType) => {
    if (productForm.offer_type === 'physical_product' && productType !== 'digital') {
        productForm.manages_stock = true
        productForm.stock_quantity = productForm.stock_quantity || 1
        if (!productForm.inventories.length) {
            productForm.inventories = [{ country_code: 'DE', stock_quantity: productForm.stock_quantity || 1, low_stock_threshold: 0, lead_time_days: 2, city: '', postal_code: '' }]
        }
    }
    if (productType === 'digital') {
        productForm.manages_stock = false
        productForm.stock_quantity = ''
        productForm.inventories = []
    }
})

watch(() => campaignForm.placement, (placement) => {
    const formats = formatsForPlacement(placement)
    if (! formats.some((format) => format.key === campaignForm.creative_format)) {
        campaignForm.creative_format = formats[0]?.key || 'feed_square'
    }
})

watch(() => editCampaignForm.placement, (placement) => {
    const formats = formatsForPlacement(placement)
    if (! formats.some((format) => format.key === editCampaignForm.creative_format)) {
        editCampaignForm.creative_format = formats[0]?.key || 'feed_square'
    }
})

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
            ? 'Deine Bestellung wurde zugestellt.'
            : 'Zahlung eingegangen, deine Bestellung wird bearbeitet.'
    }

    return {
        pending: 'Zahlung noch offen',
        awaiting_transfer: 'Wir warten auf den Zahlungseingang.',
        cancelled: 'Diese Bestellung wurde storniert.',
        refunded: 'Diese Bestellung wurde erstattet.',
    }[order.status] || ''
}

const orderShippingLabel = (status) => ({
    open: 'Offen',
    prepared: 'Wird vorbereitet',
    shipped: 'Versendet',
    delivered: 'Zugestellt',
}[status || 'open'] || status)

const orderIssueLabel = (status) => ({
    reported: 'Problem gemeldet',
    reviewing: 'In Prüfung',
    resolved: 'Gelöst',
    refunded: 'Erstattet',
    cancelled: 'Storniert',
}[status] || status)

const payoutStatusLabel = (status) => ({
    requested: 'Angefordert',
    prepared: 'In Pruefung',
    paid: 'Ausgezahlt',
    cancelled: 'Storniert',
}[status] || status || '-')

const orderIsDelivered = (order) => order.status === 'completed' && order.shipping_status === 'delivered'
const orderHasShippableItems = (order) => (order.items || []).some((item) => item.is_shippable)
const orderCanCancel = (order) => ['marketplace_product', 'marketplace_cart'].includes(order.type)
    && orderHasShippableItems(order)
    && ['pending', 'awaiting_transfer', 'completed'].includes(order.status)
    && !['shipped', 'delivered'].includes(order.shipping_status || 'open')
const orderCanReturn = (order) => orderIsDelivered(order) && orderHasShippableItems(order)

const formatPercent = (value) => `${Number(value || 0).toFixed(2).replace('.', ',')} %`

const cartItems = computed(() => props.cart?.items || [])
const cartItemCount = computed(() => cartItems.value.length)
const selectedAddonActor = computed(() => selectedClubId.value ? 'verein' : 'sportler')
const visibleAddons = computed(() => props.addons.filter((addon) => (addon.target_actor || 'verein') === selectedAddonActor.value))
const isProductLearningOffer = (product) => product?.category === 'course' || ['online_course', 'training_plan'].includes(product?.offer_type)
const courseProducts = computed(() => props.products.filter((product) => isProductLearningOffer(product)))
const marketplaceProducts = computed(() => props.products.filter((product) => !isProductLearningOffer(product)))
const visibleShopProducts = computed(() => {
    if (shopView.value === 'all' || shopView.value === 'courses') {
        return courseProducts.value
    }

    return []
})
const visibleShopProductTitle = computed(() => 'Kurse und E-Learning')
const visibleShopProductDescription = computed(() => 'Online-Kurse, Trainingsplaene und digitale Lernangebote kaufen.')
const showAccountShop = computed(() => ['all', 'account'].includes(shopView.value))
const showOutfitShop = computed(() => ['all', 'outfit'].includes(shopView.value))
const showProductShop = computed(() => ['all', 'courses'].includes(shopView.value))
const shopCategoryTabs = computed(() => [
    { key: 'all', label: 'Alle', count: courseProducts.value.length + props.accountPlans.length + visibleAddons.value.length + props.outfitPlans.length },
    { key: 'courses', label: 'Kurse / E-Learning', count: courseProducts.value.length },
    { key: 'outfit', label: 'Outfit-Abo', count: props.outfitPlans.length },
    { key: 'account', label: 'Konto-Abo & Add-ons', count: props.accountPlans.length + visibleAddons.value.length },
])

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
const adPlacements = [
    { key: 'marketplace_card', label: 'Marketplace Karte', formats: ['feed_square', 'banner_wide'], hint: 'Wird auf Marketplace-Karten und passenden Angebotsflaechen ausgespielt.' },
    { key: 'feed', label: 'Feed', formats: ['feed_square', 'feed_portrait'], hint: 'Wird im Feed mit anderen aktiven Feed-Kampagnen rotiert. Budget, Tageslimit und Freigabe steuern die Ausspielung.' },
    { key: 'sidebar', label: 'Sidebar', formats: ['banner_wide', 'feed_square'], hint: 'Schmale Anzeige in passenden Seitenbereichen.' },
    { key: 'sponsor_section', label: 'Sponsor-Bereich', formats: ['banner_wide', 'feed_square'], hint: 'Anzeige in Sponsor- und Partnerbereichen.' },
]
const formatsForPlacement = (placementKey) => {
    const placement = adPlacements.find((item) => item.key === placementKey) || adPlacements[0]
    return adFormats.filter((format) => placement.formats.includes(format.key))
}
const campaignAdFormats = computed(() => formatsForPlacement(campaignForm.placement))
const editCampaignAdFormats = computed(() => formatsForPlacement(editCampaignForm.placement))
const selectedAdFormat = computed(() => adFormats.find((format) => format.key === campaignForm.creative_format) || adFormats[0])
const selectedAdPlacement = computed(() => adPlacements.find((placement) => placement.key === campaignForm.placement) || adPlacements[0])
const selectedEditAdFormat = computed(() => adFormats.find((format) => format.key === editCampaignForm.creative_format) || adFormats[0])
const selectedEditAdPlacement = computed(() => adPlacements.find((placement) => placement.key === editCampaignForm.placement) || adPlacements[0])
const adPlacementLabel = (placementKey) => adPlacements.find((placement) => placement.key === placementKey)?.label || placementKey
const sportLabel = (value) => props.sports.find((sport) => sport.slug === value || sport.name === value || String(sport.id) === String(value))?.name || value
const selectedAdGroupSports = computed(() => Array.isArray(adGroupForm.sports) ? adGroupForm.sports : [])
const filteredAdGroupSports = computed(() => {
    const selected = new Set(selectedAdGroupSports.value)
    const query = adGroupSportQuery.value.trim().toLowerCase()

    return props.sports
        .filter((sport) => !selected.has(sport.slug))
        .filter((sport) => {
            if (!query) {
                return true
            }

            return [sport.name, sport.slug, sport.category]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(query))
        })
        .slice(0, 30)
})
const campaignCreativeRows = ref([
    { name: 'Variante A', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
    { name: 'Variante B', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
])
const adCreativeRows = ref([
    { name: 'Variante A', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
    { name: 'Variante B', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
])
const editCampaignCreativeRows = ref([])

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
    checkoutConfirmation.value = { open: true, type: 'addon', item: addon, provider: provider.value, accepted: false }
}

const confirmAddonCheckout = (addon, selectedProvider) => {
    router.post(route('auth.commerce.addons.checkout', addon.id), {
        provider: selectedProvider,
        billing_interval: interval.value,
        club_id: selectedClubId.value || null,
        accepted_terms: true,
    })
}

const checkoutAccountPlan = (plan, selectedProvider) => {
    if (plan.is_owned || !Number(plan.monthly_price_cents || 0)) {
        return
    }

    checkoutConfirmation.value = { open: true, type: 'account_plan', item: plan, provider: selectedProvider, accepted: false }
}

const confirmAccountPlanCheckout = (plan, selectedProvider) => {
    if (!plan || plan.is_owned || !Number(plan.monthly_price_cents || 0)) {
        return
    }

    const url = new URL(`/checkout/subscriptions/${plan.id}/start`, window.location.origin)
    url.searchParams.set('provider', selectedProvider)
    url.searchParams.set('billing_interval', interval.value)
    url.searchParams.set('accepted_terms', '1')
    window.location.href = url.toString()
}

const checkoutProduct = (product) => {
    checkoutConfirmation.value = { open: true, type: 'product', item: product, provider: provider.value, accepted: false }
}

const confirmProductCheckout = (product, selectedProvider) => {
    router.post(route('auth.commerce.products.checkout', product.id), {
        provider: selectedProvider,
        accepted_terms: true,
    })
}

const closeCheckoutConfirmation = () => {
    checkoutConfirmation.value = { open: false, type: null, item: null, provider: 'bank_transfer', accepted: false }
}

const providerLabel = (value) => ({
    bank_transfer: 'Ueberweisung',
    stripe: 'Stripe',
    paypal: 'PayPal',
})[value] || value

const checkoutConfirmationTitle = computed(() => {
    const item = checkoutConfirmation.value.item

    if (!item) {
        return 'Checkout bestaetigen'
    }

    if (checkoutConfirmation.value.type === 'account_plan') {
        return item.name
    }

    return item.name || item.title || 'Checkout bestaetigen'
})

const checkoutConfirmationPrice = computed(() => {
    const item = checkoutConfirmation.value.item

    if (!item) {
        return ''
    }

    if (checkoutConfirmation.value.type === 'addon') {
        return formatMoney(addonPrice(item), 'EUR')
    }

    if (checkoutConfirmation.value.type === 'account_plan') {
        return formatMoney(item.monthly_price_cents, item.currency)
    }

    return formatMoney(item.price_cents, item.currency)
})

const confirmCheckout = () => {
    const checkout = checkoutConfirmation.value

    if (!checkout.accepted || !checkout.item) {
        return
    }

    if (checkout.type === 'addon') {
        confirmAddonCheckout(checkout.item, checkout.provider)
    } else if (checkout.type === 'account_plan') {
        confirmAccountPlanCheckout(checkout.item, checkout.provider)
    } else if (checkout.type === 'product') {
        confirmProductCheckout(checkout.item, checkout.provider)
    }

    closeCheckoutConfirmation()
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

const activeOrders = computed(() => props.orders.filter((order) => ['pending', 'awaiting_transfer', 'completed', 'cancelled', 'refunded'].includes(order.status)))
const commerceTabs = computed(() => [
    { key: 'shop', label: 'Shop', icon: 'las la-th-large', count: courseProducts.value.length + props.accountPlans.length + visibleAddons.value.length + props.outfitPlans.length },
    { key: 'cart', label: 'Warenkorb', icon: 'las la-shopping-cart', count: cartItemCount.value },
    { key: 'invoices', label: 'Rechnungen', icon: 'las la-file-invoice', count: props.purchaseHistory.length },
    { key: 'ads', label: 'Ads', icon: 'las la-bullhorn', count: props.myCampaigns.length },
    { key: 'create', label: 'Verkaufen', icon: 'las la-plus-circle', count: props.myProducts.length + props.websiteRequests.length },
    { key: 'payouts', label: 'Auszahlung', icon: 'las la-wallet', count: props.payoutSummary.pending_orders || 0 },
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

const campaignCreateErrors = computed(() => Object.values(campaignForm.errors || {})
    .flatMap((message) => Array.isArray(message) ? message : [message])
    .filter(Boolean))

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

const setProductImportFile = (event) => {
    productImportForm.import_file = event.target.files?.[0] || null
}

const setCampaignCreativeUpload = (event) => {
    campaignForm.creative_image_upload = event.target.files?.[0] || null
}

const setEditCampaignCreativeUpload = (event) => {
    if (editCampaignUploadPreviewUrl.value) {
        URL.revokeObjectURL(editCampaignUploadPreviewUrl.value)
        editCampaignUploadPreviewUrl.value = ''
    }

    const file = event.target.files?.[0] || null
    editCampaignForm.creative_image_upload = file
    editCampaignUploadPreviewUrl.value = file ? URL.createObjectURL(file) : ''
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

const addAdCreativeRow = () => {
    adCreativeRows.value.push({
        name: `Variante ${String.fromCharCode(65 + adCreativeRows.value.length)}`,
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

const removeAdCreativeRow = (index) => {
    adCreativeRows.value.splice(index, 1)
    if (!adCreativeRows.value.length) {
        addAdCreativeRow()
    }
}

const addEditCampaignCreativeRow = () => {
    editCampaignCreativeRows.value.push({
        name: `Variante ${String.fromCharCode(65 + editCampaignCreativeRows.value.length)}`,
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

const removeEditCampaignCreativeRow = (index) => {
    editCampaignCreativeRows.value.splice(index, 1)
    if (!editCampaignCreativeRows.value.length) {
        addEditCampaignCreativeRow()
    }
}

const addAdGroupSport = (sport) => {
    const value = sport.slug || sport.name

    if (!selectedAdGroupSports.value.includes(value)) {
        adGroupForm.sports = [...selectedAdGroupSports.value, value]
    }

    adGroupSportQuery.value = ''
}

const removeAdGroupSport = (sport) => {
    adGroupForm.sports = selectedAdGroupSports.value.filter((value) => value !== sport)
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

const normalizeAdCreatives = () => adCreativeRows.value
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

const normalizeEditCampaignCreatives = () => editCampaignCreativeRows.value
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

const campaignAudienceText = (campaign, key) => Array.isArray(campaign.audience?.[key])
    ? campaign.audience[key].join(', ')
    : ''

const adGroupAudienceText = (group, key) => Array.isArray(group.audience?.[key])
    ? group.audience[key].join(', ')
    : ''
const adGroupAudienceSportsText = (group) => Array.isArray(group.audience?.sports)
    ? group.audience.sports.map((sport) => sportLabel(sport)).join(', ')
    : ''
const campaignRootCreatives = (campaign) => (campaign.creatives || []).filter((creative) => !creative.ad_group_id)

const firstCreativePreviewUrl = (campaign) => campaign?.creatives?.find((creative) => creative.preview_image_url || creative.creative_image_url)?.preview_image_url
    || campaign?.creatives?.find((creative) => creative.preview_image_url || creative.creative_image_url)?.creative_image_url
    || ''

const editCampaignPreviewUrl = computed(() => editCampaignUploadPreviewUrl.value
    || editCampaignForm.creative_image_url
    || editCampaignModal.value.campaign?.preview_image_url
    || editCampaignModal.value.campaign?.creative_image_url
    || firstCreativePreviewUrl(editCampaignModal.value.campaign))

const creativePreviewUrl = (creative) => creative.creative_image_url || creative.preview_image_url || ''

const toLocalDateTimeInput = (value) => {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return ''
    }

    const pad = (number) => String(number).padStart(2, '0')

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const normalizeInventoryRows = (rows = []) => rows
    .map((row) => ({
        country_code: String(row.country_code || '').toUpperCase().slice(0, 2),
        stock_quantity: Math.max(0, Number(row.stock_quantity || 0)),
        low_stock_threshold: Math.max(0, Number(row.low_stock_threshold || 0)),
        lead_time_days: row.lead_time_days === '' || row.lead_time_days === null ? '' : Math.max(0, Number(row.lead_time_days || 0)),
        city: row.city || '',
        postal_code: row.postal_code || '',
    }))
    .filter((row) => row.country_code.length === 2)

const addProductInventoryRow = () => {
    productForm.inventories.push({ country_code: 'DE', stock_quantity: 0, low_stock_threshold: 0, lead_time_days: 2, city: '', postal_code: '' })
}

const removeProductInventoryRow = (index) => {
    if (productForm.inventories.length <= 1) {
        return
    }

    productForm.inventories.splice(index, 1)
}

const addEditProductInventoryRow = () => {
    editProductForm.inventories.push({ country_code: 'DE', stock_quantity: 0, low_stock_threshold: 0, lead_time_days: 2, city: '', postal_code: '' })
}

const removeEditProductInventoryRow = (index) => {
    editProductForm.inventories.splice(index, 1)
}

const storeProduct = () => {
    productForm.attributes_text = productAttributesText()
    productForm.attribute_options = normalizeAttributeRows(productAttributeRows.value)
    productForm.variants = normalizeVariantRows(productVariantRows.value)
    productForm.inventories = productForm.manages_stock ? normalizeInventoryRows(productForm.inventories) : []
    if (productForm.manages_stock && productForm.inventories.length) {
        productForm.stock_quantity = productForm.inventories.reduce((sum, row) => sum + Number(row.stock_quantity || 0), 0) || productForm.stock_quantity || 1
    }

    productForm
        .transform((data) => transformMoneyFields(data, ['price_cents']))
        .post(route('auth.commerce.products.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            productForm.reset('title', 'description', 'attributes_text', 'attribute_options', 'variants', 'image_url', 'image_urls_text', 'image_upload', 'image_uploads', 'learning_course_id', 'sku', 'digital_delivery_note', 'course_outline_text', 'learning_goals_text', 'coach_feedback_instructions')
            productForm.offer_type = 'physical_product'
            productForm.category = sellerMarketplaceCategories.value[0]?.category || 'equipment'
            productForm.product_type = 'single'
            productForm.is_shippable = true
            productForm.manages_stock = true
            productForm.stock_quantity = 1
            productForm.inventories = [{ country_code: 'DE', stock_quantity: 1, low_stock_threshold: 0, lead_time_days: 2, city: '', postal_code: '' }]
            productForm.coaching_enabled = false
            productAttributeRows.value = [{ name: '', values: [] }]
            productVariantRows.value = []
            productCreateModal.value = false
        },
        })
}

const importProducts = () => {
    productImportForm.post(route('auth.commerce.products.import'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => productImportForm.reset('import_file'),
    })
}

const storeSellerApplication = () => {
    sellerApplicationForm.post(route('auth.commerce.seller-application.store'), {
        preserveScroll: true,
    })
}

const openEditProductModal = (product) => {
    editProductForm.title = product.title || ''
    editProductForm.description = product.description || ''
    editProductForm.image_url = product.image_url || ''
    editProductForm.image_upload = null
    editProductForm.sku = product.sku || ''
    editProductForm.price_cents = centsToMajor(product.price_cents)
    editProductForm.manages_stock = Boolean(product.manages_stock)
    editProductForm.stock_quantity = product.stock_quantity ?? ''
    editProductForm.inventories = normalizeInventoryRows(product.inventories || []).map((inventory) => ({
        country_code: inventory.country_code,
        stock_quantity: inventory.stock_quantity,
        low_stock_threshold: inventory.low_stock_threshold || 0,
        lead_time_days: inventory.lead_time_days ?? '',
        city: inventory.warehouse?.city || '',
        postal_code: inventory.warehouse?.postal_code || '',
    }))
    editProductModal.value = { open: true, product }
}

const closeEditProductModal = () => {
    editProductModal.value = { open: false, product: null }
    editProductForm.clearErrors()
}

const submitEditProduct = () => {
    const product = editProductModal.value.product

    if (!product) {
        return
    }

    editProductForm.inventories = editProductForm.manages_stock ? normalizeInventoryRows(editProductForm.inventories) : []
    if (editProductForm.manages_stock && editProductForm.inventories.length) {
        editProductForm.stock_quantity = editProductForm.inventories.reduce((sum, row) => sum + Number(row.stock_quantity || 0), 0)
    }

    editProductForm
        .transform((data) => transformMoneyFields(data, ['price_cents']))
        .post(route('auth.commerce.my-products.update', product.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeEditProductModal,
        })
}

const updateOwnProductStatus = (product, status) => {
    router.put(route('auth.commerce.my-products.status.update', product.id), { status }, {
        preserveScroll: true,
    })
}

const openDeleteProductModal = (product) => {
    deleteProductModal.value = { open: true, product, confirmation: '' }
}

const closeDeleteProductModal = () => {
    deleteProductModal.value = { open: false, product: null, confirmation: '' }
}

const destroyOwnProduct = () => {
    const product = deleteProductModal.value.product

    if (!product || deleteProductModal.value.confirmation !== 'delete') {
        return
    }

    router.delete(route('auth.commerce.my-products.destroy', product.id), {
        preserveScroll: true,
        onSuccess: closeDeleteProductModal,
    })
}

const storeCampaign = () => {
    if (campaignForm.processing) {
        return
    }

    campaignForm.creatives = normalizeCampaignCreatives()
    campaignForm.provider = adProvider.value
    campaignForm.accepted_terms = adAcceptedTerms.value
    campaignForm.start_payment = false
    campaignForm.client_reference ||= newClientReference()
    campaignActionError.value = ''
    campaignCreateError.value = ''

    campaignForm
        .transform((data) => transformMoneyFields(data, ['budget_cents', 'daily_budget_cents']))
        .post(route('auth.commerce.campaigns.store'), {
        preserveScroll: true,
        forceFormData: true,
        onError: () => {
            campaignCreateError.value = 'Die Ads-Kampagne konnte nicht zur Zahlung vorbereitet werden. Bitte pruefe die Angaben unten.'
        },
        onSuccess: () => {
            campaignCreateError.value = ''
            campaignForm.reset('name', 'headline', 'description', 'primary_text', 'target_url', 'creative_image_url', 'creative_image_upload', 'creatives', 'audience_locations', 'audience_interests', 'audience_age_min', 'audience_age_max', 'starts_at', 'ends_at', 'budget_cents', 'daily_budget_cents')
            campaignForm.client_reference = ''
            campaignCreativeRows.value = [
                { name: 'Variante A', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
                { name: 'Variante B', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
            ]
        },
        })
}

const openAdGroupModal = (campaign) => {
    adGroupModal.value = { open: true, campaign }
    adGroupForm.clearErrors()
    adGroupForm.reset()
    adGroupForm.name = `${campaign.name || 'Kampagne'} - Zielgruppe 1`
    adGroupForm.placement = campaign.placement || 'feed'
    adGroupForm.sports = []
    adGroupSportQuery.value = ''
}

const closeAdGroupModal = () => {
    adGroupModal.value = { open: false, campaign: null }
    adGroupForm.reset()
    adGroupSportQuery.value = ''
}

const storeAdGroup = () => {
    const campaign = adGroupModal.value.campaign

    if (!campaign || adGroupForm.processing) {
        return
    }

    adGroupForm
        .transform((data) => transformMoneyFields(data, ['daily_budget_cents']))
        .post(route('auth.commerce.campaigns.groups.store', campaign.id), {
            preserveScroll: true,
            onSuccess: closeAdGroupModal,
        })
}

const openAdCreativeModal = (campaign, group) => {
    adCreativeModal.value = { open: true, campaign, group }
    adCreativeForm.clearErrors()
    adCreativeForm.reset()
    adCreativeForm.ad_name = `${group.name || 'Anzeigegruppe'} - Anzeige 1`
    adCreativeRows.value = [
        { name: 'Variante A', headline: campaign.headline || campaign.name || '', primary_text: campaign.primary_text || '', description: campaign.description || '', target_url: campaign.target_url || '', cta_label: campaign.cta_label || '', creative_image_url: campaign.creative_image_url || '', weight: 100, is_active: true },
        { name: 'Variante B', headline: '', primary_text: '', description: '', target_url: campaign.target_url || '', cta_label: campaign.cta_label || '', creative_image_url: '', weight: 100, is_active: true },
    ]
}

const closeAdCreativeModal = () => {
    adCreativeModal.value = { open: false, campaign: null, group: null }
    adCreativeForm.reset()
    adCreativeRows.value = [
        { name: 'Variante A', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
        { name: 'Variante B', headline: '', primary_text: '', description: '', target_url: '', cta_label: '', creative_image_url: '', weight: 100, is_active: true },
    ]
}

const storeAdCreatives = () => {
    const campaign = adCreativeModal.value.campaign
    const group = adCreativeModal.value.group

    if (!campaign || !group || adCreativeForm.processing) {
        return
    }

    adCreativeForm.creatives = normalizeAdCreatives()
    adCreativeForm.post(route('auth.commerce.campaigns.groups.creatives.store', [campaign.id, group.id]), {
        preserveScroll: true,
        onSuccess: closeAdCreativeModal,
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

const openEditCampaignModal = (campaign) => {
    campaignActionError.value = ''
    if (editCampaignUploadPreviewUrl.value) {
        URL.revokeObjectURL(editCampaignUploadPreviewUrl.value)
        editCampaignUploadPreviewUrl.value = ''
    }
    editCampaignModal.value = { open: true, campaign }
    editCampaignForm.clearErrors()
    editCampaignForm.name = campaign.name || ''
    editCampaignForm.headline = campaign.headline || ''
    editCampaignForm.description = campaign.description || ''
    editCampaignForm.primary_text = campaign.primary_text || ''
    editCampaignForm.target_url = campaign.target_url || ''
    editCampaignForm.cta_label = campaign.cta_label || ''
    editCampaignForm.objective = campaign.objective || 'traffic'
    editCampaignForm.placement = campaign.placement || 'feed'
    editCampaignForm.creative_format = campaign.creative_format || 'feed_square'
    editCampaignForm.creative_image_url = campaign.creative_image_url || ''
    editCampaignForm.creative_image_upload = null
    editCampaignForm.audience_locations = campaignAudienceText(campaign, 'locations')
    editCampaignForm.audience_interests = campaignAudienceText(campaign, 'interests')
    editCampaignForm.audience_age_min = campaign.audience?.age_min || ''
    editCampaignForm.audience_age_max = campaign.audience?.age_max || ''
    editCampaignForm.budget_cents = Number(campaign.budget_cents || 0) / 100
    editCampaignForm.daily_budget_cents = campaign.daily_budget_cents ? Number(campaign.daily_budget_cents || 0) / 100 : ''
    editCampaignForm.starts_at = toLocalDateTimeInput(campaign.starts_at)
    editCampaignForm.ends_at = toLocalDateTimeInput(campaign.ends_at)
    editCampaignCreativeRows.value = (campaign.creatives?.length ? campaign.creatives : [{ name: 'Variante A' }]).map((creative, index) => ({
        name: creative.name || `Variante ${String.fromCharCode(65 + index)}`,
        headline: creative.headline || '',
        primary_text: creative.primary_text || '',
        description: creative.description || '',
        target_url: creative.target_url || '',
        cta_label: creative.cta_label || '',
        creative_image_url: creative.creative_image_url || '',
        preview_image_url: creative.preview_image_url || '',
        weight: Number(creative.weight || 100),
        is_active: creative.is_active !== false,
    }))
}

const closeEditCampaignModal = () => {
    if (editCampaignUploadPreviewUrl.value) {
        URL.revokeObjectURL(editCampaignUploadPreviewUrl.value)
        editCampaignUploadPreviewUrl.value = ''
    }
    editCampaignModal.value = { open: false, campaign: null }
    editCampaignForm.reset()
    editCampaignCreativeRows.value = []
}

const submitEditCampaign = () => {
    const campaign = editCampaignModal.value.campaign

    if (!campaign || editCampaignForm.processing) {
        return
    }

    editCampaignForm.creatives = normalizeEditCampaignCreatives()

    editCampaignForm
        .transform((data) => transformMoneyFields(data, ['budget_cents', 'daily_budget_cents']))
        .post(route('auth.commerce.campaigns.update', campaign.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeEditCampaignModal,
            onError: (errors) => {
                campaignActionError.value = errors.campaign_status || errors.name || 'Kampagne konnte nicht gespeichert werden.'
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
    onSuccess: () => {
        websiteForm.reset('domain', 'goals', 'notes')
        websiteRequestModal.value = false
    },
})

const openWebsiteRequestModal = () => {
    websiteForm.club_id ||= props.clubs[0]?.id || ''
    websiteRequestModal.value = true
}

const storePayoutProfile = () => payoutForm.post(route('auth.commerce.payout-profile.store'), {
    preserveScroll: true,
})

const requestPayout = () => {
    payoutRequestForm.post(route('auth.commerce.payouts.request'), {
        preserveScroll: true,
        onSuccess: () => payoutRequestForm.reset('notes'),
    })
}

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

const orderRowId = (orderId) => `commerce-order-${orderId}`

const focusOrder = async (orderId) => {
    if (!orderId) return

    activeTab.value = 'invoices'
    focusedOrderId.value = String(orderId)
    await nextTick()
    document.getElementById(orderRowId(orderId))?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

const openPurchaseDetails = (purchase) => {
    if (purchase.order_id) {
        focusOrder(purchase.order_id)
        return
    }

    if (purchase.detail_url) {
        router.visit(purchase.detail_url)
    }
}

const cancelOrder = (order) => {
    if (!orderCanCancel(order)) return
    if (!confirm('Bestellung wirklich stornieren? Das ist nur moeglich, solange sie noch nicht versendet wurde.')) return

    router.post(route('auth.commerce.orders.cancel', order.id), {}, {
        preserveScroll: true,
    })
}

onMounted(() => {
    if (focusedOrderId.value) {
        focusOrder(focusedOrderId.value)
    }
})
</script>

<template>
    <Head title="Shop & Rechnungen" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Kaeufe & Abos</p>
            <h1 class="mt-1 text-2xl font-bold text-primary">Shop, Rechnungen und Angebote</h1>
            <p class="mt-2 max-w-3xl text-sm text-secondary">
                Verwalte Marketplace-Kaeufe, Kurse, Ads, Outfit-Abos, Konto-Abos, Warenkorb und Rechnungen an einem Ort.
            </p>
            <div v-if="page.props.flash?.success" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                {{ page.props.flash.error }}
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

        <section v-show="activeTab === 'shop'" class="surface-card p-5">
            <div class="mb-5 flex gap-2 overflow-x-auto">
                <button
                    v-for="category in shopCategoryTabs"
                    :key="category.key"
                    type="button"
                    class="flex min-h-10 shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition"
                    :class="shopView === category.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-bg text-secondary hover:bg-muted hover:text-primary'"
                    @click="shopView = category.key"
                >
                    <span>{{ category.label }}</span>
                    <span v-if="category.count" class="rounded-full bg-white/15 px-2 py-0.5 text-xs">{{ category.count }}</span>
                </button>
            </div>
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
        </section>

        <section v-show="activeTab === 'shop' && showAccountShop" class="grid gap-5 lg:grid-cols-3">
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
                <p v-if="plan.localized_price" class="mt-1 text-xs text-air-blue">Lokaler Preis fuer {{ plan.pricing_country }}</p>
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
                        @click="checkoutAccountPlan(plan, 'stripe')"
                    >
                        Mit Stripe zahlen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="checkoutAccountPlan(plan, 'paypal')"
                    >
                        Mit PayPal zahlen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="checkoutAccountPlan(plan, 'bank_transfer')"
                    >
                        Per Ueberweisung zahlen
                    </button>
                </div>
            </article>
            <article v-for="addon in visibleAddons" :key="addon.id" class="surface-card flex flex-col p-5">
                <h2 class="text-lg font-semibold text-primary">{{ addon.name }}</h2>
                <p class="mt-2 flex-1 text-sm text-secondary">{{ addon.description }}</p>
                <p class="mt-4 text-2xl font-bold text-primary">{{ formatMoney(addonPrice(addon)) }}</p>
                <button class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="checkoutAddon(addon)">
                    Add-on buchen
                </button>
            </article>
        </section>

        <section v-show="activeTab === 'shop' && showProductShop" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">{{ visibleShopProductTitle }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ visibleShopProductDescription }}</p>
            </div>
            <div class="grid gap-4 p-5 lg:grid-cols-3">
                <article v-for="product in visibleShopProducts" :key="product.id" class="overflow-hidden rounded-lg border border-border bg-bg">
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
                <p v-if="!visibleShopProducts.length" class="text-sm text-secondary">Noch keine passenden Angebote veroeffentlicht.</p>
            </div>
        </section>

        <section v-show="activeTab === 'shop' && showOutfitShop" class="surface-card overflow-hidden">
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
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Eigene Angebote verwalten</h2>
                        <p class="mt-1 text-sm text-secondary">Erst nach einem freigegebenen Shop-Antrag kannst du Produkte im Marketplace verkaufen.</p>
                    </div>
                    <button v-if="sellerCanSell" type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="productCreateModal = true">
                        Produkt erstellen
                    </button>
                </div>
                <div v-if="sellerCanSell" class="mt-5 grid gap-3 rounded-lg border border-border bg-bg p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-air-blue">Excel-Import</p>
                            <h3 class="mt-1 font-semibold text-primary">Viele Angebote auf einmal hochladen</h3>
                            <p class="mt-1 text-sm text-secondary">
                                Lade die Vorlage herunter. Preise bleiben in EUR, die Airmius-Provision wird in der Tabelle automatisch je Kategorie mitgerechnet.
                            </p>
                            <p class="mt-1 text-xs text-secondary">Bilder werden per Hauptbild-URL und Galerie-URLs importiert.</p>
                        </div>
                        <a :href="route('auth.commerce.products.import-template')" class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                            Vorlage herunterladen
                        </a>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="importProducts">
                        <input
                            type="file"
                            accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary"
                            @change="setProductImportFile"
                        >
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="productImportForm.processing || !productImportForm.import_file">
                            Importieren
                        </button>
                    </form>
                    <p v-if="productImportForm.errors.import_file" class="text-sm text-error">{{ productImportForm.errors.import_file }}</p>
                </div>
                <div v-if="!sellerCanSell" class="mt-5 rounded-lg border border-border bg-bg p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-air-blue">Shop-Antrag</p>
                            <h3 class="mt-1 font-semibold text-primary">Verkaeufer-Zugang beantragen</h3>
                            <p class="mt-1 text-sm text-secondary">Status: {{ sellerApplicationStatusLabel(sellerApplication?.status) }}</p>
                            <p v-if="sellerApplication?.review_note" class="mt-2 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">{{ sellerApplication.review_note }}</p>
                        </div>
                    </div>

                    <form class="mt-4 grid gap-3" @submit.prevent="storeSellerApplication">
                        <select v-model="sellerApplicationForm.applicant_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="private">Privatperson</option>
                            <option value="business">Gewerblicher Anbieter</option>
                            <option value="club">Verein / Organisation</option>
                        </select>
                        <p v-if="sellerApplicationForm.errors.applicant_type" class="text-sm text-error">{{ sellerApplicationForm.errors.applicant_type }}</p>
                        <input v-model="sellerApplicationForm.business_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Shop-/Firmenname optional">
                        <textarea v-model="sellerApplicationForm.notes" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurz beschreiben, was du verkaufen moechtest"></textarea>

                        <div class="grid gap-2 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_product_truth" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>Ich bestaetige, dass Preise, Bilder, Bestand und Beschreibung korrekt sind.</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_rights" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>Ich habe die Rechte an Bildern, Texten und angebotenen Leistungen.</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_shipping_returns" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>Ich beachte Versand-, Rueckgabe- und Kundenservice-Pflichten.</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_commission" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>Ich akzeptiere Marketplace-Provisionen und Auszahlungspruefung.</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_data_privacy" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>Ich gehe sorgsam mit Kundendaten um und nutze sie nur fuer die Bestellung.</span>
                            </label>
                        </div>
                        <div v-if="Object.keys(sellerApplicationForm.errors).length" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                            Bitte bestaetige alle Regeln, bevor du den Shop-Antrag absendest.
                        </div>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="sellerApplicationForm.processing">
                            Shop-Antrag senden
                        </button>
                    </form>
                </div>
                <div v-if="sellerCanSell && productCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
                    <form class="relative max-h-[90dvh] w-full max-w-4xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="storeProduct">
                        <button
                            type="button"
                            class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                            aria-label="Produkt erstellen schliessen"
                            @click="productCreateModal = false"
                        >
                            <i class="las la-times text-xl"></i>
                        </button>
                        <div class="mb-4 pr-12">
                            <p class="text-xs font-semibold uppercase text-air-blue">Verkaufen</p>
                            <h2 class="mt-1 text-lg font-semibold text-primary">Produkt erstellen</h2>
                            <p class="mt-1 text-sm text-secondary">Das Angebot geht danach zur Pruefung und wird erst nach Freigabe im Marketplace angezeigt.</p>
                        </div>
                        <div class="grid gap-3">
                    <div v-if="Object.keys(productForm.errors).length" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                        Bitte prüfe die markierten Angaben. Pflichtfelder wie Titel, Kategorie und Preis müssen ausgefüllt sein.
                    </div>
                    <select v-model="productForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="">Privat / Anbieter</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <p v-if="productForm.errors.club_id" class="text-sm text-error">{{ productForm.errors.club_id }}</p>
                    <input v-model="productForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                    <p v-if="productForm.errors.title" class="text-sm text-error">{{ productForm.errors.title }}</p>
                    <div class="grid gap-3 rounded-lg border border-border bg-bg p-3">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Hauptbild per URL</label>
                            <input v-model="productForm.image_url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="https://...">
                            <p v-if="productForm.errors.image_url" class="mt-1 text-sm text-error">{{ productForm.errors.image_url }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Hauptbild hochladen</label>
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setProductImageUpload">
                            <p class="mt-1 text-xs text-secondary">JPG, PNG oder WebP. Upload ersetzt die URL.</p>
                            <p v-if="productForm.errors.image_upload" class="mt-1 text-sm text-error">{{ productForm.errors.image_upload }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Weitere Bild-URLs</label>
                            <textarea v-model="productForm.image_urls_text" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Eine URL pro Zeile"></textarea>
                            <p v-if="productForm.errors.image_urls_text" class="mt-1 text-sm text-error">{{ productForm.errors.image_urls_text }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Weitere Bilder hochladen</label>
                            <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setProductGalleryUploads">
                            <p class="mt-1 text-xs text-secondary">Bis zu 8 Dateien, Galerie maximal 12 Bilder.</p>
                            <p v-if="productForm.errors.image_uploads" class="mt-1 text-sm text-error">{{ productForm.errors.image_uploads }}</p>
                        </div>
                    </div>
                    <label class="grid gap-1">
                        <span class="text-xs font-semibold uppercase text-secondary">Kategorie des Angebots</span>
                        <select v-model="productForm.offer_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="physical_product">Produkt / Equipment</option>
                            <option value="online_course">Kurs / E-Learning</option>
                            <option value="training_plan">Trainingsplan mit Feedback</option>
                            <option value="camp">Camp / Workshop</option>
                        </select>
                        <span class="text-xs text-secondary">Diese Auswahl bestimmt, in welchem Marketplace-Bereich das Angebot nach Freigabe erscheint.</span>
                    </label>
                    <p v-if="productForm.errors.offer_type" class="text-sm text-error">{{ productForm.errors.offer_type }}</p>
                    <label class="grid gap-1">
                        <span class="text-xs font-semibold uppercase text-secondary">Marketplace-Kategorie</span>
                        <select v-model="productForm.category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="category in sellerMarketplaceCategories" :key="category.category" :value="category.category">
                                {{ category.label }}
                            </option>
                        </select>
                        <span class="text-xs text-secondary">Die Kategorie bestimmt die externe Marketplace-Provision. Dienstleistungen werden intern von Airmius angelegt.</span>
                    </label>
                    <p v-if="productForm.errors.category" class="text-sm text-error">{{ productForm.errors.category }}</p>
                    <input v-model="productForm.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer">
                    <p v-if="productForm.errors.sku" class="text-sm text-error">{{ productForm.errors.sku }}</p>
                    <select v-if="productForm.offer_type === 'physical_product'" v-model="productForm.product_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="single">Einfaches Produkt</option>
                        <option value="variable">Variables Produkt</option>
                        <option value="digital">Immaterial / digital</option>
                    </select>
                    <p v-if="productForm.errors.product_type" class="text-sm text-error">{{ productForm.errors.product_type }}</p>
                    <textarea
                        v-if="productForm.product_type === 'digital'"
                        v-model="productForm.digital_delivery_note"
                        rows="2"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Lieferinfo, z. B. Ticketcode oder Zugang wird per E-Mail versendet"
                    ></textarea>
                    <p v-if="productForm.errors.digital_delivery_note" class="text-sm text-error">{{ productForm.errors.digital_delivery_note }}</p>
                    <div v-if="isLearningOffer" class="grid gap-3 rounded-lg border border-border bg-bg p-3">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Mit Sportschule-Kurs verknuepfen</label>
                            <select v-model="productForm.learning_course_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="">Keinen Kurs automatisch freischalten</option>
                                <option v-for="course in learningCourses" :key="course.id" :value="course.id">
                                    {{ course.title }} - {{ course.status }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-secondary">Nach bezahlter Bestellung wird der verknuepfte Kurs automatisch fuer den Kaeufer freigeschaltet.</p>
                            <p v-if="productForm.errors.learning_course_id" class="mt-1 text-sm text-error">{{ productForm.errors.learning_course_id }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Kursinhalt / Module</label>
                            <textarea v-model="productForm.course_outline_text" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Eine Lektion oder Trainingsphase pro Zeile"></textarea>
                            <p v-if="productForm.errors.course_outline_text" class="mt-1 text-sm text-error">{{ productForm.errors.course_outline_text }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Lernziele</label>
                            <textarea v-model="productForm.learning_goals_text" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ein Lernziel pro Zeile"></textarea>
                            <p v-if="productForm.errors.learning_goals_text" class="mt-1 text-sm text-error">{{ productForm.errors.learning_goals_text }}</p>
                        </div>
                        <label v-if="productForm.offer_type === 'training_plan'" class="flex items-center gap-2 text-sm text-primary">
                            <input v-model="productForm.coaching_enabled" type="checkbox" class="rounded border-border bg-inputBg">
                            Athleten-Feedback aktivieren
                        </label>
                        <textarea
                            v-if="productForm.offer_type === 'training_plan'"
                            v-model="productForm.coach_feedback_instructions"
                            rows="3"
                            class="rounded-lg border-border bg-inputBg text-sm text-primary"
                            placeholder="Was sollen Athleten als Fortschritt senden? Bilder, Notizen, Belastung, Fragen ..."
                        ></textarea>
                        <p v-if="productForm.errors.coach_feedback_instructions" class="text-sm text-error">{{ productForm.errors.coach_feedback_instructions }}</p>
                    </div>
                    <select v-model="productForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="standard">Standardsteuer</option>
                        <option value="reduced">Ermäßigt</option>
                        <option value="zero">Nullsatz</option>
                    </select>
                    <p v-if="productForm.errors.tax_class" class="text-sm text-error">{{ productForm.errors.tax_class }}</p>
                    <input v-model="productForm.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR, z. B. 10,99">
                    <p v-if="productForm.errors.price_cents" class="text-sm text-error">{{ productForm.errors.price_cents }}</p>
                    <div class="rounded-lg border border-air-blue/30 bg-air-blue/10 p-3 text-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase text-air-blue">Airmius-Provision</p>
                                <p class="mt-1 font-semibold text-primary">
                                    {{ selectedProductCommission.label }}: {{ selectedProductCommission.commission_percent }} %
                                </p>
                                <p class="mt-1 text-xs text-secondary">Die Provision wird nach der gewaehlten Kategorie berechnet und intern am Produkt gespeichert.</p>
                            </div>
                            <div class="grid gap-1 text-right">
                                <p class="text-xs text-secondary">Provision bei diesem Preis</p>
                                <p class="font-bold text-primary">{{ formatMoney(productCommissionPreviewCents) }}</p>
                                <p class="text-xs text-secondary">Voraussichtliche Auszahlung: {{ formatMoney(productSellerPayoutPreviewCents) }}</p>
                            </div>
                        </div>
                    </div>
                    <label v-if="productForm.product_type !== 'digital'" class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="productForm.is_shippable" type="checkbox" class="rounded border-border bg-inputBg">
                        Versandpflichtig
                    </label>
                    <label v-if="productForm.product_type !== 'digital' && !productStockRequired" class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="productForm.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
                        Lagerbestand verwalten
                    </label>
                    <label v-if="productStockRequired" class="grid gap-1">
                        <span class="text-xs font-semibold uppercase text-secondary">Gesamtbestand *</span>
                        <input v-model="productForm.stock_quantity" type="number" min="1" required class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Mindestens 1">
                    </label>
                    <input v-else-if="productForm.manages_stock" v-model="productForm.stock_quantity" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerbestand">
                    <p v-if="productForm.errors.stock_quantity" class="text-sm text-error">{{ productForm.errors.stock_quantity }}</p>
                    <div v-if="productForm.manages_stock && productForm.product_type !== 'digital'" class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Laenderbestand</h3>
                                <p class="text-xs text-secondary">Nur Laender mit aktivem Bestand werden im internationalen Marketplace angeboten.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addProductInventoryRow">
                                Land hinzufuegen
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(inventory, index) in productForm.inventories" :key="index" class="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-6">
                                <select v-model="inventory.country_code" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-for="country in inventoryCountries" :key="country" :value="country">{{ country }}</option>
                                </select>
                                <input v-model="inventory.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" placeholder="Bestand">
                                <input v-model="inventory.low_stock_threshold" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" placeholder="Warnbestand">
                                <input v-model="inventory.lead_time_days" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" placeholder="Lieferzeit Tage">
                                <input v-model="inventory.city" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerstadt">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="removeProductInventoryRow(index)">
                                    Entfernen
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-secondary">Aktueller Gesamtbestand aus Laendern: {{ inventoryTotalStock }}</p>
                        <p v-if="productForm.errors.inventories" class="mt-2 text-sm text-error">{{ productForm.errors.inventories }}</p>
                    </div>
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
                    <p v-if="productForm.errors.description" class="text-sm text-error">{{ productForm.errors.description }}</p>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Zur Prüfung einreichen</button>
                        </div>
                    </form>
                </div>
                <p class="mt-4 text-sm text-secondary">{{ myProducts.length }} eigene Angebote</p>

                <div class="mt-4 space-y-3">
                    <article
                        v-for="product in myProducts"
                        :key="product.id"
                        class="rounded-lg border border-border bg-bg p-4"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-primary">{{ product.title }}</h3>
                                    <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">
                                        {{ offerTypeLabel(product.offer_type, product.category) }}
                                    </span>
                                    <span class="rounded-full bg-air-blue/10 px-2 py-1 text-xs font-semibold text-air-blue">
                                        {{ productStatusLabel(product.status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ product.sku || 'Keine Artikelnummer' }} · erstellt {{ formatDateTime(product.created_at) }}
                                </p>
                                <p v-if="product.rejection_reason" class="mt-2 rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                                    {{ product.rejection_reason }}
                                </p>
                            </div>
                            <div class="shrink-0 text-left sm:text-right">
                                <p class="font-semibold text-primary">{{ formatMoney(product.price_cents, product.currency) }}</p>
                                <p class="text-xs text-secondary">
                                    <span v-if="product.manages_stock">Bestand: {{ product.stock_quantity ?? 0 }}</span>
                                    <span v-else>Kein Lagerlimit</span>
                                </p>
                                <div v-if="product.inventories?.length" class="mt-2 flex flex-wrap gap-1">
                                    <span
                                        v-for="inventory in product.inventories.filter((row) => row.is_active)"
                                        :key="inventory.id"
                                        class="rounded-full border border-border px-2 py-1 text-[11px] font-semibold text-secondary"
                                    >
                                        {{ inventory.country_code }}: {{ inventory.stock_quantity }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link :href="route('auth.commerce.products.show', product.id)" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                                Details
                            </Link>
                            <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="openEditProductModal(product)">
                                Bearbeiten
                            </button>
                            <button
                                v-if="product.status !== 'review'"
                                type="button"
                                class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                @click="updateOwnProductStatus(product, 'review')"
                            >
                                Zur Pruefung
                            </button>
                            <button
                                v-if="product.status !== 'draft'"
                                type="button"
                                class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                @click="updateOwnProductStatus(product, 'draft')"
                            >
                                Entwurf
                            </button>
                            <button
                                v-if="product.status !== 'archived'"
                                type="button"
                                class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning"
                                @click="updateOwnProductStatus(product, 'archived')"
                            >
                                Archivieren
                            </button>
                            <button type="button" class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger" @click="openDeleteProductModal(product)">
                                Loeschen
                            </button>
                        </div>
                    </article>

                    <p v-if="!myProducts.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                        Noch keine eigenen Angebote eingereicht.
                    </p>
                </div>
            </article>

            <article v-if="clubs.length" class="surface-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Vereinswebsite</h2>
                        <p class="mt-1 text-sm text-secondary">Website-Anfragen sind nur fuer Vereine sichtbar, fuer die du berechtigt bist.</p>
                    </div>
                    <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openWebsiteRequestModal">
                        Website anfragen
                    </button>
                </div>
                <p class="mt-4 text-sm text-secondary">{{ websiteRequests.length }} Website-Anfragen</p>

                <div v-if="websiteRequestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
                    <form class="relative max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="storeWebsiteRequest">
                        <button
                            type="button"
                            class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                            aria-label="Website-Anfrage schliessen"
                            @click="websiteRequestModal = false"
                        >
                            <i class="las la-times text-xl"></i>
                        </button>
                        <div class="mb-4 pr-12">
                            <p class="text-xs font-semibold uppercase text-air-blue">Verein</p>
                            <h2 class="mt-1 text-lg font-semibold text-primary">Vereinswebsite erstellen lassen</h2>
                            <p class="mt-1 text-sm text-secondary">Waehle den Verein und beschreibe kurz die gewuenschte Website.</p>
                        </div>
                        <div class="grid gap-3">
                    <select v-model="websiteForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <input v-model="websiteForm.domain" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gewuenschte Domain">
                    <textarea v-model="websiteForm.goals" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Was soll die Website können?"></textarea>
                    <textarea v-model="websiteForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Weitere Hinweise"></textarea>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Anfrage senden</button>
                        </div>
                    </form>
                </div>
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

                <div class="mt-6 rounded-lg border border-border bg-bg p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-primary">Auszahlung anfordern</h3>
                            <p class="mt-1 text-sm text-secondary">Auszahlbar sind nur abgeschlossene Verkäufe, die seit mindestens 14 Tagen ohne Beschwerde und ohne Rücksendung abgeschlossen sind.</p>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">{{ payoutSummary.eligible_orders || 0 }} auszahlbar</span>
                    </div>
                    <div v-if="payoutRequestForm.errors.payout" class="mt-3 rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                        {{ payoutRequestForm.errors.payout }}
                    </div>
                    <form class="mt-4 grid gap-3 md:grid-cols-[12rem_minmax(0,1fr)_auto]" @submit.prevent="requestPayout">
                        <select v-model="payoutRequestForm.method" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="bank_transfer">Bankueberweisung</option>
                            <option value="paypal">PayPal</option>
                        </select>
                        <input v-model="payoutRequestForm.notes" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Hinweis optional">
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="payoutRequestForm.processing || !Number(payoutSummary.amount_cents || 0)">
                            Auszahlung anfordern
                        </button>
                    </form>
                </div>

                <div class="mt-6 overflow-hidden rounded-lg border border-border">
                    <div class="border-b border-border bg-bg px-4 py-3">
                        <h3 class="font-semibold text-primary">Auszahlungshistorie</h3>
                    </div>
                    <div class="divide-y divide-border">
                        <div v-for="payout in myPayouts" :key="payout.id" class="grid gap-2 p-4 text-sm md:grid-cols-[1fr_auto_auto] md:items-center">
                            <div>
                                <p class="font-semibold text-primary">{{ payout.reference || `Auszahlung #${payout.id}` }}</p>
                                <p class="text-xs text-secondary">{{ payout.orders_count || 0 }} Bestellungen · {{ payout.method }}</p>
                            </div>
                            <p class="font-semibold text-primary">{{ formatMoney(payout.amount_cents) }}</p>
                            <span class="justify-self-start rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary md:justify-self-end">{{ payoutStatusLabel(payout.status) }}</span>
                        </div>
                        <p v-if="!myPayouts.length" class="p-4 text-sm text-secondary">Noch keine Auszahlungen angefordert.</p>
                    </div>
                </div>
            </article>

            <aside class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Auszahlbar</h2>
                <div class="mt-4 space-y-3">
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Auszahlbare Bestellungen</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ payoutSummary.eligible_orders || 0 }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ payoutSummary.waiting_orders || 0 }} warten noch auf 14 Tage, Zustellung oder Klärung.</p>
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
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Bereits angefordert</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.requested_cents) }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ payoutSummary.requested_count || 0 }} offene Auszahlungsantraege</p>
                    </div>
                </div>
            </aside>
        </section>

        <section v-show="activeTab === 'ads'" class="grid gap-6 xl:grid-cols-[28rem_minmax(0,1fr)]">
            <article class="surface-card p-5">
                <p class="text-xs font-semibold uppercase text-air-blue">Schritt 1</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Kampagne erstellen</h2>
                <p class="mt-1 text-sm text-secondary">Erstelle zuerst den Container. Anzeigegruppen, Zielgruppen, Anzeigen und Varianten kommen danach darunter.</p>
                <form class="mt-4 grid gap-3" @submit.prevent="storeCampaign">
                    <input v-model="campaignForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kampagnenname">
                    <input v-model="campaignForm.headline" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Headline, max. 120 Zeichen">
                    <input v-model="campaignForm.target_url" type="url" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL">
                    <textarea v-model="campaignForm.primary_text" rows="3" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anzeigentext / Primary Text"></textarea>
                    <textarea v-model="campaignForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interne Beschreibung oder Kampagnenziel"></textarea>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Ziel</label>
                            <select v-model="campaignForm.objective" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="traffic">Traffic</option>
                                <option value="awareness">Reichweite</option>
                                <option value="leads">Leads</option>
                                <option value="sales">Sales</option>
                            </select>
                        </div>
                        <div class="hidden">
                            <label class="text-xs font-semibold uppercase text-secondary">Placement - wo erscheint die Ad?</label>
                            <select v-model="campaignForm.placement" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="placement in adPlacements" :key="placement.key" :value="placement.key">{{ placement.label }}</option>
                            </select>
                            <p class="mt-1 text-xs text-secondary">{{ selectedAdPlacement.hint }}</p>
                        </div>
                    </div>
                    <div class="hidden rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">Creative Format und Bildmaße</label>
                        <select v-model="campaignForm.creative_format" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="format in campaignAdFormats" :key="format.key" :value="format.key">
                                {{ format.label }} - {{ format.size }}
                            </option>
                        </select>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ selectedAdFormat.size }} · {{ selectedAdFormat.ratio }}</p>
                        <p class="text-xs text-secondary">{{ selectedAdFormat.hint }}</p>
                        <p class="mt-2 rounded-lg border border-border bg-card px-3 py-2 text-xs text-secondary">
                            Placement entscheidet den Ort. Creative Format entscheidet nur Groesse und Seitenverhaeltnis der Anzeige.
                        </p>
                    </div>
                    <input v-model="campaignForm.creative_image_url" type="url" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL optional">
                    <div class="hidden rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">Bild hochladen</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setCampaignCreativeUpload">
                        <p class="mt-1 text-xs text-secondary">JPG, PNG oder WebP bis 8 MB. Empfohlen: {{ selectedAdFormat.size }}.</p>
                    </div>
                    <div class="hidden rounded-lg border border-border bg-bg p-3">
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
                        <input v-model="campaignForm.daily_budget_cents" v-bind="moneyInputAttrs" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tagesbudget in EUR">
                        <input v-model="campaignForm.starts_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="campaignForm.ends_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                    <div class="hidden grid gap-3 sm:grid-cols-2">
                        <input v-model="campaignForm.audience_age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von">
                        <input v-model="campaignForm.audience_age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis">
                    </div>
                    <input v-model="campaignForm.audience_locations" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Regionen, z. B. Berlin, NRW">
                    <input v-model="campaignForm.audience_interests" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interessen, z. B. Fußball, Fitness">
                    <input v-model="campaignForm.cta_label" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA, z. B. Jetzt ansehen">
                    <div class="hidden rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">Zahlungsart</label>
                        <select v-model="adProvider" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="bank_transfer">Ueberweisung</option>
                            <option value="stripe">Stripe</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                    <label class="hidden items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                        <input v-model="adAcceptedTerms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            Ich akzeptiere AGB, Widerrufshinweise und nehme zur Kenntnis, dass die Kampagne erst nach Zahlung zur Pruefung eingereicht wird.
                            <Link :href="route('terms.show')" class="text-air-blue underline">AGB</Link>
                            <span> - </span>
                            <Link :href="route('legal.withdrawal')" class="text-air-blue underline">Widerruf</Link>
                        </span>
                    </label>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="campaignForm.processing">
                        Kampagne speichern
                    </button>
                    <div v-if="campaignCreateError || campaignCreateErrors.length" class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        <p v-if="campaignCreateError" class="font-semibold">{{ campaignCreateError }}</p>
                        <ul v-if="campaignCreateErrors.length" class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="error in campaignCreateErrors" :key="error">{{ error }}</li>
                        </ul>
                    </div>
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
                                <th class="px-5 py-3">Erstellt</th>
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
                                <td class="px-5 py-3 text-secondary">{{ formatDateTime(campaign.created_at) }}</td>
                                <td class="px-5 py-3 text-secondary">{{ campaign.impressions || 0 }}</td>
                                <td class="px-5 py-3 text-secondary">{{ campaign.clicks || 0 }}</td>
                                <td class="px-5 py-3 text-secondary">{{ formatPercent(ctr(campaign.clicks, campaign.impressions)) }}</td>
                                <td class="px-5 py-3 text-secondary">{{ formatMoney(campaign.budget_cents) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button
                                            v-if="campaign.status !== 'completed'"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="openEditCampaignModal(campaign)"
                                        >
                                            Bearbeiten
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                                            @click="openAdGroupModal(campaign)"
                                        >
                                            Anzeigegruppe
                                        </button>
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
                            <tr v-if="campaign.groups?.length">
                                <td colspan="8" class="bg-bg px-5 py-3">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold uppercase text-secondary">Anzeigegruppen</p>
                                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="openAdGroupModal(campaign)">
                                            Weitere Gruppe
                                        </button>
                                    </div>
                                    <div class="grid gap-2 lg:grid-cols-2">
                                        <div v-for="group in campaign.groups" :key="group.id" class="rounded-lg border border-border bg-card p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-semibold text-primary">{{ group.name }}</p>
                                                    <p class="text-xs text-secondary">{{ adPlacementLabel(group.placement) }}</p>
                                                </div>
                                                <div class="flex flex-col items-end gap-2">
                                                    <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ group.status }}</span>
                                                    <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="openAdCreativeModal(campaign, group)">
                                                        Anzeige + Varianten
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                                                <p><span class="text-secondary">Sportart:</span> <span class="text-primary">{{ adGroupAudienceSportsText(group) || '-' }}</span></p>
                                                <p><span class="text-secondary">Interessen:</span> <span class="text-primary">{{ adGroupAudienceText(group, 'interests') || '-' }}</span></p>
                                                <p><span class="text-secondary">Alter:</span> <span class="text-primary">{{ group.audience?.age_min || '-' }} bis {{ group.audience?.age_max || '-' }}</span></p>
                                                <p><span class="text-secondary">Geschlecht:</span> <span class="text-primary">{{ group.audience?.gender || 'all' }}</span></p>
                                                <p><span class="text-secondary">Ort/Region:</span> <span class="text-primary">{{ adGroupAudienceText(group, 'locations') || '-' }}</span></p>
                                                <p><span class="text-secondary">Zone:</span> <span class="text-primary">{{ adGroupAudienceText(group, 'zones') || '-' }}</span></p>
                                            </div>
                                            <p class="mt-3 text-xs text-secondary">Tagesbudget: {{ formatMoney(group.daily_budget_cents) }}</p>
                                            <div v-if="group.creatives?.length" class="mt-4 space-y-2">
                                                <p class="text-xs font-semibold uppercase text-secondary">Anzeigen / A-B Varianten</p>
                                                <div v-for="creative in group.creatives" :key="creative.id" class="rounded-lg border border-border bg-bg p-3">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <p class="text-xs font-semibold uppercase text-air-blue">{{ creative.ad_name || 'Anzeige' }}</p>
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
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="campaignRootCreatives(campaign).length">
                                <td colspan="8" class="bg-bg px-5 py-3">
                                    <div class="grid gap-2 md:grid-cols-2">
                                        <div v-for="creative in campaignRootCreatives(campaign)" :key="creative.id" class="rounded-lg border border-border bg-card p-3">
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

        <section v-show="activeTab === 'invoices'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Zentrale Uebersicht</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Rechnungen und Einkaeufe</h2>
                <p class="mt-1 text-sm text-secondary">Hier stehen Ads, Marketplace, Kurse, Outfit-Abos und Konto-Abos zusammen.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Bereich</th>
                            <th class="px-5 py-3">Titel</th>
                            <th class="px-5 py-3">Datum</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Betrag</th>
                            <th class="px-5 py-3 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="purchase in purchaseHistory" :key="purchase.key">
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-bg px-2.5 py-1 text-xs font-semibold text-secondary">{{ purchase.category }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ purchase.title }}</p>
                                <p v-if="purchase.description" class="text-xs text-secondary">{{ purchase.description }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ formatDateTime(purchase.ordered_at) }}</td>
                            <td class="px-5 py-3 text-secondary">{{ purchase.status_label }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-primary">{{ formatMoney(purchase.amount_cents, purchase.currency) }}</td>
                            <td class="px-5 py-3 text-right">
                                <Link v-if="purchase.learning_course?.url" :href="purchase.learning_course.url" class="mr-2 rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success">
                                    Zum Kurs
                                </Link>
                                <a v-if="purchase.invoice_url" :href="purchase.invoice_url" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                                    Rechnung
                                </a>
                                <button v-else-if="purchase.detail_url" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="openPurchaseDetails(purchase)">
                                    Details
                                </button>
                                <span v-else class="text-xs text-secondary">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!purchaseHistory.length" class="px-5 py-6 text-sm text-secondary">Noch keine Einkaeufe oder Rechnungen vorhanden.</p>
            </div>
        </section>

        <section v-show="activeTab === 'invoices'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Bestellungen, Probleme und Ruecksendungen</h2>
                <p class="mt-1 text-sm text-secondary">Fuer Marketplace-Bestellungen kannst du hier Rechnungen laden, Probleme melden und Ruecksendungen verfolgen.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="order in activeOrders"
                            :id="orderRowId(order.id)"
                            :key="order.id"
                            class="transition"
                            :class="String(focusedOrderId) === String(order.id) ? 'bg-air-blue/10' : ''"
                        >
                            <td class="px-5 py-3 font-semibold text-primary">{{ order.orderable?.name || order.orderable?.title || order.type }}</td>
                            <td class="px-5 py-3 text-secondary">
                                <p class="font-semibold text-primary">{{ orderPaymentLabel(order) }}</p>
                                <p v-if="orderPaymentHint(order)" class="text-xs text-secondary">{{ orderPaymentHint(order) }}</p>
                                <p class="text-xs text-secondary">Versand: {{ orderShippingLabel(order.shipping_status) }}</p>
                                <p v-if="orderCanCancel(order)" class="mt-1 text-xs text-secondary">Noch nicht versendet: Storno ist moeglich.</p>
                                <p v-else-if="order.shipping_status === 'shipped'" class="mt-1 text-xs text-secondary">Bereits versendet: Storno ist nicht mehr moeglich. Nach Zustellung kannst du eine Ruecksendung anfragen.</p>
                                <div v-if="order.issue_status && order.issue_status !== 'none'" class="mt-3 rounded-lg border border-warning/30 bg-warning/10 p-3">
                                    <p class="text-xs font-semibold uppercase text-warning">{{ orderIssueLabel(order.issue_status) }}</p>
                                    <p v-if="order.issue_note" class="mt-1 text-xs text-secondary">Deine Meldung: {{ order.issue_note }}</p>
                                    <p v-if="order.issue_response" class="mt-2 text-sm font-semibold text-primary">Antwort: {{ order.issue_response }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ formatMoney(order.amount_cents, order.currency) }}</td>
                            <td class="px-5 py-3 text-right">
                                <Link v-if="order.learning_course?.url" :href="order.learning_course.url" class="mr-2 rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success">
                                    Zum Kurs
                                </Link>
                                <a v-if="order.invoice_number" :href="route('auth.commerce.orders.invoice', order.id)" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                                    Rechnung
                                </a>
                                <a v-if="order.credit_note_number" :href="route('auth.commerce.orders.credit-note', order.id)" class="ml-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                                    Gutschrift
                                </a>
                                <button v-if="order.status === 'completed'" class="ml-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="openIssueModal(order, 'issue')">
                                    Problem melden
                                </button>
                                <button v-if="orderCanCancel(order)" class="ml-2 rounded-lg border border-red-500/50 px-3 py-2 text-xs font-semibold text-red-300 hover:bg-red-500/10" @click="cancelOrder(order)">
                                    Stornieren
                                </button>
                                <button v-if="orderCanReturn(order)" class="ml-2 rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="openIssueModal(order, 'return')">
                                    Rücksendung
                                </button>
                                <span v-else-if="order.issue_status && order.issue_status !== 'none'" class="text-xs text-secondary">{{ orderIssueLabel(order.issue_status) }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!activeOrders.length" class="px-5 py-6 text-sm text-secondary">Noch keine Bestellungen.</p>
            </div>
        </section>

        <section v-show="activeTab === 'invoices'" class="surface-card overflow-hidden">
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

        <div v-if="editProductModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <form class="relative max-h-[90dvh] w-full max-w-2xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="submitEditProduct">
                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                    aria-label="Produkt schliessen"
                    @click="closeEditProductModal"
                >
                    <i class="las la-times text-xl"></i>
                </button>
                <div class="pr-12">
                    <p class="text-xs font-semibold uppercase text-air-blue">Verkaufen</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Produkt bearbeiten</h2>
                    <p class="mt-1 text-sm text-secondary">Aenderungen werden danach erneut geprueft, bevor sie im Marketplace sichtbar sind.</p>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <input v-model="editProductForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                    <input v-model="editProductForm.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR">
                    <input v-model="editProductForm.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer">
                    <input v-model="editProductForm.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL">
                    <label class="rounded-lg border border-border bg-bg p-3 text-sm text-secondary md:col-span-2">
                        <span class="block text-xs font-semibold uppercase text-secondary">Bild hochladen</span>
                        <input type="file" accept="image/*" class="mt-2 text-sm text-primary" @change="editProductForm.image_upload = $event.target.files?.[0] || null">
                    </label>
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                        <input v-model="editProductForm.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
                        Bestand verwalten
                    </label>
                    <input v-if="editProductForm.manages_stock" v-model="editProductForm.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bestand">
                    <textarea v-model="editProductForm.description" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" placeholder="Beschreibung"></textarea>
                </div>
                <div v-if="editProductForm.manages_stock" class="mt-4 rounded-lg border border-border bg-bg p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">Laenderbestand</h3>
                            <p class="text-xs text-secondary">Steuert, in welchen Laendern dein Produkt sichtbar und kaufbar ist.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addEditProductInventoryRow">
                            Land hinzufuegen
                        </button>
                    </div>
                    <div class="mt-3 space-y-3">
                        <div v-for="(inventory, index) in editProductForm.inventories" :key="index" class="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-6">
                            <select v-model="inventory.country_code" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="country in inventoryCountries" :key="country" :value="country">{{ country }}</option>
                            </select>
                            <input v-model="inventory.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bestand">
                            <input v-model="inventory.low_stock_threshold" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Warnbestand">
                            <input v-model="inventory.lead_time_days" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lieferzeit">
                            <input v-model="inventory.city" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerstadt">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="removeEditProductInventoryRow(index)">
                                Entfernen
                            </button>
                        </div>
                    </div>
                    <p v-if="!editProductForm.inventories.length" class="mt-3 text-sm text-secondary">Noch kein Laenderbestand gepflegt.</p>
                </div>

                <div v-if="Object.keys(editProductForm.errors || {}).length" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                    <p v-for="(error, key) in editProductForm.errors" :key="key">{{ error }}</p>
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeEditProductModal">Abbrechen</button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="editProductForm.processing">
                        Speichern
                    </button>
                </div>
            </form>
        </div>

        <div v-if="deleteProductModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-semibold text-primary">Produkt loeschen</h2>
                <p class="mt-2 text-sm text-secondary">
                    Gib <span class="font-semibold text-primary">delete</span> ein. Wenn es bereits Bestellungen gibt, wird das Produkt archiviert statt geloescht.
                </p>
                <p class="mt-3 rounded-lg border border-border bg-bg p-3 text-sm font-semibold text-primary">{{ deleteProductModal.product?.title }}</p>
                <input v-model="deleteProductModal.confirmation" class="mt-4 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="delete">
                <div class="mt-5 flex justify-end gap-3">
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeDeleteProductModal">Abbrechen</button>
                    <button class="rounded-lg border border-danger/50 px-4 py-2 text-sm font-semibold text-danger" :disabled="deleteProductModal.confirmation !== 'delete'" @click="destroyOwnProduct">
                        Loeschen
                    </button>
                </div>
            </div>
        </div>

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

        <div v-if="adGroupModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <form class="relative max-h-[90dvh] w-full max-w-2xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="storeAdGroup">
                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                    aria-label="Anzeigegruppe schliessen"
                    @click="closeAdGroupModal"
                >
                    <i class="las la-times text-xl"></i>
                </button>
                <div class="pr-12">
                    <p class="text-xs font-semibold uppercase text-air-blue">Schritt 2</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Anzeigegruppe erstellen</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Kampagne: {{ adGroupModal.campaign?.name }}. Zielgruppe und Placement werden hier definiert.
                    </p>
                </div>

                <div class="mt-5 grid gap-3">
                    <input v-model="adGroupForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name der Anzeigegruppe">
                    <p v-if="adGroupForm.errors.name" class="text-sm text-error">{{ adGroupForm.errors.name }}</p>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Placement</label>
                        <select v-model="adGroupForm.placement" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="placement in adPlacements" :key="placement.key" :value="placement.key">{{ placement.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <label class="text-xs font-semibold uppercase text-secondary">Sportarten</label>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="sport in selectedAdGroupSports"
                                    :key="sport"
                                    class="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1 text-xs font-semibold text-primary"
                                >
                                    {{ sportLabel(sport) }}
                                    <button type="button" class="text-secondary hover:text-danger" @click="removeAdGroupSport(sport)">
                                        <i class="las la-times"></i>
                                    </button>
                                </span>
                                <span v-if="!selectedAdGroupSports.length" class="text-sm text-secondary">Noch keine Sportart gewaehlt.</span>
                            </div>
                            <input
                                v-model="adGroupSportQuery"
                                class="mt-3 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                                placeholder="Sportart suchen und aus Liste waehlen"
                            >
                            <div class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-border bg-card">
                                <button
                                    v-for="sport in filteredAdGroupSports"
                                    :key="sport.slug"
                                    type="button"
                                    class="block w-full px-3 py-2 text-left text-sm transition hover:bg-muted"
                                    @click="addAdGroupSport(sport)"
                                >
                                    <span class="block font-semibold text-primary">{{ sport.name }}</span>
                                    <span v-if="sport.category" class="block text-xs text-secondary">{{ sport.category }}</span>
                                </button>
                                <p v-if="!filteredAdGroupSports.length" class="px-3 py-3 text-sm text-secondary">Keine weitere Sportart gefunden.</p>
                            </div>
                            <p v-if="adGroupForm.errors.sports" class="mt-2 text-sm text-error">{{ adGroupForm.errors.sports }}</p>
                        </div>
                        <input v-model="adGroupForm.interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interessen, z. B. Fitness, Ausruestung">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <select v-model="adGroupForm.gender" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="all">Alle Geschlechter</option>
                            <option value="female">Frauen</option>
                            <option value="male">Maenner</option>
                            <option value="diverse">Divers</option>
                        </select>
                        <input v-model="adGroupForm.age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von">
                        <input v-model="adGroupForm.age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="adGroupForm.locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ort/Region, z. B. Saarland, Berlin">
                        <input v-model="adGroupForm.zones" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Zone, z. B. 10 km um Saarbruecken">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <input v-model="adGroupForm.daily_budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tagesbudget in EUR">
                        <input v-model="adGroupForm.starts_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="adGroupForm.ends_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeAdGroupModal">
                        Abbrechen
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="adGroupForm.processing">
                        Anzeigegruppe speichern
                    </button>
                </div>
            </form>
        </div>

        <div v-if="adCreativeModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <form class="relative max-h-[90dvh] w-full max-w-3xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="storeAdCreatives">
                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                    aria-label="Anzeige und Varianten schliessen"
                    @click="closeAdCreativeModal"
                >
                    <i class="las la-times text-xl"></i>
                </button>
                <div class="pr-12">
                    <p class="text-xs font-semibold uppercase text-air-blue">Schritt 3</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Anzeige und A/B-Varianten erstellen</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Kampagne: {{ adCreativeModal.campaign?.name }} · Anzeigegruppe: {{ adCreativeModal.group?.name }}
                    </p>
                </div>

                <div class="mt-5 grid gap-3">
                    <label class="grid gap-1">
                        <span class="text-xs font-semibold uppercase text-secondary">Anzeigenname</span>
                        <input v-model="adCreativeForm.ad_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Sommeraktion Anzeige 1">
                    </label>
                    <p v-if="adCreativeForm.errors.ad_name" class="text-sm text-error">{{ adCreativeForm.errors.ad_name }}</p>

                    <div class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Varianten fuer A/B-Test</label>
                                <p class="mt-1 text-xs text-secondary">Jede Variante kann eigene Headline, Text, Ziel-URL, Bild-URL und Gewicht haben.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addAdCreativeRow">
                                Variante hinzufuegen
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(creative, index) in adCreativeRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-card p-3">
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
                                    <input v-model="creative.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL">
                                    <input v-model="creative.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA, z. B. Jetzt ansehen">
                                </div>
                                <input v-model="creative.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL dieser Variante">
                                <button v-if="adCreativeRows.length > 1" type="button" class="justify-self-start rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="removeAdCreativeRow(index)">
                                    Variante entfernen
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="adCreativeForm.errors.creatives" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                        {{ adCreativeForm.errors.creatives }}
                    </div>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeAdCreativeModal">
                        Abbrechen
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="adCreativeForm.processing">
                        Anzeige speichern
                    </button>
                </div>
            </form>
        </div>

        <div v-if="editCampaignModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <form class="relative max-h-[90dvh] w-full max-w-3xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="submitEditCampaign">
                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                    aria-label="Bearbeiten schliessen"
                    @click="closeEditCampaignModal"
                >
                    <i class="las la-times text-xl"></i>
                </button>
                <div class="pr-12">
                    <h2 class="text-lg font-semibold text-primary">Ads-Kampagne bearbeiten</h2>
                    <p class="mt-2 text-sm text-secondary">
                        Bezahlte oder bereits aktive Kampagnen gehen nach Aenderungen wieder zur Admin-Pruefung.
                    </p>
                </div>

                <div class="mt-5 grid gap-3">
                    <input v-model="editCampaignForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kampagnenname">
                    <p v-if="editCampaignForm.errors.name" class="text-sm text-error">{{ editCampaignForm.errors.name }}</p>
                    <input v-model="editCampaignForm.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Headline, max. 120 Zeichen">
                    <input v-model="editCampaignForm.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL">
                    <p v-if="editCampaignForm.errors.target_url" class="text-sm text-error">{{ editCampaignForm.errors.target_url }}</p>
                    <textarea v-model="editCampaignForm.primary_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anzeigentext / Primary Text"></textarea>
                    <textarea v-model="editCampaignForm.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interne Beschreibung"></textarea>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Ziel</label>
                            <select v-model="editCampaignForm.objective" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="traffic">Traffic</option>
                                <option value="awareness">Reichweite</option>
                                <option value="leads">Leads</option>
                                <option value="sales">Sales</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Placement - wo erscheint die Ad?</label>
                            <select v-model="editCampaignForm.placement" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="placement in adPlacements" :key="placement.key" :value="placement.key">{{ placement.label }}</option>
                            </select>
                            <p class="mt-1 text-xs text-secondary">{{ selectedEditAdPlacement.hint }}</p>
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">Creative Format - welches Bildmass?</label>
                        <select v-model="editCampaignForm.creative_format" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="format in editCampaignAdFormats" :key="format.key" :value="format.key">
                                {{ format.label }} - {{ format.size }}
                            </option>
                        </select>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ selectedEditAdFormat.size }} - {{ selectedEditAdFormat.ratio }}</p>
                        <p class="text-xs text-secondary">{{ selectedEditAdFormat.hint }}</p>
                        <p class="mt-2 rounded-lg border border-border bg-card px-3 py-2 text-xs text-secondary">
                            Placement entscheidet den Ort. Creative Format entscheidet nur Groesse und Seitenverhaeltnis der Anzeige.
                        </p>
                    </div>
                    <input v-model="editCampaignForm.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL optional">
                    <div class="overflow-hidden rounded-lg border border-border bg-bg">
                        <div class="flex items-center justify-between gap-3 border-b border-border px-3 py-2">
                            <span class="text-xs font-semibold uppercase text-secondary">Aktuelles Anzeigenbild</span>
                            <span class="text-xs text-secondary">{{ selectedEditAdFormat.size }}</span>
                        </div>
                        <img
                            v-if="editCampaignPreviewUrl"
                            :src="editCampaignPreviewUrl"
                            :alt="editCampaignForm.headline || editCampaignForm.name || 'Ads Vorschau'"
                            class="max-h-64 w-full bg-inputBg object-contain"
                        >
                        <div v-else class="flex min-h-36 items-center justify-center px-4 py-8 text-center text-sm text-secondary">
                            Noch kein Anzeigenbild hinterlegt.
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">Neues Hauptbild hochladen</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setEditCampaignCreativeUpload">
                    </div>

                    <div class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">A/B-Test Varianten</label>
                                <p class="mt-1 text-xs text-secondary">Bearbeite Headline, Text, Ziel-URL, Bild-URL und Gewicht.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addEditCampaignCreativeRow">
                                Variante hinzufuegen
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(creative, index) in editCampaignCreativeRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-card p-3">
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
                                <div v-if="creativePreviewUrl(creative)" class="overflow-hidden rounded-lg border border-border bg-bg">
                                    <p class="border-b border-border px-3 py-2 text-xs font-semibold uppercase text-secondary">Variantenbild</p>
                                    <img :src="creativePreviewUrl(creative)" :alt="creative.name || 'Variantenbild'" class="max-h-40 w-full bg-inputBg object-contain">
                                </div>
                                <button v-if="editCampaignCreativeRows.length > 1" type="button" class="justify-self-start rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="removeEditCampaignCreativeRow(index)">
                                    Variante entfernen
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="editCampaignForm.budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary disabled:opacity-60" placeholder="Gesamtbudget in EUR" :disabled="editCampaignModal.campaign?.payment_completed">
                        <input v-model="editCampaignForm.daily_budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tagesbudget in EUR">
                        <input v-model="editCampaignForm.starts_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="editCampaignForm.ends_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                    <p v-if="editCampaignModal.campaign?.payment_completed" class="text-xs text-secondary">Das bezahlte Gesamtbudget kann hier nicht nachtraeglich geaendert werden.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="editCampaignForm.audience_age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von">
                        <input v-model="editCampaignForm.audience_age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis">
                    </div>
                    <input v-model="editCampaignForm.audience_locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Regionen, z. B. Berlin, NRW">
                    <input v-model="editCampaignForm.audience_interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interessen, z. B. Fussball, Fitness">
                    <input v-model="editCampaignForm.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA, z. B. Jetzt ansehen">
                </div>

                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeEditCampaignModal">
                        Abbrechen
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="editCampaignForm.processing">
                        Speichern
                    </button>
                </div>
            </form>
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

        <div v-if="checkoutConfirmation.open" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Checkout bestaetigen</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">{{ checkoutConfirmationTitle }}</h2>
                        <p class="mt-2 text-sm text-secondary">
                            {{ checkoutConfirmationPrice }}
                            <span v-if="checkoutConfirmation.type === 'account_plan'">pro {{ interval === 'yearly' ? 'Jahr' : 'Monat' }}</span>
                            <span> · {{ providerLabel(checkoutConfirmation.provider) }}</span>
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeCheckoutConfirmation">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <label class="mt-5 flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                    <input v-model="checkoutConfirmation.accepted" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        Ich akzeptiere AGB, Widerrufshinweise und nehme zur Kenntnis, dass Marketplace-Angebote je nach Produkt durch den jeweiligen Anbieter erbracht werden.
                        <Link :href="route('terms.show')" class="text-air-blue underline">AGB</Link>
                        <span> · </span>
                        <Link :href="route('legal.withdrawal')" class="text-air-blue underline">Widerruf</Link>
                    </span>
                </label>

                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeCheckoutConfirmation">
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!checkoutConfirmation.accepted"
                        @click="confirmCheckout"
                    >
                        Zahlungspflichtig fortfahren
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
