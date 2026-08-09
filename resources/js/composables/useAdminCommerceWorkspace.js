import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { centsToMajor, majorToCents, moneyInputAttrs, transformMoneyFields } from '@/utils/currency'
import { confirmDialog } from '@/services/dialogService'
import { useAdminCommerceProducts } from '@/composables/useAdminCommerceProducts'
import { useI18n } from 'vue-i18n'

export function useAdminCommerceWorkspace(props) {
    const { t, locale } = useI18n()
    const tx = (key, fallback, values = {}) => {
        const translated = t(key, values)
        return translated === key ? fallback : translated
    }
    const page = usePage()
    const queryTab = new URLSearchParams(String(page.url || '').split('?')[1] || '').get('tab')
    const activeTab = ref(queryTab || 'marketplace')
    const campaignStatusError = ref('')
    
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
    
    const providerProfileForm = useForm({
        display_name: props.providerProfile?.display_name || page.props.auth?.user?.name || 'Airmius',
        legal_name: props.providerProfile?.legal_name || '',
        provider_type: props.providerProfile?.provider_type || 'business',
        support_email: props.providerProfile?.support_email || page.props.auth?.user?.email || '',
        phone: props.providerProfile?.phone || '',
        website: props.providerProfile?.website || '',
        logo_url: props.providerProfile?.logo_url || '',
        public_description: props.providerProfile?.public_description || '',
        legal_country: props.providerProfile?.legal_country || 'DE',
        legal_state: props.providerProfile?.legal_state || '',
        legal_postal_code: props.providerProfile?.legal_postal_code || '',
        legal_city: props.providerProfile?.legal_city || '',
        legal_street: props.providerProfile?.legal_street || '',
        legal_house_number: props.providerProfile?.legal_house_number || '',
        show_public_address: Boolean(props.providerProfile?.show_public_address),
        show_support_email: props.providerProfile?.show_support_email ?? true,
        show_phone: Boolean(props.providerProfile?.show_phone),
    })
    
    const providerLocationForm = useForm({
        name: '',
        type: 'pickup',
        country: 'DE',
        state: '',
        postal_code: '',
        city: '',
        street: '',
        house_number: '',
        opening_hours: '',
        note: '',
        phone: '',
        email: '',
        image_url: '',
        latitude: '',
        longitude: '',
        pickup_enabled: true,
        returns_enabled: false,
        is_public: true,
    })
    const editingProviderLocation = ref(null)
    
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
    const {
        stockAdjustments,
        productForm,
        productAttributeRows,
        productFeatureRows,
        productVariantRows,
        rejectionModal,
        productDeleteModal,
        rejectionReasons,
        editProductForm,
        editAttributeRows,
        editFeatureRows,
        editVariantRows,
        attributePresets,
        presetValuesFor,
        normalizeAttributeRows,
        addProductAttributeRow,
        removeProductAttributeRow,
        addProductVariantRow,
        removeProductVariantRow,
        addProductFeatureRow,
        removeProductFeatureRow,
        addEditAttributeRow,
        removeEditAttributeRow,
        addEditVariantRow,
        removeEditVariantRow,
        addEditFeatureRow,
        removeEditFeatureRow,
        setProductImageUpload,
        setProductGalleryUploads,
        setEditProductImageUpload,
        setEditProductGalleryUploads,
        storeProduct,
        updateProductStatus,
        submitRejection,
        openDeleteProduct,
        closeDeleteProduct,
        setProductDeleteConfirmation,
        destroyProduct,
        selectRejectionReason,
        updateProductStock,
        inventoryAdjustment,
        setInventoryAdjustment,
        adjustInventoryStock,
        adjustGlobalStock,
        updateSellerApplication,
        openEditProduct,
        closeEditProduct,
        submitEditProduct,
    } = useAdminCommerceProducts()
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
        idempotency_key: '',
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
    
    const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
    const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
        style: 'currency',
        currency: currency || 'EUR',
    }).format(Number(cents || 0) / 100)
    
    const summaryCards = computed(() => [
        { label: 'Umsatz', value: formatMoney(props.summary.revenue_cents) },
        { label: 'Offen', value: formatMoney(props.summary.open_cents) },
        { label: 'Coupons', value: props.summary.coupons || 0 },
        { label: 'Add-ons', value: props.summary.addons || 0 },
        { label: 'Produkte', value: props.summary.products || 0 },
        { label: 'Kampagnen', value: props.summary.campaigns || 0 },
        { label: 'Orders', value: props.summary.orders || 0 },
        { label: 'Provision', value: formatMoney(props.summary.commission_cents) },
        { label: 'Auszahlung', value: formatMoney(props.summary.payout_cents) },
        { label: 'Websites', value: props.summary.website_requests || 0 },
    ])
    
    const formatDateTime = (value) => {
        if (!value) {
            return '-'
        }
    
        return new Intl.DateTimeFormat(localeCode.value, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value))
    }
    
    const orderPaymentLabel = (order) => {
        if (order.status === 'completed') {
            return order.shipping_status === 'delivered'
                ? tx('commerce.order.status.delivered', 'Abgeschlossen')
                : tx('commerce.order.status.paid', 'Bezahlt')
        }
    
        return {
            pending: tx('commerce.order.status.pending', 'Offen'),
            awaiting_transfer: tx('commerce.order.status.awaiting_transfer', 'Wartet auf Überweisung'),
            cancelled: tx('commerce.order.status.cancelled', 'Storniert'),
            refunded: tx('commerce.order.status.refunded', 'Erstattet'),
        }[order.status] || order.status
    }
    
    const orderPaymentHint = (order) => {
        if (order.status === 'completed') {
            return order.shipping_status === 'delivered'
                ? tx('commerce.order.hints.delivered', 'Zahlung und Zustellung erledigt')
                : tx('commerce.order.hints.processing', 'Zahlung eingegangen, Versand läuft noch')
        }
    
        return {
            pending: tx('commerce.order.hints.pending', 'Zahlung noch offen'),
            awaiting_transfer: tx('commerce.order.hints.awaiting_transfer', 'Banküberweisung muss bestätigt werden'),
            cancelled: tx('commerce.order.hints.cancelled', 'Bestellung wurde storniert'),
            refunded: tx('commerce.order.hints.refunded', 'Betrag wurde erstattet'),
        }[order.status] || ''
    }
    
    const orderShippingLabel = (status) => tx(`commerce.shipping.status.${status || 'open'}`, status || 'open')
    
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
        { key: 'marketplace', label: 'Marketplace', count: props.products.length + props.sellerApplications.length + props.warehouses.length, icon: 'las la-store' },
        { key: 'provider', label: 'Anbieter & Filialen', count: props.providerLocations.length, icon: 'las la-map-marked' },
        { key: 'orders', label: 'Bestellungen', count: props.orders.length + props.returnRequests.length + props.websiteRequests.length, icon: 'las la-box' },
        { key: 'settings', label: 'Steuern & Versand', count: props.taxRates.length + props.shippingRates.length, icon: 'las la-sliders-h' },
        { key: 'ad-prices', label: 'Ads Preise', count: 5, icon: 'las la-tags' },
        { key: 'ads', label: 'Ads', count: props.campaigns.length, icon: 'las la-bullhorn' },
        { key: 'marketing', label: 'Rabatte & Add-ons', count: props.coupons.length + props.addons.length, icon: 'las la-gift' },
        { key: 'payouts', label: 'Auszahlungen', count: props.payoutCandidates.length + props.payouts.length, icon: 'las la-wallet' },
        { key: 'reports', label: 'Reports', count: props.auditLogs.length + props.sellerReports.length, icon: 'las la-chart-bar' },
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

    const storeCoupon = () => couponForm
        .transform((data) => transformMoneyFields(data, ['value_cents']))
        .post(route('admin.commerce.coupons.store'), {
            preserveScroll: true,
            onSuccess: () => couponForm.reset(),
        })

    const storeAddon = () => addonForm
        .transform((data) => transformMoneyFields(data, ['monthly_price_cents', 'yearly_price_cents']))
        .post(route('admin.commerce.addons.store'), {
            preserveScroll: true,
            onSuccess: () => addonForm.reset(),
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
    
    const storeProviderProfile = () => providerProfileForm.post(route('auth.commerce.provider-profile.store'), {
        preserveScroll: true,
    })
    
    const resetProviderLocationForm = () => {
        providerLocationForm.reset()
        providerLocationForm.type = 'pickup'
        providerLocationForm.country = 'DE'
        providerLocationForm.pickup_enabled = true
        providerLocationForm.returns_enabled = false
        providerLocationForm.is_public = true
        providerLocationForm.clearErrors()
        editingProviderLocation.value = null
    }
    
    const editProviderLocation = (location) => {
        editingProviderLocation.value = location
        Object.assign(providerLocationForm, {
            name: location.name || '',
            type: location.type || 'pickup',
            country: location.country || 'DE',
            state: location.state || '',
            postal_code: location.postal_code || '',
            city: location.city || '',
            street: location.street || '',
            house_number: location.house_number || '',
            opening_hours: location.opening_hours || '',
            note: location.note || '',
            phone: location.phone || '',
            email: location.email || '',
            image_url: location.image_url || '',
            latitude: location.latitude || '',
            longitude: location.longitude || '',
            pickup_enabled: Boolean(location.pickup_enabled),
            returns_enabled: Boolean(location.returns_enabled),
            is_public: Boolean(location.is_public),
        })
    }
    
    const saveProviderLocation = () => {
        const options = {
            preserveScroll: true,
            onSuccess: resetProviderLocationForm,
        }
    
        if (editingProviderLocation.value) {
            providerLocationForm.put(route('auth.commerce.provider-locations.update', editingProviderLocation.value.id), options)
            return
        }
    
        providerLocationForm.post(route('auth.commerce.provider-locations.store'), options)
    }
    
    const destroyProviderLocation = async (location) => {
        const confirmed = await confirmDialog({
            title: tx('commerce.provider_location.remove.title', 'Standort entfernen'),
            message: tx('commerce.provider_location.remove.message', `${location.name} wirklich aus dem Anbieterprofil entfernen?`, { name: location.name }),
            confirmLabel: tx('commerce.provider_location.remove.confirm', 'Entfernen'),
            danger: true,
        })
    
        if (!confirmed) return
    
        router.delete(route('auth.commerce.provider-locations.destroy', location.id), {
            preserveScroll: true,
        })
    }
    
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
        refundModal.amount_cents = centsToMajor(Math.max(0, (order.amount_cents || 0) - (order.refunded_cents || 0)))
        refundModal.reason = ''
        refundModal.idempotency_key = globalThis.crypto?.randomUUID?.()
            || `web-${order.id}-${Date.now()}-${Math.random().toString(36).slice(2)}`
    }
    
    const submitRefund = () => {
        if (!refundModal.order) return
    
        router.post(route('admin.commerce.orders.refund', refundModal.order.id), {
            amount_cents: majorToCents(refundModal.amount_cents),
            reason: refundModal.reason,
            idempotency_key: refundModal.idempotency_key,
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
    return {
        page,
        centsToMajor,
        majorToCents,
        moneyInputAttrs,
        activeTab,
        campaignStatusError,
        couponForm,
        addonForm,
        marketplaceVisualForm,
        taxRateForm,
        commerceSettingsForm,
        marketplaceCommissionForm,
        providerProfileForm,
        providerLocationForm,
        editingProviderLocation,
        shippingRateForm,
        campaignForm,
        adFormats,
        selectedAdFormat,
        campaignCreativeRows,
        stockAdjustments,
        productForm,
        productAttributeRows,
        productFeatureRows,
        productVariantRows,
        rejectionModal,
        productDeleteModal,
        rejectionReasons,
        editProductForm,
        editAttributeRows,
        editFeatureRows,
        editVariantRows,
        attributePresets,
        presetValuesFor,
        normalizeAttributeRows,
        addProductAttributeRow,
        removeProductAttributeRow,
        addProductVariantRow,
        removeProductVariantRow,
        addProductFeatureRow,
        removeProductFeatureRow,
        addEditAttributeRow,
        removeEditAttributeRow,
        addEditVariantRow,
        removeEditVariantRow,
        addEditFeatureRow,
        removeEditFeatureRow,
        setProductImageUpload,
        setProductGalleryUploads,
        setEditProductImageUpload,
        setEditProductGalleryUploads,
        storeProduct,
        updateProductStatus,
        submitRejection,
        openDeleteProduct,
        closeDeleteProduct,
        setProductDeleteConfirmation,
        destroyProduct,
        selectRejectionReason,
        updateProductStock,
        inventoryAdjustment,
        setInventoryAdjustment,
        adjustInventoryStock,
        adjustGlobalStock,
        updateSellerApplication,
        openEditProduct,
        closeEditProduct,
        submitEditProduct,
        shippingModal,
        refundModal,
        issueReplyModal,
        reportedOrders,
        sellerApplicationStatusLabel,
        formatMoney,
        summaryCards,
        formatDateTime,
        orderPaymentLabel,
        orderPaymentHint,
        orderShippingLabel,
        trackingUrlFor,
        autofillTrackingUrl,
        orderIssueLabel,
        formatPercent,
        ctr,
        budgetUsage,
        tabs,
        adPricingCards,
        setMarketplaceVisualUpload,
        updateMarketplaceVisuals,
        storeCoupon,
        storeAddon,
        storeTaxRate,
        updateCommerceSettings,
        updateMarketplaceCommissions,
        storeProviderProfile,
        resetProviderLocationForm,
        editProviderLocation,
        saveProviderLocation,
        destroyProviderLocation,
        addMarketplaceCommissionRow,
        removeMarketplaceCommissionRow,
        updateTaxRate,
        storeShippingRate,
        updateShippingRate,
        updateReturnRequest,
        setCampaignCreativeUpload,
        addCampaignCreativeRow,
        removeCampaignCreativeRow,
        normalizeCampaignCreatives,
        resetCampaignCreativeRows,
        storeCampaign,
        markOrderPaid,
        updateCampaignStatus,
        updateOrderIssue,
        openIssueReplyModal,
        closeIssueReplyModal,
        submitIssueReply,
        openShippingModal,
        submitShipping,
        openRefundModal,
        submitRefund,
        updateWebsiteRequest,
        createPayout,
        markPayoutPaid,
        updatePayoutProfile,
    }
}
