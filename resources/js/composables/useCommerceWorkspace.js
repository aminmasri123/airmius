import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { moneyInputAttrs, transformMoneyFields } from '@/utils/currency'
import { confirmDialog } from '@/services/dialogService'
import { useCommerceProducts } from '@/composables/useCommerceProducts'
import { useI18n } from 'vue-i18n'

export function useCommerceWorkspace(props) {
    const { t, locale } = useI18n()
    const tx = (key, fallback, values = {}) => {
        const translated = t(key, values)
        return translated === key ? fallback : translated
    }
    const page = usePage()
    const selectedClubId = ref(props.clubs[0]?.id || '')
    const provider = ref('bank_transfer')
    const adProvider = ref('bank_transfer')
    const interval = ref('monthly')
    const adAcceptedTerms = ref(false)
    const issueModal = ref({ open: false, order: null, note: '', mode: 'issue' })
    const showCartCheckout = ref(false)
    const checkoutConfirmation = ref({ open: false, type: null, item: null, provider: 'bank_transfer', accepted: false })
    const checkoutProcessing = ref(false)
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
    const {
        addEditProductInventoryRow,
        addProductAttributeRow,
        addProductInventoryRow,
        addProductVariantRow,
        attributePresets,
        closeDeleteProductModal,
        closeEditProductModal,
        deleteProductModal,
        destroyOwnProduct,
        editProductForm,
        editProductModal,
        importProducts,
        inventoryCountries,
        inventoryTotalStock,
        isLearningOffer,
        normalizeAttributeRows,
        offerTypeLabel,
        openDeleteProductModal,
        openEditProductModal,
        openWebsiteRequestModal,
        presetValuesFor,
        productAttributeRows,
        productCommissionPreviewCents,
        productCreateModal,
        productForm,
        productImportForm,
        productSellerPayoutPreviewCents,
        productStatusLabel,
        productStockRequired,
        productVariantRows,
        removeEditProductInventoryRow,
        removeProductAttributeRow,
        removeProductInventoryRow,
        removeProductVariantRow,
        selectedProductCommission,
        sellerApplicationForm,
        sellerApplicationStatusLabel,
        sellerMarketplaceCategories,
        setDeleteProductConfirmation,
        setEditProductImageUpload,
        setProductGalleryUploads,
        setProductImageUpload,
        setProductImportFile,
        storeProduct,
        storeSellerApplication,
        storeWebsiteRequest,
        submitEditProduct,
        updateOwnProductStatus,
        websiteForm,
        websiteRequestModal,
    } = useCommerceProducts({
        clubs: () => props.clubs,
        marketplaceCategoryCommissions: () => props.marketplaceCategoryCommissions,
        pricingCountries: () => props.pricingCountries,
        sellerApplication: () => props.sellerApplication,
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
    const providerProfileForm = useForm({
        display_name: props.providerProfile?.display_name || page.props.auth?.user?.name || '',
        legal_name: props.providerProfile?.legal_name || '',
        provider_type: props.providerProfile?.provider_type || 'private',
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

    const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')

    const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
        style: 'currency',
        currency,
    }).format(Number(cents || 0) / 100)

    const formatDateTime = (value) => value
        ? new Intl.DateTimeFormat(localeCode.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
        : '-'

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
                ? tx('commerce.order.hints.delivered', 'Deine Bestellung wurde zugestellt.')
                : tx('commerce.order.hints.processing', 'Zahlung eingegangen, deine Bestellung wird bearbeitet.')
        }

        return {
            pending: tx('commerce.order.hints.pending', 'Zahlung noch offen'),
            awaiting_transfer: tx('commerce.order.hints.awaiting_transfer', 'Wir warten auf den Zahlungseingang.'),
            cancelled: tx('commerce.order.hints.cancelled', 'Diese Bestellung wurde storniert.'),
            refunded: tx('commerce.order.hints.refunded', 'Diese Bestellung wurde erstattet.'),
        }[order.status] || ''
    }

    const orderShippingLabel = (status) => ({
        open: tx('commerce.shipping.status.open', 'Offen'),
        prepared: tx('commerce.shipping.status.prepared', 'Wird vorbereitet'),
        shipped: tx('commerce.shipping.status.shipped', 'Versendet'),
        delivered: tx('commerce.shipping.status.delivered', 'Zugestellt'),
    }[status || 'open'] || status)

    const orderIssueLabel = (status) => ({
        reported: tx('commerce.issue.status.reported', 'Problem gemeldet'),
        reviewing: tx('commerce.issue.status.reviewing', 'In Prüfung'),
        resolved: tx('commerce.issue.status.resolved', 'Gelöst'),
        refunded: tx('commerce.issue.status.refunded', 'Erstattet'),
        cancelled: tx('commerce.issue.status.cancelled', 'Storniert'),
    }[status] || status)

    const orderSupport = (order) => order.support_summary || {}

    const payoutStatusLabel = (status) => ({
        requested: tx('commerce.payout.status.requested', 'Angefordert'),
        prepared: tx('commerce.payout.status.prepared', 'In Prüfung'),
        paid: tx('commerce.payout.status.paid', 'Ausgezahlt'),
        cancelled: tx('commerce.payout.status.cancelled', 'Storniert'),
    }[status] || status || '-')

    const orderIsDelivered = (order) => order.status === 'completed' && order.shipping_status === 'delivered'
    const orderHasShippableItems = (order) => (order.items || []).some((item) => item.is_shippable)
    const orderCanCancel = (order) => ['marketplace_product', 'marketplace_cart'].includes(order.type)
        && orderHasShippableItems(order)
        && ['pending', 'awaiting_transfer', 'completed'].includes(order.status)
        && !['shipped', 'delivered'].includes(order.shipping_status || 'open')
    const orderCanReportIssue = (order) => orderSupport(order).can_report_issue ?? order.status === 'completed'
    const orderCanReturn = (order) => orderSupport(order).can_request_return ?? (orderIsDelivered(order) && orderHasShippableItems(order))

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
    const visibleShopProductTitle = computed(() => tx('commerce.shop.learning_title', 'Kurse und E-Learning'))
    const visibleShopProductDescription = computed(() => tx('commerce.shop.learning_description', 'Online-Kurse, Trainingspläne und digitale Lernangebote kaufen.'))
    const showAccountShop = computed(() => ['all', 'account'].includes(shopView.value))
    const showOutfitShop = computed(() => ['all', 'outfit'].includes(shopView.value))
    const showProductShop = computed(() => ['all', 'courses'].includes(shopView.value))
    const shopCategoryTabs = computed(() => [
        { key: 'all', label: tx('commerce.shop.categories.all', 'Alle'), count: courseProducts.value.length + props.accountPlans.length + visibleAddons.value.length + props.outfitPlans.length },
        { key: 'courses', label: tx('commerce.shop.categories.courses', 'Kurse / E-Learning'), count: courseProducts.value.length },
        { key: 'outfit', label: tx('commerce.shop.categories.outfit', 'Outfit-Abo'), count: props.outfitPlans.length },
        { key: 'account', label: tx('commerce.shop.categories.account', 'Konto-Abo & Add-ons'), count: props.accountPlans.length + visibleAddons.value.length },
    ])

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
        checkoutProcessing.value = true
        router.post(route('auth.commerce.addons.checkout', addon.id), {
            provider: selectedProvider,
            billing_interval: interval.value,
            club_id: selectedClubId.value || null,
            accepted_terms: true,
        }, {
            preserveScroll: true,
            onSuccess: closeCheckoutConfirmation,
            onFinish: () => {
                checkoutProcessing.value = false
            },
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

        checkoutProcessing.value = true
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
        checkoutProcessing.value = true
        router.post(route('auth.commerce.products.checkout', product.id), {
            provider: selectedProvider,
            accepted_terms: true,
        }, {
            preserveScroll: true,
            onSuccess: closeCheckoutConfirmation,
            onFinish: () => {
                checkoutProcessing.value = false
            },
        })
    }

    const closeCheckoutConfirmation = () => {
        checkoutConfirmation.value = { open: false, type: null, item: null, provider: 'bank_transfer', accepted: false }
        checkoutProcessing.value = false
    }

    const setCheckoutAccepted = (accepted) => {
        checkoutConfirmation.value.accepted = accepted
    }

    const providerLabel = (value) => ({
        bank_transfer: tx('commerce.payment.bank_transfer', 'Überweisung'),
        stripe: tx('commerce.payment.stripe', 'Stripe'),
        paypal: tx('commerce.payment.paypal', 'PayPal'),
    })[value] || value

    const checkoutConfirmationTitle = computed(() => {
        const item = checkoutConfirmation.value.item

        if (!item) {
            return tx('commerce.checkout.confirm', 'Checkout bestätigen')
        }

        if (checkoutConfirmation.value.type === 'account_plan') {
            return item.name
        }

        return item.name || item.title || tx('commerce.checkout.confirm', 'Checkout bestätigen')
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
        { key: 'shop', label: tx('commerce.tabs.shop', 'Shop'), icon: 'las la-th-large', count: courseProducts.value.length + props.accountPlans.length + visibleAddons.value.length + props.outfitPlans.length },
        { key: 'cart', label: tx('commerce.tabs.cart', 'Warenkorb'), icon: 'las la-shopping-cart', count: cartItemCount.value },
        { key: 'invoices', label: tx('commerce.tabs.invoices', 'Rechnungen'), icon: 'las la-file-invoice', count: props.purchaseHistory.length },
        { key: 'ads', label: tx('commerce.tabs.ads', 'Ads'), icon: 'las la-bullhorn', count: props.myCampaigns.length },
        { key: 'create', label: tx('commerce.tabs.create', 'Verkaufen'), icon: 'las la-plus-circle', count: props.myProducts.length + props.websiteRequests.length },
        { key: 'provider', label: tx('commerce.tabs.provider', 'Anbieterprofil'), icon: 'las la-store', count: props.providerLocations.length },
        { key: 'payouts', label: tx('commerce.tabs.payouts', 'Auszahlung'), icon: 'las la-wallet', count: props.payoutSummary.pending_orders || 0 },
    ])

    const campaignCreateErrors = computed(() => Object.values(campaignForm.errors || {})
        .flatMap((message) => Array.isArray(message) ? message : [message])
        .filter(Boolean))

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
                campaignCreateError.value = 'Die Ads-Kampagne konnte nicht zur Zahlung vorbereitet werden. Bitte prüfe die Angaben unten.'
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

    const setDeleteCampaignConfirmation = (confirmation) => {
        deleteCampaignModal.value.confirmation = confirmation
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
                campaignActionError.value = errors.confirmation || errors.campaign_status || 'Kampagne konnte nicht gelöscht werden.'
            },
        })
    }

    const storePayoutProfile = () => payoutForm.post(route('auth.commerce.payout-profile.store'), {
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
            message: tx('commerce.provider_location.remove.message', `${location.name} wirklich aus deinem Anbieterprofil entfernen?`, { name: location.name }),
            confirmLabel: tx('commerce.provider_location.remove.confirm', 'Entfernen'),
            danger: true,
        })

        if (!confirmed) return

        router.delete(route('auth.commerce.provider-locations.destroy', location.id), {
            preserveScroll: true,
        })
    }

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

    const setIssueNote = (note) => {
        issueModal.value.note = note
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

    const cancelOrder = async (order) => {
        if (!orderCanCancel(order)) return
        const confirmed = await confirmDialog({
            title: tx('commerce.order.cancel.title', 'Bestellung stornieren'),
            message: tx('commerce.order.cancel.message', 'Bestellung wirklich stornieren? Das ist nur möglich, solange sie noch nicht versendet wurde.'),
            confirmLabel: tx('commerce.order.cancel.confirm', 'Stornieren'),
            danger: true,
        })

        if (!confirmed) return

        router.post(route('auth.commerce.orders.cancel', order.id), {}, {
            preserveScroll: true,
        })
    }

    onMounted(() => {
        if (focusedOrderId.value) {
            focusOrder(focusedOrderId.value)
        }
    })

    return {
        page,
        moneyInputAttrs,
        selectedClubId,
        provider,
        adProvider,
        interval,
        adAcceptedTerms,
        issueModal,
        showCartCheckout,
        checkoutConfirmation,
        checkoutProcessing,
        queryTab,
        queryOrderId,
        activeTab,
        focusedOrderId,
        shopView,
        campaignActionError,
        campaignCreateError,
        deleteCampaignModal,
        editCampaignModal,
        adGroupModal,
        adCreativeModal,
        editCampaignUploadPreviewUrl,
        adGroupSportQuery,
        addEditProductInventoryRow,
        addProductAttributeRow,
        addProductInventoryRow,
        addProductVariantRow,
        attributePresets,
        closeDeleteProductModal,
        closeEditProductModal,
        deleteProductModal,
        destroyOwnProduct,
        editProductForm,
        editProductModal,
        importProducts,
        inventoryCountries,
        inventoryTotalStock,
        isLearningOffer,
        normalizeAttributeRows,
        offerTypeLabel,
        openDeleteProductModal,
        openEditProductModal,
        openWebsiteRequestModal,
        presetValuesFor,
        productAttributeRows,
        productCommissionPreviewCents,
        productCreateModal,
        productForm,
        productImportForm,
        productSellerPayoutPreviewCents,
        productStatusLabel,
        productStockRequired,
        productVariantRows,
        removeEditProductInventoryRow,
        removeProductAttributeRow,
        removeProductInventoryRow,
        removeProductVariantRow,
        selectedProductCommission,
        sellerApplicationForm,
        sellerApplicationStatusLabel,
        sellerMarketplaceCategories,
        setDeleteProductConfirmation,
        setEditProductImageUpload,
        setProductGalleryUploads,
        setProductImageUpload,
        setProductImportFile,
        storeProduct,
        storeSellerApplication,
        storeWebsiteRequest,
        submitEditProduct,
        updateOwnProductStatus,
        websiteForm,
        websiteRequestModal,
        campaignForm,
        adGroupForm,
        adCreativeForm,
        editCampaignForm,
        payoutForm,
        payoutRequestForm,
        providerProfileForm,
        providerLocationForm,
        editingProviderLocation,
        cartCheckoutForm,
        formatMoney,
        formatDateTime,
        orderPaymentLabel,
        orderPaymentHint,
        orderShippingLabel,
        orderIssueLabel,
        orderSupport,
        payoutStatusLabel,
        orderIsDelivered,
        orderHasShippableItems,
        orderCanCancel,
        orderCanReportIssue,
        orderCanReturn,
        formatPercent,
        cartItems,
        cartItemCount,
        selectedAddonActor,
        visibleAddons,
        isProductLearningOffer,
        courseProducts,
        marketplaceProducts,
        visibleShopProducts,
        visibleShopProductTitle,
        visibleShopProductDescription,
        showAccountShop,
        showOutfitShop,
        showProductShop,
        shopCategoryTabs,
        adFormats,
        adPlacements,
        formatsForPlacement,
        campaignAdFormats,
        editCampaignAdFormats,
        selectedAdFormat,
        selectedAdPlacement,
        selectedEditAdFormat,
        selectedEditAdPlacement,
        adPlacementLabel,
        sportLabel,
        selectedAdGroupSports,
        filteredAdGroupSports,
        campaignCreativeRows,
        adCreativeRows,
        editCampaignCreativeRows,
        newClientReference,
        ctr,
        addonPrice,
        checkoutAddon,
        confirmAddonCheckout,
        checkoutAccountPlan,
        confirmAccountPlanCheckout,
        checkoutProduct,
        confirmProductCheckout,
        closeCheckoutConfirmation,
        setCheckoutAccepted,
        providerLabel,
        checkoutConfirmationTitle,
        checkoutConfirmationPrice,
        confirmCheckout,
        addToCart,
        updateCartItem,
        removeCartItem,
        checkoutCart,
        activeOrders,
        commerceTabs,
        campaignCreateErrors,
        setCampaignCreativeUpload,
        setEditCampaignCreativeUpload,
        addCampaignCreativeRow,
        removeCampaignCreativeRow,
        addAdCreativeRow,
        removeAdCreativeRow,
        addEditCampaignCreativeRow,
        removeEditCampaignCreativeRow,
        addAdGroupSport,
        removeAdGroupSport,
        normalizeCampaignCreatives,
        normalizeAdCreatives,
        normalizeEditCampaignCreatives,
        campaignAudienceText,
        adGroupAudienceText,
        adGroupAudienceSportsText,
        campaignRootCreatives,
        firstCreativePreviewUrl,
        editCampaignPreviewUrl,
        creativePreviewUrl,
        toLocalDateTimeInput,
        storeCampaign,
        openAdGroupModal,
        closeAdGroupModal,
        storeAdGroup,
        openAdCreativeModal,
        closeAdCreativeModal,
        storeAdCreatives,
        updateOwnCampaignStatus,
        openEditCampaignModal,
        closeEditCampaignModal,
        submitEditCampaign,
        deleteOwnCampaign,
        closeDeleteCampaignModal,
        setDeleteCampaignConfirmation,
        confirmDeleteOwnCampaign,
        storePayoutProfile,
        storeProviderProfile,
        resetProviderLocationForm,
        editProviderLocation,
        saveProviderLocation,
        destroyProviderLocation,
        requestPayout,
        openIssueModal,
        closeIssueModal,
        setIssueNote,
        submitOrderRequest,
        orderRowId,
        focusOrder,
        openPurchaseDetails,
        cancelOrder,
    }
}
