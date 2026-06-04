<script setup>
import CommerceAdCreativeModal from '@/Components/Commerce/CommerceAdCreativeModal.vue'
import CommerceAdGroupModal from '@/Components/Commerce/CommerceAdGroupModal.vue'
import CommerceCartCheckoutModal from '@/Components/Commerce/CommerceCartCheckoutModal.vue'
import CommerceCheckoutConfirmationModal from '@/Components/Commerce/CommerceCheckoutConfirmationModal.vue'
import CommerceDeleteCampaignModal from '@/Components/Commerce/CommerceDeleteCampaignModal.vue'
import CommerceDeleteProductModal from '@/Components/Commerce/CommerceDeleteProductModal.vue'
import CommerceEditCampaignModal from '@/Components/Commerce/CommerceEditCampaignModal.vue'
import CommerceEditProductModal from '@/Components/Commerce/CommerceEditProductModal.vue'
import CommerceOrderRequestModal from '@/Components/Commerce/CommerceOrderRequestModal.vue'

defineProps({
    editProductForm: { type: Object, required: true },
    inventoryCountries: { type: Array, default: () => [] },
    editProductModal: { type: Object, required: true },
    moneyInputAttrs: { type: Object, default: () => ({}) },
    addEditProductInventoryRow: { type: Function, required: true },
    closeEditProductModal: { type: Function, required: true },
    removeEditProductInventoryRow: { type: Function, required: true },
    setEditProductImageUpload: { type: Function, required: true },
    submitEditProduct: { type: Function, required: true },
    deleteProductModal: { type: Object, required: true },
    closeDeleteProductModal: { type: Function, required: true },
    destroyOwnProduct: { type: Function, required: true },
    setDeleteProductConfirmation: { type: Function, required: true },
    issueModal: { type: Object, required: true },
    closeIssueModal: { type: Function, required: true },
    submitOrderRequest: { type: Function, required: true },
    setIssueNote: { type: Function, required: true },
    adGroupSportQuery: { type: String, default: '' },
    adPlacements: { type: Array, default: () => [] },
    filteredAdGroupSports: { type: Array, default: () => [] },
    adGroupForm: { type: Object, required: true },
    adGroupModal: { type: Object, required: true },
    selectedAdGroupSports: { type: Array, default: () => [] },
    sportLabel: { type: Function, required: true },
    addAdGroupSport: { type: Function, required: true },
    closeAdGroupModal: { type: Function, required: true },
    removeAdGroupSport: { type: Function, required: true },
    storeAdGroup: { type: Function, required: true },
    adCreativeRows: { type: Array, default: () => [] },
    adCreativeForm: { type: Object, required: true },
    adCreativeModal: { type: Object, required: true },
    addAdCreativeRow: { type: Function, required: true },
    closeAdCreativeModal: { type: Function, required: true },
    removeAdCreativeRow: { type: Function, required: true },
    storeAdCreatives: { type: Function, required: true },
    editCampaignForm: { type: Object, required: true },
    editCampaignModal: { type: Object, required: true },
    editCampaignAdFormats: { type: Array, default: () => [] },
    editCampaignCreativeRows: { type: Array, default: () => [] },
    editCampaignPreviewUrl: { type: String, default: '' },
    selectedEditAdFormat: { type: Object, default: () => ({}) },
    selectedEditAdPlacement: { type: Object, default: () => ({}) },
    creativePreviewUrl: { type: Function, required: true },
    addEditCampaignCreativeRow: { type: Function, required: true },
    closeEditCampaignModal: { type: Function, required: true },
    removeEditCampaignCreativeRow: { type: Function, required: true },
    setEditCampaignCreativeUpload: { type: Function, required: true },
    submitEditCampaign: { type: Function, required: true },
    deleteCampaignModal: { type: Object, required: true },
    closeDeleteCampaignModal: { type: Function, required: true },
    confirmDeleteOwnCampaign: { type: Function, required: true },
    setDeleteCampaignConfirmation: { type: Function, required: true },
    checkoutConfirmation: { type: Object, required: true },
    interval: { type: String, default: 'monthly' },
    checkoutConfirmationPrice: { type: String, default: '' },
    providerLabel: { type: Function, required: true },
    checkoutConfirmationTitle: { type: String, default: '' },
    closeCheckoutConfirmation: { type: Function, required: true },
    confirmCheckout: { type: Function, required: true },
    setCheckoutAccepted: { type: Function, required: true },
    cart: { type: Object, default: () => ({ items: [], summary: {} }) },
    cartItems: { type: Array, default: () => [] },
    cartCheckoutForm: { type: Object, required: true },
    formatMoney: { type: Function, required: true },
    showCartCheckout: { type: Boolean, default: false },
    pricingCountries: { type: Array, default: () => [] },
    checkoutCart: { type: Function, required: true },
})

const emit = defineEmits(['update:adGroupSportQuery', 'update:showCartCheckout'])
</script>

<template>
    <CommerceEditProductModal
        :form="editProductForm"
        :inventory-countries="inventoryCountries"
        :modal="editProductModal"
        :money-input-attrs="moneyInputAttrs"
        @add-inventory-row="addEditProductInventoryRow"
        @close="closeEditProductModal"
        @remove-inventory-row="removeEditProductInventoryRow"
        @set-image-upload="setEditProductImageUpload"
        @submit="submitEditProduct"
    />

    <CommerceDeleteProductModal
        :modal="deleteProductModal"
        @close="closeDeleteProductModal"
        @confirm="destroyOwnProduct"
        @update:confirmation="setDeleteProductConfirmation"
    />

    <CommerceOrderRequestModal
        :modal="issueModal"
        @close="closeIssueModal"
        @submit="submitOrderRequest"
        @update:note="setIssueNote"
    />

    <CommerceAdGroupModal
        :ad-group-sport-query="adGroupSportQuery"
        :ad-placements="adPlacements"
        :filtered-ad-group-sports="filteredAdGroupSports"
        :form="adGroupForm"
        :modal="adGroupModal"
        :money-input-attrs="moneyInputAttrs"
        :selected-ad-group-sports="selectedAdGroupSports"
        :sport-label="sportLabel"
        @add-sport="addAdGroupSport"
        @close="closeAdGroupModal"
        @remove-sport="removeAdGroupSport"
        @submit="storeAdGroup"
        @update:ad-group-sport-query="emit('update:adGroupSportQuery', $event)"
    />

    <CommerceAdCreativeModal
        :ad-creative-rows="adCreativeRows"
        :form="adCreativeForm"
        :modal="adCreativeModal"
        @add-row="addAdCreativeRow"
        @close="closeAdCreativeModal"
        @remove-row="removeAdCreativeRow"
        @submit="storeAdCreatives"
    />

    <CommerceEditCampaignModal
        :ad-placements="adPlacements"
        :creative-preview-url="creativePreviewUrl"
        :edit-campaign-ad-formats="editCampaignAdFormats"
        :edit-campaign-creative-rows="editCampaignCreativeRows"
        :edit-campaign-preview-url="editCampaignPreviewUrl"
        :form="editCampaignForm"
        :modal="editCampaignModal"
        :money-input-attrs="moneyInputAttrs"
        :selected-edit-ad-format="selectedEditAdFormat"
        :selected-edit-ad-placement="selectedEditAdPlacement"
        @add-creative-row="addEditCampaignCreativeRow"
        @close="closeEditCampaignModal"
        @remove-creative-row="removeEditCampaignCreativeRow"
        @set-creative-upload="setEditCampaignCreativeUpload"
        @submit="submitEditCampaign"
    />

    <CommerceDeleteCampaignModal
        :modal="deleteCampaignModal"
        @close="closeDeleteCampaignModal"
        @confirm="confirmDeleteOwnCampaign"
        @update:confirmation="setDeleteCampaignConfirmation"
    />

    <CommerceCheckoutConfirmationModal
        :confirmation="checkoutConfirmation"
        :interval="interval"
        :price="checkoutConfirmationPrice"
        :provider-label="providerLabel"
        :title="checkoutConfirmationTitle"
        @close="closeCheckoutConfirmation"
        @confirm="confirmCheckout"
        @update:accepted="setCheckoutAccepted"
    />

    <CommerceCartCheckoutModal
        :cart="cart"
        :cart-items="cartItems"
        :form="cartCheckoutForm"
        :format-money="formatMoney"
        :open="showCartCheckout"
        :pricing-countries="pricingCountries"
        @close="emit('update:showCartCheckout', false)"
        @submit="checkoutCart"
    />
</template>
