<script setup>
import AdminCommerceMarketplaceVisualsPanel from './AdminCommerceMarketplaceVisualsPanel.vue'
import AdminCommerceProductForm from './AdminCommerceProductForm.vue'
import AdminCommerceProductInventoryList from './AdminCommerceProductInventoryList.vue'
import AdminCommerceSellerApplications from './AdminCommerceSellerApplications.vue'

defineProps({
    sellerApplications: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    marketplaceVisuals: { type: Array, default: () => [] },
    productForm: { type: Object, required: true },
    productAttributeRows: { type: Array, default: () => [] },
    productFeatureRows: { type: Array, default: () => [] },
    productVariantRows: { type: Array, default: () => [] },
    marketplaceVisualForm: { type: Object, required: true },
    stockAdjustments: { type: Object, default: () => ({}) },
    attributePresets: { type: Array, default: () => [] },
    moneyInputAttrs: { type: Object, default: () => ({}) },
    sellerApplicationStatusLabel: { type: Function, required: true },
    formatDateTime: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
    presetValuesFor: { type: Function, required: true },
    normalizeAttributeRows: { type: Function, required: true },
    inventoryAdjustment: { type: Function, required: true },
})

const emit = defineEmits([
    'update-seller-application',
    'store-product',
    'set-product-image-upload',
    'set-product-gallery-uploads',
    'add-product-attribute-row',
    'remove-product-attribute-row',
    'add-product-variant-row',
    'remove-product-variant-row',
    'add-product-feature-row',
    'remove-product-feature-row',
    'set-inventory-adjustment',
    'adjust-inventory-stock',
    'update-product-stock',
    'adjust-global-stock',
    'update-product-status',
    'open-edit-product',
    'open-delete-product',
    'update-marketplace-visuals',
    'set-marketplace-visual-upload',
])
</script>

<template>
    <div class="space-y-6">
        <section class="grid gap-6">
            <AdminCommerceSellerApplications
                :format-date-time="formatDateTime"
                :seller-applications="sellerApplications"
                :seller-application-status-label="sellerApplicationStatusLabel"
                @update-seller-application="(application, status) => emit('update-seller-application', application, status)"
            />

            <article class="surface-card p-5">
                <AdminCommerceProductForm
                    :attribute-presets="attributePresets"
                    :money-input-attrs="moneyInputAttrs"
                    :normalize-attribute-rows="normalizeAttributeRows"
                    :preset-values-for="presetValuesFor"
                    :product-attribute-rows="productAttributeRows"
                    :product-feature-rows="productFeatureRows"
                    :product-form="productForm"
                    :product-variant-rows="productVariantRows"
                    @store-product="emit('store-product')"
                    @set-product-image-upload="emit('set-product-image-upload', $event)"
                    @set-product-gallery-uploads="emit('set-product-gallery-uploads', $event)"
                    @add-product-attribute-row="emit('add-product-attribute-row')"
                    @remove-product-attribute-row="emit('remove-product-attribute-row', $event)"
                    @add-product-variant-row="emit('add-product-variant-row')"
                    @remove-product-variant-row="emit('remove-product-variant-row', $event)"
                    @add-product-feature-row="emit('add-product-feature-row')"
                    @remove-product-feature-row="emit('remove-product-feature-row', $event)"
                />

                <AdminCommerceProductInventoryList
                    :format-money="formatMoney"
                    :inventory-adjustment="inventoryAdjustment"
                    :products="products"
                    :stock-adjustments="stockAdjustments"
                    :warehouses="warehouses"
                    @set-inventory-adjustment="(product, inventory, key, value) => emit('set-inventory-adjustment', product, inventory, key, value)"
                    @adjust-inventory-stock="(product, inventory) => emit('adjust-inventory-stock', product, inventory)"
                    @update-product-stock="emit('update-product-stock', $event)"
                    @adjust-global-stock="emit('adjust-global-stock', $event)"
                    @update-product-status="(product, status) => emit('update-product-status', product, status)"
                    @open-edit-product="emit('open-edit-product', $event)"
                    @open-delete-product="emit('open-delete-product', $event)"
                />
            </article>
        </section>

        <AdminCommerceMarketplaceVisualsPanel
            :marketplace-visual-form="marketplaceVisualForm"
            :marketplace-visuals="marketplaceVisuals"
            @update-marketplace-visuals="emit('update-marketplace-visuals')"
            @set-marketplace-visual-upload="(key, event) => emit('set-marketplace-visual-upload', key, event)"
        />
    </div>
</template>
