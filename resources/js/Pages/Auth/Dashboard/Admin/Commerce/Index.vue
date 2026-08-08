<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import AdminCommerceAdPricingPanel from '@/Components/Admin/Commerce/AdminCommerceAdPricingPanel.vue'
import AdminCommerceAdsPanel from '@/Components/Admin/Commerce/AdminCommerceAdsPanel.vue'
import AdminCommerceEditProductModal from '@/Components/Admin/Commerce/AdminCommerceEditProductModal.vue'
import AdminCommerceGuidedAssistant from '@/Components/Admin/Commerce/AdminCommerceGuidedAssistant.vue'
import AdminCommerceIssueReplyModal from '@/Components/Admin/Commerce/AdminCommerceIssueReplyModal.vue'
import AdminCommerceMarketingPanel from '@/Components/Admin/Commerce/AdminCommerceMarketingPanel.vue'
import AdminCommerceMarketplacePanel from '@/Components/Admin/Commerce/AdminCommerceMarketplacePanel.vue'
import AdminCommerceOrdersPanel from '@/Components/Admin/Commerce/AdminCommerceOrdersPanel.vue'
import AdminCommercePageHeader from '@/Components/Admin/Commerce/AdminCommercePageHeader.vue'
import AdminCommercePayoutsPanel from '@/Components/Admin/Commerce/AdminCommercePayoutsPanel.vue'
import AdminCommerceProductDeleteModal from '@/Components/Admin/Commerce/AdminCommerceProductDeleteModal.vue'
import AdminCommerceProviderPanel from '@/Components/Admin/Commerce/AdminCommerceProviderPanel.vue'
import AdminCommerceRefundModal from '@/Components/Admin/Commerce/AdminCommerceRefundModal.vue'
import AdminCommerceRejectProductModal from '@/Components/Admin/Commerce/AdminCommerceRejectProductModal.vue'
import AdminCommerceReportsPanel from '@/Components/Admin/Commerce/AdminCommerceReportsPanel.vue'
import AdminCommerceSettingsPanel from '@/Components/Admin/Commerce/AdminCommerceSettingsPanel.vue'
import AdminCommerceShippingModal from '@/Components/Admin/Commerce/AdminCommerceShippingModal.vue'
import AdminCommerceSummaryGrid from '@/Components/Admin/Commerce/AdminCommerceSummaryGrid.vue'
import AdminCommerceTabNav from '@/Components/Admin/Commerce/AdminCommerceTabNav.vue'
import AdminCommerceWebsiteRequestsPanel from '@/Components/Admin/Commerce/AdminCommerceWebsiteRequestsPanel.vue'
import { useAdminCommerceWorkspace } from '@/composables/useAdminCommerceWorkspace'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t } = useI18n()

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
    providerProfile: { type: Object, default: null },
    providerLocations: { type: Array, default: () => [] },
})

const {
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
} = useAdminCommerceWorkspace(props)
</script>

<template>
    <Head :title="t('Commerce')" />

    <div class="space-y-6">
        <AdminCommercePageHeader
            :campaign-status-error="page.props.errors?.campaign_status"
            :success="page.props.flash?.success"
        />

        <AdminCommerceSummaryGrid :cards="summaryCards" />

        <AdminCommerceTabNav v-model:active-tab="activeTab" :tabs="tabs" />

        <AdminCommerceGuidedAssistant
            :active-tab="activeTab"
            :addons="addons"
            :campaigns="campaigns"
            :coupons="coupons"
            :orders="orders"
            :payout-candidates="payoutCandidates"
            :payouts="payouts"
            :products="products"
            :provider-locations="providerLocations"
            :return-requests="returnRequests"
            :seller-applications="sellerApplications"
            :shipping-rates="shippingRates"
            :tabs="tabs"
            :tax-rates="taxRates"
            :website-requests="websiteRequests"
            @select-tab="activeTab = $event"
        />

        <AdminCommerceProviderPanel
            v-if="activeTab === 'provider'"
            :editing-provider-location="editingProviderLocation"
            :provider-location-form="providerLocationForm"
            :provider-locations="providerLocations"
            :provider-profile="providerProfile"
            :provider-profile-form="providerProfileForm"
            @destroy-provider-location="destroyProviderLocation"
            @edit-provider-location="editProviderLocation"
            @reset-provider-location-form="resetProviderLocationForm"
            @save-provider-location="saveProviderLocation"
            @store-provider-profile="storeProviderProfile"
        />

        <AdminCommerceSettingsPanel
            v-if="activeTab === 'settings'"
            :add-marketplace-commission-row="addMarketplaceCommissionRow"
            :cents-to-major="centsToMajor"
            :commerce-settings-form="commerceSettingsForm"
            :format-money="formatMoney"
            :marketplace-commission-form="marketplaceCommissionForm"
            :money-input-attrs="moneyInputAttrs"
            :oss-report="ossReport"
            :remove-marketplace-commission-row="removeMarketplaceCommissionRow"
            :shipping-rate-form="shippingRateForm"
            :shipping-rates="shippingRates"
            :store-shipping-rate="storeShippingRate"
            :store-tax-rate="storeTaxRate"
            :tax-rate-form="taxRateForm"
            :tax-rates="taxRates"
            :update-commerce-settings="updateCommerceSettings"
            :update-marketplace-commissions="updateMarketplaceCommissions"
            :update-shipping-rate="updateShippingRate"
            :update-tax-rate="updateTaxRate"
        />

        <AdminCommerceAdPricingPanel
            v-if="activeTab === 'ad-prices'"
            :ad-pricing-cards="adPricingCards"
            :commerce-settings-form="commerceSettingsForm"
            :format-money="formatMoney"
            :major-to-cents="majorToCents"
            :money-input-attrs="moneyInputAttrs"
            :update-commerce-settings="updateCommerceSettings"
        />

        <AdminCommerceMarketingPanel
            v-if="activeTab === 'marketing'"
            :addon-form="addonForm"
            :addons="addons"
            :coupon-form="couponForm"
            :coupons="coupons"
            :format-money="formatMoney"
            :money-input-attrs="moneyInputAttrs"
            :store-addon="storeAddon"
            :store-coupon="storeCoupon"
        />

        <AdminCommerceMarketplacePanel
            v-if="activeTab === 'marketplace'"
            :attribute-presets="attributePresets"
            :format-date-time="formatDateTime"
            :format-money="formatMoney"
            :inventory-adjustment="inventoryAdjustment"
            :marketplace-visual-form="marketplaceVisualForm"
            :marketplace-visuals="marketplaceVisuals"
            :money-input-attrs="moneyInputAttrs"
            :normalize-attribute-rows="normalizeAttributeRows"
            :preset-values-for="presetValuesFor"
            :product-attribute-rows="productAttributeRows"
            :product-feature-rows="productFeatureRows"
            :product-form="productForm"
            :product-variant-rows="productVariantRows"
            :products="products"
            :seller-application-status-label="sellerApplicationStatusLabel"
            :seller-applications="sellerApplications"
            :stock-adjustments="stockAdjustments"
            :warehouses="warehouses"
            @add-product-attribute-row="addProductAttributeRow"
            @add-product-feature-row="addProductFeatureRow"
            @add-product-variant-row="addProductVariantRow"
            @adjust-global-stock="adjustGlobalStock"
            @adjust-inventory-stock="adjustInventoryStock"
            @open-delete-product="openDeleteProduct"
            @open-edit-product="openEditProduct"
            @remove-product-attribute-row="removeProductAttributeRow"
            @remove-product-feature-row="removeProductFeatureRow"
            @remove-product-variant-row="removeProductVariantRow"
            @set-inventory-adjustment="setInventoryAdjustment"
            @set-marketplace-visual-upload="setMarketplaceVisualUpload"
            @set-product-gallery-uploads="setProductGalleryUploads"
            @set-product-image-upload="setProductImageUpload"
            @store-product="storeProduct"
            @update-marketplace-visuals="updateMarketplaceVisuals"
            @update-product-status="updateProductStatus"
            @update-product-stock="updateProductStock"
            @update-seller-application="updateSellerApplication"
        />

        <AdminCommerceAdsPanel
            v-if="activeTab === 'ads'"
            :ad-diagnostics="adDiagnostics"
            :ad-formats="adFormats"
            :ad-placement-report="adPlacementReport"
            :ad-report="adReport"
            :budget-usage="budgetUsage"
            :campaign-creative-rows="campaignCreativeRows"
            :campaign-form="campaignForm"
            :campaign-status-error="campaignStatusError"
            :campaigns="campaigns"
            :ctr="ctr"
            :format-money="formatMoney"
            :format-percent="formatPercent"
            :money-input-attrs="moneyInputAttrs"
            :page-campaign-status-error="page.props.errors?.campaign_status"
            :selected-ad-format="selectedAdFormat"
            @add-campaign-creative-row="addCampaignCreativeRow"
            @remove-campaign-creative-row="removeCampaignCreativeRow"
            @set-campaign-creative-upload="setCampaignCreativeUpload"
            @store-campaign="storeCampaign"
            @update-campaign-status="updateCampaignStatus"
        />

        <AdminCommercePayoutsPanel
            v-if="activeTab === 'payouts'"
            :format-money="formatMoney"
            :payout-candidates="payoutCandidates"
            :payout-profiles="payoutProfiles"
            :payouts="payouts"
            @create-payout="createPayout"
            @mark-payout-paid="markPayoutPaid"
            @update-payout-profile="updatePayoutProfile"
        />

        <AdminCommerceOrdersPanel
            v-if="activeTab === 'orders'"
            :format-date-time="formatDateTime"
            :format-money="formatMoney"
            :order-issue-label="orderIssueLabel"
            :order-payment-hint="orderPaymentHint"
            :order-payment-label="orderPaymentLabel"
            :order-shipping-label="orderShippingLabel"
            :orders="orders"
            :reported-orders="reportedOrders"
            :return-requests="returnRequests"
            @mark-order-paid="markOrderPaid"
            @open-issue-reply="openIssueReplyModal"
            @open-refund="openRefundModal"
            @open-shipping="openShippingModal"
            @update-order-issue="updateOrderIssue"
            @update-return-request="updateReturnRequest"
        />

        <AdminCommerceReportsPanel
            v-if="activeTab === 'reports'"
            :audit-logs="auditLogs"
            :seller-reports="sellerReports"
        />

        <AdminCommerceWebsiteRequestsPanel
            v-if="activeTab === 'websites'"
            :website-requests="websiteRequests"
            @update-website-request="updateWebsiteRequest"
        />
    </div>

    <AdminCommerceEditProductModal
        :attribute-presets="attributePresets"
        :attribute-rows="editAttributeRows"
        :feature-rows="editFeatureRows"
        :form="editProductForm"
        :money-input-attrs="moneyInputAttrs"
        :normalize-attribute-rows="normalizeAttributeRows"
        :preset-values-for="presetValuesFor"
        :variant-rows="editVariantRows"
        @add-attribute-row="addEditAttributeRow"
        @add-feature-row="addEditFeatureRow"
        @add-variant-row="addEditVariantRow"
        @close="closeEditProduct"
        @remove-attribute-row="removeEditAttributeRow"
        @remove-feature-row="removeEditFeatureRow"
        @remove-variant-row="removeEditVariantRow"
        @set-gallery-uploads="setEditProductGalleryUploads"
        @set-image-upload="setEditProductImageUpload"
        @submit="submitEditProduct"
    />

    <AdminCommerceRejectProductModal
        :form="rejectionModal"
        :reasons="rejectionReasons"
        @close="rejectionModal.open = false"
        @select-reason="selectRejectionReason"
        @submit="submitRejection"
    />

    <AdminCommerceProductDeleteModal
        :modal="productDeleteModal"
        @close="closeDeleteProduct"
        @confirm="destroyProduct"
        @update:confirmation="setProductDeleteConfirmation"
    />

    <AdminCommerceShippingModal
        :carriers="shippingCarriers"
        :form="shippingModal"
        @autofill-tracking-url="autofillTrackingUrl"
        @close="shippingModal.open = false"
        @submit="submitShipping"
    />

    <AdminCommerceRefundModal
        :form="refundModal"
        :money-input-attrs="moneyInputAttrs"
        @close="refundModal.open = false"
        @submit="submitRefund"
    />

    <AdminCommerceIssueReplyModal
        :form="issueReplyModal"
        @close="closeIssueReplyModal"
        @submit="submitIssueReply"
    />
</template>
