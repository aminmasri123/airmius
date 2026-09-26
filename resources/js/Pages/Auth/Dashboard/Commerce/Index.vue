<script setup>
import AppLayout from "@/Components/Auth/Layouts/AppLayout.vue"
import AppButton from "@/Components/UI/AppButton.vue"
import AppLoadingState from "@/Components/UI/AppLoadingState.vue"
import Modal from "@/Components/Modal.vue"
import { useCommerceWorkspace } from "@/composables/useCommerceWorkspace"
import commerceCriticalCheckoutCopy from "./commerceCriticalCheckoutCopy.json"
import { Head, Link } from "@inertiajs/vue3"
import { useI18n } from "vue-i18n"

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
    providerProfile: { type: Object, default: null },
    providerLocations: { type: Array, default: () => [] },
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

const { t, locale } = useI18n()
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}
const ct = (key) => {
    const language = String(locale.value || 'de').split('-')[0]

    return commerceCriticalCheckoutCopy[language]?.[key] || commerceCriticalCheckoutCopy.de[key] || key
}

const {
    page,
    moneyInputAttrs,
    selectedClubId,
    addonClubs,
    provider,
    adProvider,
    interval,
    adAcceptedTerms,
    issueModal,
    showCartCheckout,
    checkoutConfirmation,
    checkoutProcessing,
    checkoutError,
    cartCheckoutProcessing,
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
    productClubs,
    websiteClubs,
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
    openCartCheckout,
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
} = useCommerceWorkspace(props)
</script>

<template>
    <Head :title="tx('commerce.page_title', 'Shop & Rechnungen')" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('commerce.eyebrow', 'Käufe & Abos') }}</p>
            <h1 class="mt-1 text-2xl font-bold text-primary">{{ tx('commerce.title', 'Shop, Rechnungen und Angebote') }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-secondary">
                {{ tx('commerce.intro', 'Verwalte Marketplace-Käufe, Kurse, Ads, Outfit-Abos, Konto-Abos, Warenkorb und Rechnungen an einem Ort.') }}
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

        <section v-if="activeTab === 'cart'" class="surface-card overflow-hidden">
            <div class="border-b border-border bg-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('commerce.ui.cart_title', 'Warenkorb') }}</p>
                        <h2 class="mt-1 text-2xl font-bold text-primary">{{ tx('commerce.ui.cart_heading', 'Deine ausgewählten Produkte') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('commerce.ui.cart_hint', 'Hier erscheinen nur Artikel, die du bewusst in den Einkaufswagen gelegt hast.') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary">
                            {{ cartItemCount }} {{ tx('commerce.ui.items', 'Artikel') }}
                        </span>
                        <button
                            class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                            :disabled="!cartItems.length"
                            @click="openCartCheckout"
                        >
                            {{ tx('commerce.ui.checkout', 'Zur Kasse') }}
                        </button>
                    </div>
                </div>
            </div>
            <div class="divide-y divide-border">
                <div v-for="item in cartItems" :key="item.id" class="grid gap-4 p-5 md:grid-cols-[5rem_minmax(0,1fr)_8rem_auto] md:items-center">
                    <Link :href="item.product?.show_url || route('auth.commerce.products.show', item.product?.id)" class="block overflow-hidden rounded-lg border border-border bg-inputBg">
                        <img v-if="item.product?.image_url" :src="item.product.image_url" :alt="item.product.title" width="160" height="160" loading="lazy" decoding="async" class="aspect-square h-full w-full object-cover">
                        <div v-else class="flex aspect-square items-center justify-center">
                            <i class="las la-store text-3xl text-air-blue"></i>
                        </div>
                    </Link>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-bg px-2.5 py-1 text-xs font-semibold uppercase text-secondary">{{ item.product?.category || tx('commerce.ui.product', 'Produkt') }}</span>
                            <span v-if="item.product?.sku" class="text-xs text-secondary">{{ tx('commerce.ui.sku', 'Art.-Nr. {sku}', { sku: item.product.sku }) }}</span>
                        </div>
                        <Link :href="item.product?.show_url || route('auth.commerce.products.show', item.product?.id)" class="mt-2 block break-words font-semibold text-primary hover:text-air-blue">
                            {{ item.product?.title }}
                        </Link>
                        <p class="mt-1 line-clamp-2 text-sm text-secondary">{{ item.product?.description }}</p>
                        <p class="mt-2 text-xs font-semibold text-success">{{ tx('commerce.ui.available', 'Verfügbar: {count} Stück', { count: item.product?.stock_quantity }) }}</p>
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
                            {{ tx('commerce.ui.remove', 'Entfernen') }}
                        </button>
                    </div>
                </div>
                <div v-if="cartItems.length" class="grid gap-3 bg-bg p-5 text-sm text-secondary sm:grid-cols-4">
                    <p class="rounded-lg border border-border bg-card p-3">{{ tx('commerce.ui.subtotal', 'Warenwert') }}<br><span class="font-semibold text-primary">{{ formatMoney(cart.summary?.item_gross_cents, cart.summary?.currency) }}</span></p>
                    <p class="rounded-lg border border-border bg-card p-3">{{ tx('commerce.ui.shipping', 'Versand') }}<br><span class="font-semibold text-primary">{{ formatMoney(cart.summary?.shipping_cents, cart.summary?.currency) }}</span></p>
                    <p class="rounded-lg border border-border bg-card p-3">{{ tx('commerce.ui.tax', 'Steuer') }}<br><span class="font-semibold text-primary">{{ formatMoney(cart.summary?.tax_cents, cart.summary?.currency) }}</span></p>
                    <p class="rounded-lg border border-border bg-card p-3">{{ tx('commerce.ui.total', 'Gesamt') }}<br><span class="text-lg font-bold text-primary">{{ formatMoney(cart.summary?.amount_cents, cart.summary?.currency) }}</span></p>
                </div>
                <div v-else class="grid gap-4 p-8 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-border bg-bg">
                        <i class="las la-shopping-bag text-3xl text-air-blue"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-primary">{{ tx('commerce.ui.empty_cart', 'Dein Warenkorb ist leer') }}</h3>
                        <p class="mt-1 text-sm text-secondary">{{ tx('commerce.ui.empty_cart_hint', 'Füge ein Marketplace-Produkt hinzu, dann erscheint es hier.') }}</p>
                    </div>
                    <Link :href="route('guest.marketplace')" class="mx-auto rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        {{ tx('commerce.ui.browse_marketplace', 'Marketplace ansehen') }}
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="activeTab === 'shop'" class="surface-card p-5">
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
                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('commerce.ui.club_for_addons', 'Verein für Add-ons') }}</label>
                    <select v-model="selectedClubId" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="">{{ tx('commerce.ui.private_no_club', 'Privat / kein Verein') }}</option>
                        <option v-for="club in addonClubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('commerce.ui.payment_method', 'Zahlungsart') }}</label>
                    <select v-model="provider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="bank_transfer">{{ t('commerce.payment.bank_transfer') }}</option>
                        <option value="stripe">{{ t('commerce.payment.stripe') }}</option>
                        <option value="paypal">{{ t('commerce.payment.paypal') }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-secondary">{{ tx('commerce.ui.addon_interval', 'Add-on Laufzeit') }}</label>
                    <select v-model="interval" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="monthly">{{ tx('commerce.ui.monthly', 'Monatlich') }}</option>
                        <option value="yearly">{{ tx('commerce.ui.yearly', 'Jährlich') }}</option>
                    </select>
                </div>
            </div>
        </section>

        <section v-if="activeTab === 'shop' && showAccountShop" class="grid gap-5 lg:grid-cols-3">
            <article v-for="plan in accountPlans" :key="plan.id" class="surface-card flex flex-col p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('commerce.ui.account_plan', 'Konto-Abo') }}</p>
                <div class="mt-1 flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold text-primary">{{ plan.name }}</h2>
                    <span v-if="plan.is_owned" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">{{ tx('commerce.ui.active', 'Aktiv') }}</span>
                    <span v-else-if="plan.badge" class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">{{ plan.badge }}</span>
                </div>
                <p class="mt-2 flex-1 text-sm text-secondary">{{ plan.description }}</p>
                <p class="mt-4 text-2xl font-bold text-primary">{{ formatMoney(plan.monthly_price_cents, plan.currency) }}</p>
                <p class="text-sm text-secondary">{{ plan.monthly_price_cents ? tx('commerce.ui.per_month', 'pro Monat') : tx('commerce.ui.free', 'kostenlos') }}</p>
                <p v-if="plan.yearly_price_cents" class="mt-1 text-xs text-secondary">{{ formatMoney(plan.yearly_price_cents, plan.currency) }} {{ tx('commerce.ui.yearly', 'pro Jahr') }}</p>
                <p v-if="plan.localized_price" class="mt-1 text-xs text-air-blue">{{ tx('commerce.ui.local_price', 'Lokaler Preis für {country}', { country: plan.pricing_country }) }}</p>
                <dl class="mt-4 border-t border-border pt-3 text-sm text-secondary">
                    <div class="flex justify-between gap-3">
                        <dt>{{ tx('commerce.ui.storage', 'Speicher') }}</dt>
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
                    {{ tx('commerce.ui.plan_owned', 'Du besitzt diesen Plan') }}
                </div>
                <div v-else-if="!plan.monthly_price_cents" class="mt-5 rounded-lg border border-border px-4 py-2 text-center text-sm font-semibold text-primary">
                    {{ tx('commerce.ui.free_basic_plan', 'Kostenloser Basisplan') }}
                </div>
                <div v-else class="mt-5 grid gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                        @click="checkoutAccountPlan(plan, 'stripe')"
                    >
                        {{ tx('commerce.ui.pay_with', 'Mit {provider} zahlen', { provider: tx('commerce.payment.stripe', 'Stripe') }) }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="checkoutAccountPlan(plan, 'paypal')"
                    >
                        {{ tx('commerce.ui.pay_with', 'Mit {provider} zahlen', { provider: tx('commerce.payment.paypal', 'PayPal') }) }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="checkoutAccountPlan(plan, 'bank_transfer')"
                    >
                        {{ tx('commerce.ui.pay_with', 'Mit {provider} zahlen', { provider: tx('commerce.payment.bank_transfer', 'Überweisung') }) }}
                    </button>
                </div>
            </article>
            <article v-for="addon in visibleAddons" :key="addon.id" class="surface-card flex flex-col p-5">
                <h2 class="text-lg font-semibold text-primary">{{ addon.name }}</h2>
                <p class="mt-2 flex-1 text-sm text-secondary">{{ addon.description }}</p>
                <p class="mt-4 text-2xl font-bold text-primary">{{ formatMoney(addonPrice(addon)) }}</p>
                <button class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="checkoutAddon(addon)">
                    {{ tx('commerce.ui.book_addon', 'Add-on buchen') }}
                </button>
            </article>
        </section>

        <section v-if="activeTab === 'shop' && showProductShop" class="surface-card overflow-hidden">
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
                            <Link :href="route('auth.commerce.products.show', product.id)" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary">{{ tx('commerce.ui.details', 'Details') }}</Link>
                            <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="addToCart(product)">
                                {{ tx('commerce.ui.add_to_cart', 'In den Warenkorb') }}
                            </button>
                        </div>
                    </div>
                    </div>
                </article>
                <p v-if="!visibleShopProducts.length" class="text-sm text-secondary">{{ tx('commerce.ui.no_offers', 'Noch keine passenden Angebote veröffentlicht.') }}</p>
            </div>
        </section>

        <section v-if="activeTab === 'shop' && showOutfitShop" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">{{ tx('commerce.ui.outfit_title', 'Sportkleidung-Abos') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ tx('commerce.ui.outfit_description', 'Monatliche Outfit-Boxen mit Style-Profil, Lieferübersicht und optionalem Sponsor-Rabatt.') }}</p>
            </div>
            <div class="grid gap-4 p-5 lg:grid-cols-3">
                <article v-for="plan in outfitPlans" :key="plan.id" class="rounded-lg border border-border bg-bg p-4">
                    <p class="text-xs uppercase text-secondary">{{ tx('commerce.ui.pieces_per_box', '{count} Teile pro Box', { count: plan.items_per_box }) }}</p>
                    <h3 class="mt-1 font-semibold text-primary">{{ plan.name }}</h3>
                    <p class="mt-2 min-h-12 text-sm text-secondary">{{ plan.description }}</p>
                    <p v-if="plan.sponsor" class="mt-2 text-xs font-semibold text-air-blue">{{ tx('commerce.ui.sponsored_by', 'Subventioniert von {name}', { name: plan.sponsor.name }) }}</p>
                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div>
                            <span class="text-lg font-bold text-primary">{{ formatMoney(plan.effective_monthly_price_cents, plan.currency) }}</span>
                            <p v-if="plan.sponsor_discount_cents" class="text-xs text-secondary">{{ tx('commerce.ui.instead_of', 'statt {price}', { price: formatMoney(plan.monthly_price_cents, plan.currency) }) }}</p>
                        </div>
                        <Link :href="route('auth.outfit-subscriptions.index')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                            {{ tx('commerce.ui.outfit_cta', 'Zum Outfit-Abo') }}
                        </Link>
                    </div>
                </article>
                <p v-if="!outfitPlans.length" class="text-sm text-secondary">{{ tx('commerce.ui.no_outfits', 'Noch keine Outfit-Abos freigegeben.') }}</p>
            </div>
        </section>

        <section v-if="activeTab === 'create'" class="grid gap-6 xl:grid-cols-2">
            <article class="surface-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ tx('auto.Eigene Angebote verwalten', 'Eigene Angebote verwalten') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('auto.Erst nach einem freigegebenen Shop-Antrag kannst du Produkte im Marketplace verkaufen.', 'Erst nach einem freigegebenen Shop-Antrag kannst du Produkte im Marketplace verkaufen.') }}</p>
                    </div>
                    <button v-if="sellerCanSell" type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="productCreateModal = true">
                        {{ tx('auto.Produkt erstellen', 'Produkt erstellen') }}
                    </button>
                </div>
                <div v-if="sellerCanSell" class="mt-5 grid gap-3 rounded-lg border border-border bg-bg p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-air-blue">{{ tx('auto.Excel-Import', 'Excel-Import') }}</p>
                            <h3 class="mt-1 font-semibold text-primary">{{ tx('auto.Viele Angebote auf einmal hochladen', 'Viele Angebote auf einmal hochladen') }}</h3>
                            <p class="mt-1 text-sm text-secondary">
                                {{ tx('auto.Lade die Vorlage herunter. Preise bleiben in EUR, die Airmius-Provision wird in der Tabelle automatisch je Kategorie mitgerechnet.', 'Lade die Vorlage herunter. Preise bleiben in EUR, die Airmius-Provision wird in der Tabelle automatisch je Kategorie mitgerechnet.') }}
                            </p>
                            <p class="mt-1 text-xs text-secondary">{{ tx('auto.Bilder werden per Hauptbild-URL und Galerie-URLs importiert.', 'Bilder werden per Hauptbild-URL und Galerie-URLs importiert.') }}</p>
                        </div>
                        <a :href="route('auth.commerce.products.import-template')" class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                            {{ tx('auto.Vorlage herunterladen', 'Vorlage herunterladen') }}
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
                            {{ tx('auto.Importieren', 'Importieren') }}
                        </button>
                    </form>
                    <p v-if="productImportForm.errors.import_file" class="text-sm text-error">{{ productImportForm.errors.import_file }}</p>
                </div>
                <div v-if="!sellerCanSell" class="mt-5 rounded-lg border border-border bg-bg p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-air-blue">{{ tx('auto.Shop-Antrag', 'Shop-Antrag') }}</p>
                            <h3 class="mt-1 font-semibold text-primary">{{ tx('auto.Verkäufer-Zugang beantragen', 'Verkäufer-Zugang beantragen') }}</h3>
                            <p class="mt-1 text-sm text-secondary">Status: {{ sellerApplicationStatusLabel(sellerApplication?.status) }}</p>
                            <p v-if="sellerApplication?.review_note" class="mt-2 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">{{ sellerApplication.review_note }}</p>
                        </div>
                    </div>

                    <form class="mt-4 grid gap-3" @submit.prevent="storeSellerApplication">
                        <select v-model="sellerApplicationForm.applicant_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="private">{{ tx('auto.Privatperson', 'Privatperson') }}</option>
                            <option value="business">{{ tx('auto.Gewerblicher Anbieter', 'Gewerblicher Anbieter') }}</option>
                            <option value="club">{{ tx('auto.Verein / Organisation', 'Verein / Organisation') }}</option>
                        </select>
                        <p v-if="sellerApplicationForm.errors.applicant_type" class="text-sm text-error">{{ sellerApplicationForm.errors.applicant_type }}</p>
                        <input v-model="sellerApplicationForm.business_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.Shop-/Firmenname optional', 'Shop-/Firmenname optional')">
                        <textarea v-model="sellerApplicationForm.notes" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('auto.Kurz beschreiben, was du verkaufen möchtest', 'Kurz beschreiben, was du verkaufen möchtest')"></textarea>

                        <div class="grid gap-2 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_product_truth" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>{{ tx('auto.Ich bestätige, dass Preise, Bilder, Bestand und Beschreibung korrekt sind.', 'Ich bestätige, dass Preise, Bilder, Bestand und Beschreibung korrekt sind.') }}</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_rights" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>{{ tx('auto.Ich habe die Rechte an Bildern, Texten und angebotenen Leistungen.', 'Ich habe die Rechte an Bildern, Texten und angebotenen Leistungen.') }}</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_shipping_returns" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>{{ tx('auto.Ich beachte Versand-, Rückgabe- und Kundenservice-Pflichten.', 'Ich beachte Versand-, Rückgabe- und Kundenservice-Pflichten.') }}</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_commission" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>{{ tx('auto.Ich akzeptiere Marketplace-Provisionen und Auszahlungsprüfung.', 'Ich akzeptiere Marketplace-Provisionen und Auszahlungsprüfung.') }}</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input v-model="sellerApplicationForm.rule_data_privacy" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>{{ tx('auto.Ich gehe sorgsam mit Kundendaten um und nutze sie nur für die Bestellung.', 'Ich gehe sorgsam mit Kundendaten um und nutze sie nur für die Bestellung.') }}</span>
                            </label>
                        </div>
                        <div v-if="Object.keys(sellerApplicationForm.errors).length" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                            {{ tx('auto.Bitte bestätige alle Regeln, bevor du den Shop-Antrag absendest.', 'Bitte bestätige alle Regeln, bevor du den Shop-Antrag absendest.') }}
                        </div>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="sellerApplicationForm.processing">
                            {{ tx('auto.Shop-Antrag senden', 'Shop-Antrag senden') }}
                        </button>
                    </form>
                </div>
                <div v-if="sellerCanSell && productCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
                    <form class="relative max-h-[90dvh] w-full max-w-4xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="storeProduct">
                        <button
                            type="button"
                            class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                            :aria-label="tAuto('Produkt erstellen schließen')"
                            @click="productCreateModal = false"
                        >
                            <i class="las la-times text-xl"></i>
                        </button>
                        <div class="mb-4 pr-12">
                            <p class="text-xs font-semibold uppercase text-air-blue">Verkaufen</p>
                            <h2 class="mt-1 text-lg font-semibold text-primary">Produkt erstellen</h2>
                            <p class="mt-1 text-sm text-secondary">Das Angebot geht danach zur Prüfung und wird erst nach Freigabe im Marketplace angezeigt.</p>
                        </div>
                        <div class="grid gap-3">
                    <div v-if="Object.keys(productForm.errors).length" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                        Bitte prüfe die markierten Angaben. Pflichtfelder wie Titel, Kategorie und Preis müssen ausgefüllt sein.
                    </div>
                    <select v-model="productForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Anbieter oder Verein')">
                        <option value="">Privat / Anbieter</option>
                        <option v-for="club in productClubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <p v-if="productForm.errors.club_id" class="text-sm text-error">{{ productForm.errors.club_id }}</p>
                    <input v-model="productForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                    <p v-if="productForm.errors.title" class="text-sm text-error">{{ productForm.errors.title }}</p>
                    <div class="grid gap-3 rounded-lg border border-border bg-bg p-3">
                        <div>
                            <label for="product-main-image-url" class="text-xs font-semibold uppercase text-secondary">Hauptbild per URL</label>
                            <input id="product-main-image-url" v-model="productForm.image_url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.url_placeholder')">
                            <p v-if="productForm.errors.image_url" class="mt-1 text-sm text-error">{{ productForm.errors.image_url }}</p>
                        </div>
                        <div>
                            <label for="product-main-image-upload" class="text-xs font-semibold uppercase text-secondary">Hauptbild hochladen</label>
                            <input id="product-main-image-upload" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setProductImageUpload">
                            <p class="mt-1 text-xs text-secondary">JPG, PNG oder WebP. Upload ersetzt die URL.</p>
                            <p v-if="productForm.errors.image_upload" class="mt-1 text-sm text-error">{{ productForm.errors.image_upload }}</p>
                        </div>
                        <div>
                            <label for="product-gallery-urls" class="text-xs font-semibold uppercase text-secondary">Weitere Bild-URLs</label>
                            <textarea id="product-gallery-urls" v-model="productForm.image_urls_text" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Eine URL pro Zeile"></textarea>
                            <p v-if="productForm.errors.image_urls_text" class="mt-1 text-sm text-error">{{ productForm.errors.image_urls_text }}</p>
                        </div>
                        <div>
                            <label for="product-gallery-upload" class="text-xs font-semibold uppercase text-secondary">Weitere Bilder hochladen</label>
                            <input id="product-gallery-upload" type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setProductGalleryUploads">
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
                    <select v-if="productForm.offer_type === 'physical_product'" v-model="productForm.product_type" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Produkttyp')">
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
                            <label for="product-learning-course" class="text-xs font-semibold uppercase text-secondary">Mit Sportschule-Kurs verknüpfen</label>
                            <select id="product-learning-course" v-model="productForm.learning_course_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="">Keinen Kurs automatisch freischalten</option>
                                <option v-for="course in learningCourses" :key="course.id" :value="course.id">
                                    {{ course.title }} - {{ course.status }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-secondary">Nach bezahlter Bestellung wird der verknüpfte Kurs automatisch für den Käufer freigeschaltet.</p>
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
                    <select v-model="productForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Steuerklasse')">
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
                                <p class="mt-1 text-xs text-secondary">Die Provision wird nach der gewählten Kategorie berechnet und intern am Produkt gespeichert.</p>
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
                                <h3 class="text-sm font-semibold text-primary">Länderbestand</h3>
                                <p class="text-xs text-secondary">Nur Länder mit aktivem Bestand werden im internationalen Marketplace angeboten.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addProductInventoryRow">
                                Land hinzufügen
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(inventory, index) in productForm.inventories" :key="index" class="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-6">
                                <select v-model="inventory.country_code" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="`Lagerland ${index + 1}`">
                                    <option v-for="country in inventoryCountries" :key="country" :value="country">{{ country }}</option>
                                </select>
                                <input v-model="inventory.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" placeholder="Bestand" :aria-label="`Lager ${index + 1}: Bestand`">
                                <input v-model="inventory.low_stock_threshold" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" placeholder="Warnbestand" :aria-label="`Lager ${index + 1}: Warnbestand`">
                                <input v-model="inventory.lead_time_days" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" placeholder="Lieferzeit Tage" :aria-label="`Lager ${index + 1}: Lieferzeit in Tagen`">
                                <input v-model="inventory.city" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerstadt" :aria-label="`Lager ${index + 1}: Stadt`">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" :aria-label="`Lager ${index + 1} entfernen`" @click="removeProductInventoryRow(index)">
                                    Entfernen
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-secondary">Aktueller Gesamtbestand aus Ländern: {{ inventoryTotalStock }}</p>
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
                                <select v-model="row.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="`Merkmal ${index + 1}: Name`" @change="row.values = []">
                                    <option value="">Merkmal wählen</option>
                                    <option v-for="preset in attributePresets" :key="preset.name" :value="preset.name">{{ preset.name }}</option>
                                </select>
                                <select v-model="row.values" multiple class="min-h-24 rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="`Merkmal ${index + 1}: Werte`">
                                    <option v-for="value in presetValuesFor(row.name)" :key="value" :value="value">{{ value }}</option>
                                </select>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" :aria-label="`Merkmal ${index + 1} entfernen`" @click="removeProductAttributeRow(index)">
                                    Entfernen
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="productForm.product_type === 'variable'" class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">{{ t('commerce.ui.variants') }}</h3>
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
                                    :aria-label="`Produktvariante ${index + 1}: ${attribute.name}`"
                                >
                                    <option value="">{{ attribute.name }}</option>
                                    <option v-for="value in attribute.values" :key="value" :value="value">{{ value }}</option>
                                </select>
                                <input v-model="variant.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR" :aria-label="`Produktvariante ${index + 1}: Preis in EUR`">
                                <input v-model="variant.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bestand" :aria-label="`Produktvariante ${index + 1}: Bestand`">
                                <input v-model="variant.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer" :aria-label="`Produktvariante ${index + 1}: Artikelnummer`">
                                <input v-model="variant.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL" :aria-label="`Produktvariante ${index + 1}: Bild-URL`">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" :aria-label="`Produktvariante ${index + 1} entfernen`" @click="removeProductVariantRow(index)">Variante entfernen</button>
                            </div>
                        </div>
                    </div>
                    <textarea v-model="productForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.description_placeholder')"></textarea>
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
                                Zur Prüfung
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
                                Löschen
                            </button>
                        </div>
                    </article>

                    <p v-if="!myProducts.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                        Noch keine eigenen Angebote eingereicht.
                    </p>
                </div>
            </article>

            <article v-if="websiteClubs.length" class="surface-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Vereinswebsite</h2>
                        <p class="mt-1 text-sm text-secondary">Website-Anfragen sind nur für Vereine sichtbar, für die du berechtigt bist.</p>
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
                            :aria-label="tAuto('Website-Anfrage schließen')"
                            @click="websiteRequestModal = false"
                        >
                            <i class="las la-times text-xl"></i>
                        </button>
                        <div class="mb-4 pr-12">
                            <p class="text-xs font-semibold uppercase text-air-blue">Verein</p>
                            <h2 class="mt-1 text-lg font-semibold text-primary">Vereinswebsite erstellen lassen</h2>
                            <p class="mt-1 text-sm text-secondary">Wähle den Verein und beschreibe kurz die gewünschte Website.</p>
                        </div>
                        <div class="grid gap-3">
                    <select v-model="websiteForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="club in websiteClubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <input v-model="websiteForm.domain" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gewünschte Domain">
                    <textarea v-model="websiteForm.goals" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Was soll die Website können?"></textarea>
                    <textarea v-model="websiteForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Weitere Hinweise"></textarea>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs leading-relaxed text-secondary">{{ t('agency.privacy_notice') }}</p>
                        <label class="mt-2 flex cursor-pointer items-start gap-2 text-sm font-semibold text-primary">
                            <input v-model="websiteForm.accepted_privacy" type="checkbox" class="mt-0.5 rounded border-border bg-inputBg text-air-blue focus:ring-air-blue">
                            <span>{{ t('agency.privacy_accept') }}</span>
                        </label>
                        <span v-if="websiteForm.errors.accepted_privacy" class="mt-1 block text-xs text-error">{{ websiteForm.errors.accepted_privacy }}</span>
                    </div>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="websiteForm.processing || !websiteForm.accepted_privacy">{{ t('agency.send') }}</button>
                        </div>
                    </form>
                </div>
            </article>
        </section>

        <section v-if="activeTab === 'provider'" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <article class="surface-card p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ t('commerce.ui.marketplace_provider') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">Sitzadresse & Öffentliches Profil</h2>
                        <p class="mt-1 max-w-2xl text-sm text-secondary">
                            Die Sitzadresse bleibt intern, solange du sie nicht freigibst. Kunden sehen nur die Daten, die du bewusst öffentlich schaltest.
                        </p>
                    </div>
                    <span class="rounded-full border border-border bg-bg px-3 py-1 text-xs font-semibold text-secondary">
                        {{ providerProfile?.status || 'draft' }}
                    </span>
                </div>

                <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="storeProviderProfile">
                    <div class="md:col-span-2 grid gap-4 md:grid-cols-3">
                        <label class="grid gap-1 text-sm font-semibold text-primary md:col-span-2">
                            Anzeigename im Marketplace
                            <input v-model="providerProfileForm.display_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Airmius Run Shop">
                            <span v-if="providerProfileForm.errors.display_name" class="text-xs text-error">{{ providerProfileForm.errors.display_name }}</span>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-primary">
                            Anbieterart
                            <select v-model="providerProfileForm.provider_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="private">Privatperson</option>
                                <option value="business">Unternehmen / Shop</option>
                                <option value="club">Verein</option>
                            </select>
                        </label>
                    </div>

                    <label class="grid gap-1 text-sm font-semibold text-primary">
                        Rechtlicher Name
                        <input v-model="providerProfileForm.legal_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Firma, Verein oder Vor- und Nachname">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold text-primary">
                        Support-E-Mail
                        <input v-model="providerProfileForm.support_email" type="email" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="support@example.com">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold text-primary">
                        Telefon
                        <input v-model="providerProfileForm.phone" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="+49 ...">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold text-primary">
                        Website
                        <input v-model="providerProfileForm.website" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.url_placeholder')">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold text-primary md:col-span-2">
                        Logo-URL
                        <input v-model="providerProfileForm.logo_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.logo_url_placeholder')">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold text-primary md:col-span-2">
                        Öffentliche Beschreibung
                        <textarea v-model="providerProfileForm.public_description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurz erklären, was Kunden bei dir kaufen, abholen oder buchen können."></textarea>
                    </label>

                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4">
                        <h3 class="font-semibold text-primary">Sitzadresse</h3>
                        <p class="mt-1 text-xs text-secondary">Diese Adresse nutzt du als Anbieteradresse. Öffentlich wird sie nur mit aktivierter Sichtbarkeit.</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-6">
                            <input v-model="providerProfileForm.legal_country" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" placeholder="DE" maxlength="2">
                            <input v-model="providerProfileForm.legal_postal_code" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" placeholder="PLZ">
                            <input v-model="providerProfileForm.legal_city" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-3" placeholder="Stadt">
                            <input v-model="providerProfileForm.legal_street" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-4" placeholder="Straße">
                            <input v-model="providerProfileForm.legal_house_number" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" placeholder="Hausnummer">
                            <input v-model="providerProfileForm.legal_state" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-6" placeholder="Bundesland / Region">
                        </div>
                    </div>

                    <div class="md:col-span-2 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-3">
                        <label class="flex items-start gap-3 text-sm text-secondary">
                            <input v-model="providerProfileForm.show_public_address" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                            <span><strong class="block text-primary">Sitzadresse anzeigen</strong>Nur aktivieren, wenn Kunden diese Adresse sehen sollen.</span>
                        </label>
                        <label class="flex items-start gap-3 text-sm text-secondary">
                            <input v-model="providerProfileForm.show_support_email" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                            <span><strong class="block text-primary">E-Mail anzeigen</strong>Für Rückfragen im Marketplace sichtbar.</span>
                        </label>
                        <label class="flex items-start gap-3 text-sm text-secondary">
                            <input v-model="providerProfileForm.show_phone" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                            <span><strong class="block text-primary">Telefon anzeigen</strong>Optional für lokale Shops oder Abholung.</span>
                        </label>
                    </div>

                    <button class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary md:col-span-2" :disabled="providerProfileForm.processing">
                        Anbieterprofil speichern
                    </button>
                </form>
            </article>

            <aside class="space-y-6">
                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Standorte</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">Boutique, Filiale oder Abholstelle</h2>
                    <p class="mt-1 text-sm text-secondary">Öffentliche Standorte können Kunden auf Anbieter- und Produktseiten sehen.</p>

                    <form class="mt-4 grid gap-3" @submit.prevent="saveProviderLocation">
                        <input v-model="providerLocationForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name, z. B. Airmius Store Saarbrücken">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <select v-model="providerLocationForm.type" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Standortart')">
                                <option value="pickup">Abholstelle</option>
                                <option value="boutique">Boutique</option>
                                <option value="branch">Filiale</option>
                                <option value="warehouse">Lager</option>
                                <option value="partner">Partnerstandort</option>
                            </select>
                            <input v-model="providerLocationForm.country" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="DE" maxlength="2">
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input v-model="providerLocationForm.postal_code" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ">
                            <input v-model="providerLocationForm.city" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Stadt">
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[1fr_7rem]">
                            <input v-model="providerLocationForm.street" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Straße">
                            <input v-model="providerLocationForm.house_number" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Nr.">
                        </div>
                        <textarea v-model="providerLocationForm.opening_hours" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Öffnungszeiten, z. B. Mo-Fr 10-18 Uhr"></textarea>
                        <textarea v-model="providerLocationForm.note" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Hinweis für Kunden, z. B. Abholung am Empfang"></textarea>
                        <input v-model="providerLocationForm.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL optional">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input v-model="providerLocationForm.phone" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Telefon optional">
                            <input v-model="providerLocationForm.email" type="email" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="E-Mail optional">
                        </div>
                        <div class="grid gap-2 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                            <label class="flex items-center gap-2"><input v-model="providerLocationForm.is_public" type="checkbox" class="rounded border-border bg-inputBg"> Öffentlich anzeigen</label>
                            <label class="flex items-center gap-2"><input v-model="providerLocationForm.pickup_enabled" type="checkbox" class="rounded border-border bg-inputBg"> Abholung möglich</label>
                            <label class="flex items-center gap-2"><input v-model="providerLocationForm.returns_enabled" type="checkbox" class="rounded border-border bg-inputBg"> Rückgabe möglich</label>
                        </div>
                        <div v-if="Object.keys(providerLocationForm.errors).length" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                            Bitte prüfe die Angaben zum Standort.
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="providerLocationForm.processing">
                                {{ editingProviderLocation ? 'Standort aktualisieren' : 'Standort hinzufügen' }}
                            </button>
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="resetProviderLocationForm">
                                Zurücksetzen
                            </button>
                        </div>
                    </form>
                </article>

                <article class="surface-card p-5">
                    <h2 class="text-lg font-bold text-primary">Gespeicherte Standorte</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="location in providerLocations" :key="location.id" class="rounded-lg border border-border bg-bg p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-primary">{{ location.name }}</h3>
                                    <p class="mt-1 text-sm text-secondary">{{ location.address }}</p>
                                </div>
                                <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ location.type }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                                <span v-if="location.is_public" class="rounded-full bg-success/10 px-2 py-1 text-success">Öffentlich</span>
                                <span v-if="location.pickup_enabled" class="rounded-full bg-air-blue/10 px-2 py-1 text-air-blue">Abholung</span>
                                <span v-if="location.returns_enabled" class="rounded-full bg-warning/10 px-2 py-1 text-warning">Rückgabe</span>
                            </div>
                            <p v-if="location.opening_hours" class="mt-3 text-sm text-secondary">{{ location.opening_hours }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="editProviderLocation(location)">Bearbeiten</button>
                                <button type="button" class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger" @click="destroyProviderLocation(location)">Löschen</button>
                            </div>
                        </div>
                        <p v-if="!providerLocations.length" class="rounded-lg border border-dashed border-border p-4 text-sm text-secondary">
                            Noch keine Filiale oder Abholstelle gespeichert.
                        </p>
                    </div>
                </article>
            </aside>
        </section>

        <section v-if="activeTab === 'payouts'" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <article class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Auszahlungsdaten</h2>
                <p class="mt-1 text-sm text-secondary">Hinterlege IBAN oder PayPal, damit Airmius Marketplace-Erlöse nach Prüfung auszahlen kann.</p>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storePayoutProfile">
                    <input v-model="payoutForm.account_holder" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.account_holder_placeholder')">
                    <input v-model="payoutForm.paypal_email" type="email" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PayPal-E-Mail">
                    <input v-model="payoutForm.iban" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.iban_placeholder')">
                    <input v-model="payoutForm.bic" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.bic_placeholder')">
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
                        <select v-model="payoutRequestForm.method" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Auszahlungsmethode')">
                            <option value="bank_transfer">Banküberweisung</option>
                            <option value="paypal">{{ t('commerce.payment.paypal') }}</option>
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
                            <p class="font-semibold text-primary">{{ formatMoney(payout.amount_cents, payout.currency) }}</p>
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
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.gross_cents, payoutSummary.currency) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Provision</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.commission_cents, payoutSummary.currency) }}</p>
                    </div>
                    <div class="rounded-lg border border-success/30 bg-success/10 p-3">
                        <p class="text-xs uppercase text-success">Auszahlbar</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.amount_cents, payoutSummary.currency) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">Bereits angefordert</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(payoutSummary.requested_cents, payoutSummary.currency) }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ payoutSummary.requested_count || 0 }} offene Auszahlungsanträge</p>
                    </div>
                </div>
            </aside>
        </section>

        <section v-if="activeTab === 'ads'" class="grid gap-6 xl:grid-cols-[28rem_minmax(0,1fr)]">
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
                            <label for="campaign-objective" class="text-xs font-semibold uppercase text-secondary">Ziel</label>
                            <select id="campaign-objective" v-model="campaignForm.objective" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
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
                            Placement entscheidet den Ort. Creative Format entscheidet nur Größe und Seitenverhältnis der Anzeige.
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
                        <input v-model="campaignForm.budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gesamtbudget in EUR" :aria-label="tAuto('Gesamtbudget in EUR')">
                        <input v-model="campaignForm.daily_budget_cents" v-bind="moneyInputAttrs" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tagesbudget in EUR">
                        <div>
                            <label for="campaign-starts-at" class="text-xs font-semibold uppercase text-secondary">Start</label>
                            <input id="campaign-starts-at" v-model="campaignForm.starts_at" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </div>
                        <div>
                            <label for="campaign-ends-at" class="text-xs font-semibold uppercase text-secondary">Ende</label>
                            <input id="campaign-ends-at" v-model="campaignForm.ends_at" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </div>
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
                            <option value="bank_transfer">Überweisung</option>
                            <option value="stripe">{{ t('commerce.payment.stripe') }}</option>
                            <option value="paypal">{{ t('commerce.payment.paypal') }}</option>
                        </select>
                    </div>
                    <label class="hidden items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                        <input v-model="adAcceptedTerms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            Ich akzeptiere AGB, Widerrufshinweise und nehme zur Kenntnis, dass die Kampagne erst nach Zahlung zur Prüfung eingereicht wird.
                            <Link :href="route('terms.show')" class="text-air-blue underline">{{ t('commerce.ui.terms') }}</Link>
                            <span> - </span>
                            <Link :href="route('legal.withdrawal')" class="text-air-blue underline">Widerruf</Link>
                        </span>
                    </label>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <AppButton type="submit" :loading="campaignForm.processing" :disabled="campaignForm.processing">
                            {{ campaignForm.processing ? 'Speichert...' : 'Kampagne speichern' }}
                        </AppButton>
                        <AppLoadingState v-if="campaignForm.processing" label="Kampagne wird gespeichert..." inline />
                    </div>
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
                                            Zur Prüfung
                                        </button>
                                        <button
                                            v-if="['pending_review', 'paused'].includes(campaign.status)"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="updateOwnCampaignStatus(campaign, 'draft')"
                                        >
                                            Zurückziehen
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger"
                                            @click="deleteOwnCampaign(campaign)"
                                        >
                                            Löschen
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
                    <p v-if="!myCampaigns.length" class="px-5 py-6 text-sm text-secondary">{{ t('commerce.ui.no_campaigns') }}</p>
                </div>
            </article>
        </section>

        <section v-if="activeTab === 'invoices'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Zentrale Übersicht</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Rechnungen und EinKäufe</h2>
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
                <p v-if="!purchaseHistory.length" class="px-5 py-6 text-sm text-secondary">Noch keine EinKäufe oder Rechnungen vorhanden.</p>
            </div>
        </section>

        <section v-if="activeTab === 'invoices'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Bestellungen, Probleme und Rücksendungen</h2>
                <p class="mt-1 text-sm text-secondary">Für Marketplace-Bestellungen kannst du hier Rechnungen laden, Probleme melden und Rücksendungen verfolgen.</p>
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
                                <p v-if="orderCanCancel(order)" class="mt-1 text-xs text-secondary">Noch nicht versendet: Storno ist möglich.</p>
                                <p v-else-if="order.shipping_status === 'shipped'" class="mt-1 text-xs text-secondary">Bereits versendet: Storno ist nicht mehr möglich. Nach Zustellung kannst du eine Rücksendung anfragen.</p>
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

        <section v-if="activeTab === 'invoices'" class="surface-card overflow-hidden">
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
                    :aria-label="tAuto('Produkt schließen')"
                    @click="closeEditProductModal"
                >
                    <i class="las la-times text-xl"></i>
                </button>
                <div class="pr-12">
                    <p class="text-xs font-semibold uppercase text-air-blue">Verkaufen</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Produkt bearbeiten</h2>
                    <p class="mt-1 text-sm text-secondary">Änderungen werden danach erneut geprüft, bevor sie im Marketplace sichtbar sind.</p>
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
                    <textarea v-model="editProductForm.description" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" :placeholder="t('commerce.ui.description_placeholder')"></textarea>
                </div>
                <div v-if="editProductForm.manages_stock" class="mt-4 rounded-lg border border-border bg-bg p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">Länderbestand</h3>
                            <p class="text-xs text-secondary">Steuert, in welchen Ländern dein Produkt sichtbar und kaufbar ist.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addEditProductInventoryRow">
                            Land hinzufügen
                        </button>
                    </div>
                    <div class="mt-3 space-y-3">
                        <div v-for="(inventory, index) in editProductForm.inventories" :key="index" class="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-6">
                            <select v-model="inventory.country_code" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="`Bearbeiten – Lagerland ${index + 1}`">
                                <option v-for="country in inventoryCountries" :key="country" :value="country">{{ country }}</option>
                            </select>
                            <input v-model="inventory.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bestand" :aria-label="`Bearbeiten – Lager ${index + 1}: Bestand`">
                            <input v-model="inventory.low_stock_threshold" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Warnbestand" :aria-label="`Bearbeiten – Lager ${index + 1}: Warnbestand`">
                            <input v-model="inventory.lead_time_days" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lieferzeit" :aria-label="`Bearbeiten – Lager ${index + 1}: Lieferzeit in Tagen`">
                            <input v-model="inventory.city" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerstadt" :aria-label="`Bearbeiten – Lager ${index + 1}: Stadt`">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" :aria-label="`Bearbeiten – Lager ${index + 1} entfernen`" @click="removeEditProductInventoryRow(index)">
                                Entfernen
                            </button>
                        </div>
                    </div>
                    <p v-if="!editProductForm.inventories.length" class="mt-3 text-sm text-secondary">Noch kein Länderbestand gepflegt.</p>
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
                <h2 class="text-lg font-semibold text-primary">Produkt löschen</h2>
                <p class="mt-2 text-sm text-secondary">
                    Gib <span class="font-semibold text-primary">delete</span> ein. Wenn es bereits Bestellungen gibt, wird das Produkt archiviert statt gelöscht.
                </p>
                <p class="mt-3 rounded-lg border border-border bg-bg p-3 text-sm font-semibold text-primary">{{ deleteProductModal.product?.title }}</p>
                <input v-model="deleteProductModal.confirmation" class="mt-4 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="delete">
                <div class="mt-5 flex justify-end gap-3">
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeDeleteProductModal">Abbrechen</button>
                    <button class="rounded-lg border border-danger/50 px-4 py-2 text-sm font-semibold text-danger" :disabled="deleteProductModal.confirmation !== 'delete'" @click="destroyOwnProduct">
                        Löschen
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
                    :aria-label="tAuto('Anzeigegruppe schließen')"
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
                        <label for="ad-group-placement" class="text-xs font-semibold uppercase text-secondary">Placement</label>
                        <select id="ad-group-placement" v-model="adGroupForm.placement" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="placement in adPlacements" :key="placement.key" :value="placement.key">{{ placement.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <label class="text-xs font-semibold uppercase text-secondary">{{ t('commerce.ui.sports') }}</label>
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
                                <span v-if="!selectedAdGroupSports.length" class="text-sm text-secondary">Noch keine Sportart gewählt.</span>
                            </div>
                            <input
                                v-model="adGroupSportQuery"
                                class="mt-3 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                                placeholder="Sportart suchen und aus Liste wählen"
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
                        <input v-model="adGroupForm.interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interessen, z. B. Fitness, Ausrüstung">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <select v-model="adGroupForm.gender" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Geschlecht')">
                            <option value="all">Alle Geschlechter</option>
                            <option value="female">Frauen</option>
                            <option value="male">Männer</option>
                            <option value="diverse">Divers</option>
                        </select>
                        <input v-model="adGroupForm.age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von" :aria-label="tAuto('Mindestalter')">
                        <input v-model="adGroupForm.age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis" :aria-label="tAuto('Höchstalter')">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="adGroupForm.locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ort/Region, z. B. Saarland, Berlin">
                        <input v-model="adGroupForm.zones" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Zone, z. B. 10 km um Saarbrücken">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <input v-model="adGroupForm.daily_budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tagesbudget in EUR">
                        <input v-model="adGroupForm.starts_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Start der Anzeigegruppe')">
                        <input v-model="adGroupForm.ends_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Ende der Anzeigegruppe')">
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
                    :aria-label="tAuto('Anzeige und Varianten schließen')"
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
                                <label class="text-xs font-semibold uppercase text-secondary">Varianten für A/B-Test</label>
                                <p class="mt-1 text-xs text-secondary">Jede Variante kann eigene Headline, Text, Ziel-URL, Bild-URL und Gewicht haben.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addAdCreativeRow">
                                Variante hinzufügen
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(creative, index) in adCreativeRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-card p-3">
                                <div class="grid gap-2 sm:grid-cols-[1fr_6rem_auto]">
                                    <input v-model="creative.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Variante A" :aria-label="`Variante ${index + 1}: Name`">
                                    <input v-model.number="creative.weight" type="number" min="1" max="1000" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gewicht" :aria-label="`Variante ${index + 1}: Gewicht`">
                                    <label class="flex items-center gap-2 text-xs font-semibold text-primary">
                                        <input v-model="creative.is_active" type="checkbox" class="rounded border-border bg-inputBg" :aria-label="`Variante ${index + 1}: Aktiv`">
                                        Aktiv
                                    </label>
                                </div>
                                <input v-model="creative.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Headline dieser Variante" :aria-label="`Variante ${index + 1}: Headline`">
                                <textarea v-model="creative.primary_text" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anzeigentext dieser Variante" :aria-label="`Variante ${index + 1}: Anzeigentext`"></textarea>
                                <textarea v-model="creative.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung dieser Variante" :aria-label="`Variante ${index + 1}: Beschreibung`"></textarea>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input v-model="creative.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL" :aria-label="`Variante ${index + 1}: Ziel-URL`">
                                    <input v-model="creative.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA, z. B. Jetzt ansehen" :aria-label="`Variante ${index + 1}: Handlungsaufforderung`">
                                </div>
                                <input v-model="creative.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL dieser Variante" :aria-label="`Variante ${index + 1}: Bild-URL`">
                                <button v-if="adCreativeRows.length > 1" type="button" class="justify-self-start rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" :aria-label="`Variante ${index + 1} entfernen`" @click="removeAdCreativeRow(index)">
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
                    :aria-label="tAuto('Bearbeiten schließen')"
                    @click="closeEditCampaignModal"
                >
                    <i class="las la-times text-xl"></i>
                </button>
                <div class="pr-12">
                    <h2 class="text-lg font-semibold text-primary">Ads-Kampagne bearbeiten</h2>
                    <p class="mt-2 text-sm text-secondary">
                        Bezahlte oder bereits aktive Kampagnen gehen nach Änderungen wieder zur Admin-Prüfung.
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
                            <label for="edit-campaign-objective" class="text-xs font-semibold uppercase text-secondary">Ziel</label>
                            <select id="edit-campaign-objective" v-model="editCampaignForm.objective" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="traffic">Traffic</option>
                                <option value="awareness">Reichweite</option>
                                <option value="leads">Leads</option>
                                <option value="sales">Sales</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit-campaign-placement" class="text-xs font-semibold uppercase text-secondary">Placement - wo erscheint die Ad?</label>
                            <select id="edit-campaign-placement" v-model="editCampaignForm.placement" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="placement in adPlacements" :key="placement.key" :value="placement.key">{{ placement.label }}</option>
                            </select>
                            <p class="mt-1 text-xs text-secondary">{{ selectedEditAdPlacement.hint }}</p>
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <label for="edit-campaign-creative-format" class="text-xs font-semibold uppercase text-secondary">Creative Format - welches Bildmass?</label>
                        <select id="edit-campaign-creative-format" v-model="editCampaignForm.creative_format" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="format in editCampaignAdFormats" :key="format.key" :value="format.key">
                                {{ format.label }} - {{ format.size }}
                            </option>
                        </select>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ selectedEditAdFormat.size }} - {{ selectedEditAdFormat.ratio }}</p>
                        <p class="text-xs text-secondary">{{ selectedEditAdFormat.hint }}</p>
                        <p class="mt-2 rounded-lg border border-border bg-card px-3 py-2 text-xs text-secondary">
                            Placement entscheidet den Ort. Creative Format entscheidet nur Größe und Seitenverhältnis der Anzeige.
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
                            width="640"
                            height="360"
                            loading="eager"
                            decoding="async"
                            class="max-h-64 w-full bg-inputBg object-contain"
                        >
                        <div v-else class="flex min-h-36 items-center justify-center px-4 py-8 text-center text-sm text-secondary">
                            Noch kein Anzeigenbild hinterlegt.
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-3">
                        <label for="edit-campaign-image-upload" class="text-xs font-semibold uppercase text-secondary">Neues Hauptbild hochladen</label>
                        <input id="edit-campaign-image-upload" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setEditCampaignCreativeUpload">
                    </div>

                    <div class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">A/B-Test Varianten</label>
                                <p class="mt-1 text-xs text-secondary">Bearbeite Headline, Text, Ziel-URL, Bild-URL und Gewicht.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addEditCampaignCreativeRow">
                                Variante hinzufügen
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(creative, index) in editCampaignCreativeRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-card p-3">
                                <div class="grid gap-2 sm:grid-cols-[1fr_6rem_auto]">
                                    <input v-model="creative.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Variante A" :aria-label="`Bearbeiten – Variante ${index + 1}: Name`">
                                    <input v-model.number="creative.weight" type="number" min="1" max="1000" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gewicht" :aria-label="`Bearbeiten – Variante ${index + 1}: Gewicht`">
                                    <label class="flex items-center gap-2 text-xs font-semibold text-primary">
                                        <input v-model="creative.is_active" type="checkbox" class="rounded border-border bg-inputBg" :aria-label="`Bearbeiten – Variante ${index + 1}: Aktiv`">
                                        Aktiv
                                    </label>
                                </div>
                                <input v-model="creative.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Headline dieser Variante" :aria-label="`Bearbeiten – Variante ${index + 1}: Headline`">
                                <textarea v-model="creative.primary_text" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anzeigentext dieser Variante" :aria-label="`Bearbeiten – Variante ${index + 1}: Anzeigentext`"></textarea>
                                <textarea v-model="creative.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung dieser Variante" :aria-label="`Bearbeiten – Variante ${index + 1}: Beschreibung`"></textarea>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input v-model="creative.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL optional" :aria-label="`Bearbeiten – Variante ${index + 1}: Ziel-URL`">
                                    <input v-model="creative.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA optional" :aria-label="`Bearbeiten – Variante ${index + 1}: Handlungsaufforderung`">
                                </div>
                                <input v-model="creative.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL dieser Variante" :aria-label="`Bearbeiten – Variante ${index + 1}: Bild-URL`">
                                <div v-if="creativePreviewUrl(creative)" class="overflow-hidden rounded-lg border border-border bg-bg">
                                    <p class="border-b border-border px-3 py-2 text-xs font-semibold uppercase text-secondary">Variantenbild</p>
                                    <img :src="creativePreviewUrl(creative)" :alt="creative.name || 'Variantenbild'" width="480" height="270" loading="lazy" decoding="async" class="max-h-40 w-full bg-inputBg object-contain">
                                </div>
                                <button v-if="editCampaignCreativeRows.length > 1" type="button" class="justify-self-start rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" :aria-label="`Bearbeiten – Variante ${index + 1} entfernen`" @click="removeEditCampaignCreativeRow(index)">
                                    Variante entfernen
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="editCampaignForm.budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary disabled:opacity-60" placeholder="Gesamtbudget in EUR" :disabled="editCampaignModal.campaign?.payment_completed">
                        <input v-model="editCampaignForm.daily_budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tagesbudget in EUR">
                        <input v-model="editCampaignForm.starts_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Start der Kampagne bearbeiten')">
                        <input v-model="editCampaignForm.ends_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tAuto('Ende der Kampagne bearbeiten')">
                    </div>
                    <p v-if="editCampaignModal.campaign?.payment_completed" class="text-xs text-secondary">Das bezahlte Gesamtbudget kann hier nicht nachträglich geändert werden.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="editCampaignForm.audience_age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von" :aria-label="tAuto('Mindestalter der Kampagne bearbeiten')">
                        <input v-model="editCampaignForm.audience_age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis" :aria-label="tAuto('Höchstalter der Kampagne bearbeiten')">
                    </div>
                    <input v-model="editCampaignForm.audience_locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Regionen, z. B. Berlin, NRW">
                    <input v-model="editCampaignForm.audience_interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interessen, z. B. Fußball, Fitness">
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
                <h2 class="text-lg font-semibold text-primary">Ads-Kampagne löschen</h2>
                <p class="mt-2 text-sm text-secondary">
                    Diese Kampagne wird dauerhaft gelöscht:
                    <span class="font-semibold text-primary">{{ deleteCampaignModal.campaign?.headline || deleteCampaignModal.campaign?.name }}</span>
                </p>
                <p class="mt-4 text-sm text-secondary">
                    Bitte gib <strong class="text-primary">delete</strong> ein, um die Löschung zu bestätigen.
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
                        Endgültig löschen
                    </button>
                </div>
            </div>
        </div>

        <Modal
            :show="checkoutConfirmation.open"
            max-width="lg"
            :closeable="!checkoutProcessing"
            class="z-[60]"
            aria-labelledby="commerce-checkout-confirmation-title"
            @close="closeCheckoutConfirmation"
        >
            <div class="p-2 sm:p-3">
                <div class="flex items-start justify-between gap-4 pe-10">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ t('commerce.checkout.confirm') }}</p>
                        <h2 id="commerce-checkout-confirmation-title" class="mt-1 text-lg font-semibold text-primary">{{ checkoutConfirmationTitle }}</h2>
                        <p class="mt-2 text-sm text-secondary">
                            {{ checkoutConfirmationPrice }}
                            <span v-if="checkoutConfirmation.type === 'account_plan'">/ {{ interval === 'yearly' ? ct('period_year') : ct('period_month') }}</span>
                            <span> · {{ providerLabel(checkoutConfirmation.provider) }}</span>
                        </p>
                    </div>
                </div>

                <label class="mt-5 flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                    <input v-model="checkoutConfirmation.accepted" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        {{ ct('provider_terms') }}
                        <Link :href="route('terms.show')" class="text-air-blue underline">{{ t('commerce.ui.terms') }}</Link>
                        <span> · </span>
                        <Link :href="route('legal.withdrawal')" class="text-air-blue underline">{{ ct('withdrawal') }}</Link>
                    </span>
                </label>

                <AppLoadingState
                    v-if="checkoutProcessing"
                    class="mt-4"
                    :label="ct('preparing')"
                    inline
                />

                <p v-if="checkoutError" class="mt-4 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger" role="alert">
                    {{ checkoutError }}
                </p>

                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <AppButton
                        type="button"
                        variant="secondary"
                        :disabled="checkoutProcessing"
                        @click="closeCheckoutConfirmation"
                    >
                        {{ ct('cancel') }}
                    </AppButton>
                    <AppButton
                        type="button"
                        :disabled="!checkoutConfirmation.accepted || checkoutProcessing"
                        :loading="checkoutProcessing"
                        @click="confirmCheckout"
                    >
                        {{ checkoutProcessing ? ct('preparing') : ct('continue_paid') }}
                    </AppButton>
                </div>
            </div>
        </Modal>

        <Modal
            :show="showCartCheckout"
            max-width="xl"
            :closeable="!cartCheckoutProcessing"
            class="z-[60]"
            aria-labelledby="commerce-cart-checkout-title"
            @close="showCartCheckout = false"
        >
            <form class="max-h-[calc(100dvh-4rem)] overflow-y-auto p-2 sm:p-3" @submit.prevent="checkoutCart">
                <h2 id="commerce-cart-checkout-title" class="text-lg font-semibold text-primary">{{ t('commerce.checkout.title') }}</h2>
                <p class="mt-2 text-sm text-secondary">
                    {{ ct('total') }}: {{ formatMoney(cart.summary?.amount_cents, cart.summary?.currency) }}
                </p>

                <div class="mt-4 rounded-xl border border-border bg-bg p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ t('commerce.checkout.selected_products') }}</p>
                    <div class="mt-3 space-y-3">
                        <div v-for="item in cartItems" :key="`checkout-${item.id}`" class="flex items-center gap-3">
                            <img v-if="item.product?.image_url" :src="item.product.image_url" :alt="item.product.title" width="48" height="48" loading="lazy" decoding="async" class="h-12 w-12 rounded-lg object-cover">
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
                    <select v-model="cartCheckoutForm.shipping_country" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="ct('shipping_country')">
                        <option v-for="country in pricingCountries" :key="country.country" :value="country.country">{{ country.label }}</option>
                    </select>
                    <select v-model="cartCheckoutForm.provider" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('commerce.ui.payment_method')">
                        <option value="bank_transfer">{{ t('commerce.payment.bank_transfer') }}</option>
                        <option value="stripe">{{ t('commerce.payment.stripe') }}</option>
                        <option value="paypal">{{ t('commerce.payment.paypal') }}</option>
                    </select>
                    <select v-model="cartCheckoutForm.customer_type" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="ct('customer_type')">
                        <option value="consumer">{{ t('commerce.checkout.consumer') }}</option>
                        <option value="business">{{ t('commerce.checkout.business') }}</option>
                    </select>
                    <input v-if="cartCheckoutForm.customer_type === 'business'" v-model="cartCheckoutForm.customer_vat_id" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" :aria-label="t('commerce.checkout.vat_id_placeholder')" :placeholder="t('commerce.checkout.vat_id_placeholder')">
                    <input v-if="cartCheckoutForm.customer_type === 'business'" v-model="cartCheckoutForm.customer_company" class="rounded-lg border-border bg-inputBg text-sm text-primary sm:col-span-2" :aria-label="t('commerce.checkout.company_placeholder')" :placeholder="t('commerce.checkout.company_placeholder')">
                    <input v-model="cartCheckoutForm.shipping_street" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('commerce.checkout.street_placeholder')" :placeholder="t('commerce.checkout.street_placeholder')">
                    <input v-model="cartCheckoutForm.shipping_house_number" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('commerce.checkout.house_number_placeholder')" :placeholder="t('commerce.checkout.house_number_placeholder')">
                    <input v-model="cartCheckoutForm.shipping_postal_code" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('commerce.checkout.postal_code_placeholder')" :placeholder="t('commerce.checkout.postal_code_placeholder')">
                    <input v-model="cartCheckoutForm.shipping_city" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('commerce.checkout.city_placeholder')" :placeholder="t('commerce.checkout.city_placeholder')">
                </div>

                <label class="mt-4 flex items-start gap-3 text-sm text-secondary">
                    <input v-model="cartCheckoutForm.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>{{ t('commerce.checkout.accept_terms') }}</span>
                </label>

                <p v-if="cartCheckoutForm.errors.accepted_terms || checkoutError" class="mt-3 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm font-semibold text-danger" role="alert">
                    {{ cartCheckoutForm.errors.accepted_terms || checkoutError }}
                </p>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" :disabled="cartCheckoutProcessing" @click="showCartCheckout = false">{{ ct('cancel') }}</button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="cartCheckoutProcessing || !cartCheckoutForm.accepted_terms">
                        {{ cartCheckoutProcessing ? ct('processing') : ct('continue_paid') }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
