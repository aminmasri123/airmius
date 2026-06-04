<script setup>
import CommerceMyProductsList from './CommerceMyProductsList.vue'
import CommerceProductCreateModal from './CommerceProductCreateModal.vue'
import CommerceSellerAccessPanel from './CommerceSellerAccessPanel.vue'
import CommerceWebsiteRequestsPanel from './CommerceWebsiteRequestsPanel.vue'

defineProps({
    clubs: { type: Array, default: () => [] },
    sellerCanSell: { type: Boolean, default: false },
    sellerApplication: { type: Object, default: null },
    sellerReadiness: { type: Object, default: null },
    productCreateModal: { type: Boolean, default: false },
    websiteRequestModal: { type: Boolean, default: false },
    productImportForm: { type: Object, required: true },
    sellerApplicationForm: { type: Object, required: true },
    productForm: { type: Object, required: true },
    myProducts: { type: Array, default: () => [] },
    websiteRequests: { type: Array, default: () => [] },
    websiteForm: { type: Object, required: true },
    learningCourses: { type: Array, default: () => [] },
    sellerMarketplaceCategories: { type: Array, default: () => [] },
    selectedProductCommission: { type: Object, default: () => ({ label: '', commission_percent: 0 }) },
    productCommissionPreviewCents: { type: Number, default: 0 },
    productSellerPayoutPreviewCents: { type: Number, default: 0 },
    productStockRequired: { type: Boolean, default: false },
    isLearningOffer: { type: Boolean, default: false },
    inventoryCountries: { type: Array, default: () => [] },
    inventoryTotalStock: { type: Number, default: 0 },
    productAttributeRows: { type: Array, default: () => [] },
    productVariantRows: { type: Array, default: () => [] },
    attributePresets: { type: Array, default: () => [] },
    moneyInputAttrs: { type: Object, default: () => ({}) },
    formatMoney: { type: Function, required: true },
    formatDateTime: { type: Function, required: true },
    offerTypeLabel: { type: Function, required: true },
    productStatusLabel: { type: Function, required: true },
    sellerApplicationStatusLabel: { type: Function, required: true },
    presetValuesFor: { type: Function, required: true },
    normalizeAttributeRows: { type: Function, required: true },
})

const emit = defineEmits([
    'update:productCreateModal',
    'update:websiteRequestModal',
    'import-products',
    'set-product-import-file',
    'store-seller-application',
    'store-product',
    'set-product-image-upload',
    'set-product-gallery-uploads',
    'add-product-inventory-row',
    'remove-product-inventory-row',
    'add-product-attribute-row',
    'remove-product-attribute-row',
    'add-product-variant-row',
    'remove-product-variant-row',
    'open-edit-product-modal',
    'update-own-product-status',
    'open-delete-product-modal',
    'open-website-request-modal',
    'store-website-request',
])
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-2">
        <article class="surface-card p-5">
            <CommerceSellerAccessPanel
                :product-import-form="productImportForm"
                :seller-application="sellerApplication"
                :seller-readiness="sellerReadiness"
                :seller-application-form="sellerApplicationForm"
                :seller-application-status-label="sellerApplicationStatusLabel"
                :seller-can-sell="sellerCanSell"
                @create-product="emit('update:productCreateModal', true)"
                @import-products="emit('import-products')"
                @set-product-import-file="emit('set-product-import-file', $event)"
                @store-seller-application="emit('store-seller-application')"
            />

            <CommerceProductCreateModal
                v-if="sellerCanSell && productCreateModal"
                :clubs="clubs"
                :product-form="productForm"
                :learning-courses="learningCourses"
                :seller-marketplace-categories="sellerMarketplaceCategories"
                :selected-product-commission="selectedProductCommission"
                :product-commission-preview-cents="productCommissionPreviewCents"
                :product-seller-payout-preview-cents="productSellerPayoutPreviewCents"
                :product-stock-required="productStockRequired"
                :is-learning-offer="isLearningOffer"
                :inventory-countries="inventoryCountries"
                :inventory-total-stock="inventoryTotalStock"
                :product-attribute-rows="productAttributeRows"
                :product-variant-rows="productVariantRows"
                :attribute-presets="attributePresets"
                :money-input-attrs="moneyInputAttrs"
                :format-money="formatMoney"
                :preset-values-for="presetValuesFor"
                :normalize-attribute-rows="normalizeAttributeRows"
                @close="emit('update:productCreateModal', false)"
                @store-product="emit('store-product')"
                @set-product-image-upload="emit('set-product-image-upload', $event)"
                @set-product-gallery-uploads="emit('set-product-gallery-uploads', $event)"
                @add-product-inventory-row="emit('add-product-inventory-row')"
                @remove-product-inventory-row="emit('remove-product-inventory-row', $event)"
                @add-product-attribute-row="emit('add-product-attribute-row')"
                @remove-product-attribute-row="emit('remove-product-attribute-row', $event)"
                @add-product-variant-row="emit('add-product-variant-row')"
                @remove-product-variant-row="emit('remove-product-variant-row', $event)"
            />

            <CommerceMyProductsList
                :my-products="myProducts"
                :format-date-time="formatDateTime"
                :format-money="formatMoney"
                :offer-type-label="offerTypeLabel"
                :product-status-label="productStatusLabel"
                @open-edit-product-modal="emit('open-edit-product-modal', $event)"
                @update-own-product-status="(product, status) => emit('update-own-product-status', product, status)"
                @open-delete-product-modal="emit('open-delete-product-modal', $event)"
            />
        </article>

        <CommerceWebsiteRequestsPanel
            v-if="clubs.length"
            :clubs="clubs"
            :website-form="websiteForm"
            :website-request-modal="websiteRequestModal"
            :website-requests="websiteRequests"
            @open-website-request-modal="emit('open-website-request-modal')"
            @update:website-request-modal="emit('update:websiteRequestModal', $event)"
            @store-website-request="emit('store-website-request')"
        />
    </section>
</template>
