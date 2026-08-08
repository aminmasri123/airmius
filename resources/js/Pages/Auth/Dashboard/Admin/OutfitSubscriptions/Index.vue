<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import AdminOutfitDeliveriesPanel from '@/Components/Admin/OutfitSubscriptions/AdminOutfitDeliveriesPanel.vue'
import AdminOutfitPageHeader from '@/Components/Admin/OutfitSubscriptions/AdminOutfitPageHeader.vue'
import AdminOutfitPaymentsPanel from '@/Components/Admin/OutfitSubscriptions/AdminOutfitPaymentsPanel.vue'
import AdminOutfitPlanCreateModal from '@/Components/Admin/OutfitSubscriptions/AdminOutfitPlanCreateModal.vue'
import AdminOutfitPlansPanel from '@/Components/Admin/OutfitSubscriptions/AdminOutfitPlansPanel.vue'
import AdminOutfitTabNav from '@/Components/Admin/OutfitSubscriptions/AdminOutfitTabNav.vue'
import AdminOutfitVisualsPanel from '@/Components/Admin/OutfitSubscriptions/AdminOutfitVisualsPanel.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { confirmDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    plans: { type: Array, default: () => [] },
    sponsors: { type: Array, default: () => [] },
    sports: { type: Array, default: () => [] },
    subscriptions: { type: Array, default: () => [] },
    deliveries: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    visuals: { type: Object, default: () => ({}) },
})

const { t, te, locale } = useI18n()
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}
const editingPlanId = ref(null)
const editForms = ref({})
const deliveryForms = ref({})
const visualUploadInput = ref(null)
const setVisualUploadInput = (element) => { visualUploadInput.value = element }
const visualModalOpen = ref(false)
const createPlanModalOpen = ref(false)
const paymentModal = ref({ open: false, subscription: null, note: '' })
const shippingAddressModal = ref({ open: false, subscription: null })
const deliveryModal = ref({ open: false, subscription: null, delivery: null })
const cancelSubscriptionModal = ref({ open: false, subscription: null, reason: '' })
const deleteSubscriptionModal = ref({ open: false, subscription: null, confirmation: '' })
const deleteDeliveryModal = ref({ open: false, delivery: null, confirmation: '' })
const newSportQuery = ref('')
const editSportQueries = ref({})
const activeTab = ref('payments')

const newPlan = useForm({
    sponsor_id: '',
    name: '',
    description: '',
    contract_title: '',
    contract_terms_text: '',
    minimum_term_months: 3,
    pause_allowed_after_months: 3,
    cancellation_notice_days: 14,
    monthly_price_eur: 29,
    sponsor_discount_eur: 0,
    currency: 'EUR',
    target_gender: 'unisex',
    sizes_text: 'XS, S, M, L, XL',
    sports: [],
    items_per_box: 3,
    branding_type: 'none',
    sort_order: 0,
    is_public: true,
    is_active: true,
})

const visualForm = useForm({
    hero_source: props.visuals.hero?.source || '',
    hero_upload: null,
})

const shippingAddressForm = useForm({
    shipping_name: '',
    shipping_country: 'DE',
    shipping_street: '',
    shipping_house_number: '',
    shipping_postal_code: '',
    shipping_city: '',
    shipping_state: '',
    shipping_note: '',
})

const tabs = computed(() => [
    {
        id: 'payments',
        label: tx('Zahlungen', 'Zahlungen'),
        icon: 'las la-receipt',
        count: props.summary.pendingPayments || 0,
    },
    {
        id: 'plans',
        label: tx('Pläne', 'Pläne'),
        icon: 'las la-box-open',
        count: props.summary.plans || 0,
    },
    {
        id: 'deliveries',
        label: tx('Lieferungen', 'Lieferungen'),
        icon: 'las la-shipping-fast',
        count: props.summary.plannedDeliveries || 0,
    },
    {
        id: 'visuals',
        label: tx('Bilder', 'Bilder'),
        icon: 'las la-image',
        count: null,
    },
])

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const formatDate = (value) => {
    if (!value) return tx('Noch nicht gesetzt', 'Noch nicht gesetzt')

    return new Intl.DateTimeFormat(localeCode.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value))
}

const shippingAddressLine = (address) => [
    [address?.street, address?.house_number].filter(Boolean).join(' '),
    [address?.postal_code, address?.city].filter(Boolean).join(' '),
    [address?.state, address?.country].filter(Boolean).join(', '),
].filter(Boolean).join(', ')

const statusLabel = (status) => tx(`outfit_workspace.status.${status}`, status || 'Unbekannt')

const issueTypeLabel = (type) => tx(`outfit_workspace.issue_types.${type}`, type || '-')

const issueStatusLabel = (status) => tx(`outfit_workspace.issue_status.${status}`, status || '-')

const paymentStatusLabel = (status) => ({
    pending: 'Nicht bezahlt',
    paid: 'Bezahlt',
    failed: 'Fehlgeschlagen',
    cancelled: 'Abgebrochen',
})[status] || status || 'Unbekannt'

const paymentProviderLabel = (provider) => tx(`outfit_workspace.payment.${provider}`, provider === 'stripe' ? 'Karte / Stripe' : provider || 'Nicht gesetzt')

const badgeClass = (status) => ({
    pending: 'border-warning/50 bg-warning/10 text-warning',
    pending_payment: 'border-warning/50 bg-warning/10 text-warning',
    paid: 'border-success/50 bg-success/10 text-success',
    active: 'border-success/50 bg-success/10 text-success',
    cancels_at_period_end: 'border-warning/50 bg-warning/10 text-warning',
    payment_paused: 'border-warning/50 bg-warning/10 text-warning',
    cancelled: 'border-error/50 bg-error/10 text-error',
    failed: 'border-error/50 bg-error/10 text-error',
    planned: 'border-accent/50 bg-accent/10 text-accent',
    preparing: 'border-warning/50 bg-warning/10 text-warning',
    shipped: 'border-accent/50 bg-accent/10 text-accent',
    delivered: 'border-success/50 bg-success/10 text-success',
})[status] || 'border-border bg-inputBg text-secondary'

const toList = (value) => String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

const toLines = (value) => String(value || '')
    .split('\n')
    .map((item) => item.trim())
    .filter(Boolean)

const cents = (value) => Math.max(0, Math.round(Number(String(value).replace(',', '.')) * 100))
const sportOptions = computed(() => props.sports || [])

const sportLabel = (value) => {
    const sport = sportOptions.value.find((sport) => sport.slug === value || sport.name === value || String(sport.id) === String(value))

    if (!sport) return value

    const key = `sports.${sport.slug}`

    return te(key) ? t(key) : sport.name
}

const sportCategoryLabel = (sport) => {
    if (!sport?.category) return ''

    const legacyCategories = {
        'Olympisch': 'olympic',
        'Olympisch / weltweit': 'olympic_worldwide',
        'IOC-anerkannt': 'ioc_recognized',
        'Weltweit': 'worldwide',
    }
    const category = legacyCategories[sport.category] || sport.category
    const key = `sport_categories.${category}`

    return te(key) ? t(key) : sport.category
}

const normalizeSports = (value) => Array.isArray(value) ? value.filter(Boolean) : toList(value)

const selectedSports = (form) => normalizeSports(form.sports)

const filteredSports = (query, form) => {
    const search = String(query || '').trim().toLowerCase()
    const selected = new Set(selectedSports(form))

    return sportOptions.value
        .filter((sport) => !selected.has(sport.slug))
        .filter((sport) => {
            if (!search) return true

            return [
                sportLabel(sport.slug),
                sport.name,
                sport.slug,
                sportCategoryLabel(sport),
                sport.category,
            ].filter(Boolean).some((value) => String(value).toLowerCase().includes(search))
        })
        .slice(0, 18)
}

const addSport = (form, sport, queryRef = null) => {
    const value = sport.slug || sport.name
    const current = selectedSports(form)

    if (!current.includes(value)) {
        form.sports = [...current, value]
    }

    if (queryRef) {
        queryRef.value = ''
    }
}

const removeSport = (form, value) => {
    form.sports = selectedSports(form).filter((sport) => sport !== value)
}

const payload = (form) => ({
    sponsor_id: form.sponsor_id || null,
    name: form.name,
    description: form.description,
    contract_title: form.contract_title,
    contract_terms: toLines(form.contract_terms_text),
    minimum_term_months: Number(form.minimum_term_months || 0),
    pause_allowed_after_months: Number(form.pause_allowed_after_months || 0),
    cancellation_notice_days: Number(form.cancellation_notice_days || 0),
    monthly_price_cents: cents(form.monthly_price_eur),
    sponsor_discount_cents: cents(form.sponsor_discount_eur),
    currency: form.currency,
    target_gender: form.target_gender,
    sizes: toList(form.sizes_text),
    sports: selectedSports(form),
    items_per_box: Number(form.items_per_box || 1),
    branding_type: form.branding_type,
    sort_order: Number(form.sort_order || 0),
    is_public: Boolean(form.is_public),
    is_active: Boolean(form.is_active),
})

const storePlan = () => {
    newPlan.transform(payload).post(route('admin.outfit-subscription-plans.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newPlan.reset()
            newPlan.sports = []
            newSportQuery.value = ''
            createPlanModalOpen.value = false
        },
    })
}

const openCreatePlanModal = () => {
    newPlan.clearErrors()
    createPlanModalOpen.value = true
}

const closeCreatePlanModal = () => {
    if (newPlan.processing) return
    createPlanModalOpen.value = false
}

const formFor = (plan) => {
    if (!editForms.value[plan.id]) {
        editForms.value[plan.id] = useForm({
            sponsor_id: plan.sponsor_id || '',
            name: plan.name,
            description: plan.description || '',
            contract_title: plan.contract_title || '',
            contract_terms_text: (plan.contract_terms || []).join('\n'),
            minimum_term_months: plan.minimum_term_months ?? 3,
            pause_allowed_after_months: plan.pause_allowed_after_months ?? 3,
            cancellation_notice_days: plan.cancellation_notice_days ?? 14,
            monthly_price_eur: Number(plan.monthly_price_cents || 0) / 100,
            sponsor_discount_eur: Number(plan.sponsor_discount_cents || 0) / 100,
            currency: plan.currency || 'EUR',
            target_gender: plan.target_gender || 'unisex',
            sizes_text: (plan.sizes || []).join(', '),
            sports: normalizeSports(plan.sports || []),
            items_per_box: plan.items_per_box || 3,
            branding_type: plan.branding_type || 'none',
            sort_order: plan.sort_order || 0,
            is_public: Boolean(plan.is_public),
            is_active: Boolean(plan.is_active),
        })
    }

    return editForms.value[plan.id]
}

const savePlan = (plan) => {
    formFor(plan).transform(payload).put(route('admin.outfit-subscription-plans.update', plan.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingPlanId.value = null
        },
    })
}

const destroyPlan = async (plan) => {
    const confirmed = await confirmDialog({
        title: tx('outfit_admin.plan_delete_title', 'Plan löschen oder deaktivieren'),
        message: tx('outfit_admin.plan_delete_message', `Soll der Plan "${plan.name}" wirklich gelöscht oder deaktiviert werden?`, { name: plan.name }),
        confirmLabel: tx('outfit_admin.continue', 'Fortfahren'),
        danger: true,
    })

    if (!confirmed) return

    router.delete(route('admin.outfit-subscription-plans.destroy', plan.id), { preserveScroll: true })
}

const formForDelivery = (delivery) => {
    deliveryForms.value[delivery.id] ??= useForm({
        status: delivery.status || 'planned',
        delivery_month: delivery.delivery_month || '',
        tracking_number: delivery.tracking_number || '',
        tracking_url: delivery.tracking_url || '',
        carrier: delivery.carrier || '',
        items_text: (delivery.items || []).join('\n'),
        notes: delivery.notes || '',
        issue_status: delivery.issue?.status || 'reviewing',
        issue_admin_note: delivery.issue?.admin_note || '',
        return_tracking_number: delivery.issue?.return_tracking_number || '',
        return_tracking_url: delivery.issue?.return_tracking_url || '',
    })

    return deliveryForms.value[delivery.id]
}

const saveDelivery = (delivery, onSuccess = null) => {
    formForDelivery(delivery).put(route('admin.outfit-deliveries.update', delivery.id), {
        preserveScroll: true,
        onSuccess,
    })
}

const markDeliveryShipped = (delivery) => {
    const form = formForDelivery(delivery)

    router.post(route('admin.outfit-deliveries.shipped', delivery.id), {
        tracking_number: form.tracking_number,
        tracking_url: form.tracking_url,
        carrier: form.carrier,
    }, {
        preserveScroll: true,
    })
}

const markDeliveryDelivered = (delivery) => {
    router.post(route('admin.outfit-deliveries.delivered', delivery.id), {}, {
        preserveScroll: true,
    })
}

const saveDeliveryIssue = (delivery) => {
    const form = formForDelivery(delivery)

    router.put(route('admin.outfit-deliveries.issue.update', delivery.id), {
        issue_status: form.issue_status,
        issue_admin_note: form.issue_admin_note,
        return_tracking_number: form.return_tracking_number,
        return_tracking_url: form.return_tracking_url,
    }, {
        preserveScroll: true,
    })
}

const openDeliveryModal = (subscription) => {
    if (!subscription.latest_delivery) return

    deliveryModal.value = {
        open: true,
        subscription,
        delivery: subscription.latest_delivery,
    }
}

const closeDeliveryModal = () => {
    deliveryModal.value = { open: false, subscription: null, delivery: null }
}

const saveDeliveryModal = () => {
    if (!deliveryModal.value.delivery) return

    saveDelivery(deliveryModal.value.delivery, closeDeliveryModal)
}

const markDeliveryModalShipped = () => {
    if (!deliveryModal.value.delivery) return

    markDeliveryShipped(deliveryModal.value.delivery)
    closeDeliveryModal()
}

const markDeliveryModalDelivered = () => {
    if (!deliveryModal.value.delivery) return

    markDeliveryDelivered(deliveryModal.value.delivery)
    closeDeliveryModal()
}

const openDeleteDeliveryModal = (delivery) => {
    deleteDeliveryModal.value = { open: true, delivery, confirmation: '' }
}

const closeDeleteDeliveryModal = () => {
    deleteDeliveryModal.value = { open: false, delivery: null, confirmation: '' }
}

const deleteDelivery = () => {
    const delivery = deleteDeliveryModal.value.delivery
    if (!delivery || deleteDeliveryModal.value.confirmation !== 'delete') return

    router.delete(route('admin.outfit-deliveries.destroy', delivery.id), {
        data: {
            confirmation: deleteDeliveryModal.value.confirmation,
        },
        preserveScroll: true,
        onSuccess: closeDeleteDeliveryModal,
    })
}

const setHeroUpload = (event) => {
    visualForm.hero_upload = event.target.files?.[0] || null
}

const updateVisuals = () => visualForm.post(route('admin.outfit-subscriptions.visuals.update'), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
        visualForm.hero_upload = null
        if (visualUploadInput.value) {
            visualUploadInput.value.value = null
        }
        visualModalOpen.value = false
    },
})

const openVisualModal = () => {
    visualForm.hero_source = props.visuals.hero?.source || ''
    visualForm.hero_upload = null
    if (visualUploadInput.value) {
        visualUploadInput.value.value = null
    }
    visualModalOpen.value = true
}

const closeVisualModal = () => {
    if (visualForm.processing) return
    visualModalOpen.value = false
}

const openPaymentModal = (subscription) => {
    paymentModal.value = { open: true, subscription, note: '' }
}

const closePaymentModal = () => {
    paymentModal.value = { open: false, subscription: null, note: '' }
}

const markSubscriptionPaid = () => {
    const subscription = paymentModal.value.subscription
    if (!subscription) return

    router.post(route('admin.outfit-subscriptions.mark-paid', subscription.id), {
        payment_note: paymentModal.value.note,
    }, {
        preserveScroll: true,
        onSuccess: closePaymentModal,
    })
}

const remindPayment = (subscription) => {
    if (!subscription?.can_send_payment_reminder) return

    router.post(route('admin.outfit-subscriptions.payment-reminder', subscription.id), {}, {
        preserveScroll: true,
    })
}

const markPaymentOpen = async (subscription) => {
    if (!subscription) return

    const confirmed = await confirmDialog({
        title: tx('outfit_admin.mark_unpaid_title', 'Zahlung als offen markieren'),
        message: tx('outfit_admin.mark_unpaid_message', 'Dieses laufende Abo als offene Zahlung markieren? Danach startet die Mahnlogik automatisch.'),
        confirmLabel: tx('outfit_admin.mark_unpaid_confirm', 'Als offen markieren'),
    })

    if (!confirmed) return

    router.post(route('admin.outfit-subscriptions.mark-unpaid', subscription.id), {}, {
        preserveScroll: true,
    })
}

const openShippingAddressModal = (subscription) => {
    const address = subscription.shipping_address || {}

    shippingAddressForm.clearErrors()
    shippingAddressForm.shipping_name = address.name || subscription.user?.name || ''
    shippingAddressForm.shipping_country = address.country || 'DE'
    shippingAddressForm.shipping_street = address.street || ''
    shippingAddressForm.shipping_house_number = address.house_number || ''
    shippingAddressForm.shipping_postal_code = address.postal_code || ''
    shippingAddressForm.shipping_city = address.city || ''
    shippingAddressForm.shipping_state = address.state || ''
    shippingAddressForm.shipping_note = address.note || ''
    shippingAddressModal.value = { open: true, subscription }
}

const closeShippingAddressModal = () => {
    if (shippingAddressForm.processing) return

    shippingAddressModal.value = { open: false, subscription: null }
}

const saveShippingAddress = () => {
    const subscription = shippingAddressModal.value.subscription
    if (!subscription) return

    shippingAddressForm.put(route('admin.outfit-subscriptions.shipping-address.update', subscription.id), {
        preserveScroll: true,
        onSuccess: closeShippingAddressModal,
    })
}

const openCancelSubscriptionModal = (subscription) => {
    cancelSubscriptionModal.value = { open: true, subscription, reason: '' }
}

const closeCancelSubscriptionModal = () => {
    cancelSubscriptionModal.value = { open: false, subscription: null, reason: '' }
}

const cancelSubscription = () => {
    const subscription = cancelSubscriptionModal.value.subscription
    if (!subscription) return

    router.post(route('admin.outfit-subscriptions.cancel', subscription.id), {
        reason: cancelSubscriptionModal.value.reason,
    }, {
        preserveScroll: true,
        onSuccess: closeCancelSubscriptionModal,
    })
}

const openDeleteSubscriptionModal = (subscription) => {
    deleteSubscriptionModal.value = { open: true, subscription, confirmation: '' }
}

const closeDeleteSubscriptionModal = () => {
    deleteSubscriptionModal.value = { open: false, subscription: null, confirmation: '' }
}

const deleteSubscription = () => {
    const subscription = deleteSubscriptionModal.value.subscription
    if (!subscription || deleteSubscriptionModal.value.confirmation !== 'delete') return

    router.delete(route('admin.outfit-subscriptions.destroy', subscription.id), {
        data: {
            confirmation: deleteSubscriptionModal.value.confirmation,
        },
        preserveScroll: true,
        onSuccess: closeDeleteSubscriptionModal,
    })
}
</script>

<template>
    <Head :title="tx('outfit_admin.page_title', 'Admin Outfit-Abos')" />

    <div class="space-y-6 p-4 sm:p-6">
        <AdminOutfitPageHeader :summary="summary" />
        <AdminOutfitTabNav v-model:active-tab="activeTab" :tabs="tabs" />

        <AdminOutfitVisualsPanel
            :active-tab="activeTab"
            :close-visual-modal="closeVisualModal"
            :open-visual-modal="openVisualModal"
            :set-hero-upload="setHeroUpload"
            :set-visual-upload-input="setVisualUploadInput"
            :update-visuals="updateVisuals"
            :visual-form="visualForm"
            :visual-modal-open="visualModalOpen"
            :visuals="visuals"
        />

        <AdminOutfitPlanCreateModal
            v-model:new-sport-query="newSportQuery"
            :active-tab="activeTab"
            :add-sport="addSport"
            :close-create-plan-modal="closeCreatePlanModal"
            :create-plan-modal-open="createPlanModalOpen"
            :filtered-sports="filteredSports"
            :new-plan="newPlan"
            :open-create-plan-modal="openCreatePlanModal"
            :remove-sport="removeSport"
            :selected-sports="selectedSports"
            :sponsors="sponsors"
            :sport-category-label="sportCategoryLabel"
            :sport-label="sportLabel"
            :store-plan="storePlan"
        />

        <AdminOutfitPaymentsPanel
            v-if="activeTab === 'payments'"
            :badge-class="badgeClass"
            :cancel-subscription="cancelSubscription"
            :cancel-subscription-modal="cancelSubscriptionModal"
            :close-cancel-subscription-modal="closeCancelSubscriptionModal"
            :close-delete-subscription-modal="closeDeleteSubscriptionModal"
            :close-payment-modal="closePaymentModal"
            :close-shipping-address-modal="closeShippingAddressModal"
            :delete-subscription="deleteSubscription"
            :delete-subscription-modal="deleteSubscriptionModal"
            :format-date="formatDate"
            :format-money="formatMoney"
            :issue-status-label="issueStatusLabel"
            :issue-type-label="issueTypeLabel"
            :mark-payment-open="markPaymentOpen"
            :mark-subscription-paid="markSubscriptionPaid"
            :open-cancel-subscription-modal="openCancelSubscriptionModal"
            :open-delete-subscription-modal="openDeleteSubscriptionModal"
            :open-delivery-modal="openDeliveryModal"
            :open-payment-modal="openPaymentModal"
            :open-shipping-address-modal="openShippingAddressModal"
            :payment-modal="paymentModal"
            :payment-provider-label="paymentProviderLabel"
            :payment-status-label="paymentStatusLabel"
            :remind-payment="remindPayment"
            :save-shipping-address="saveShippingAddress"
            :shipping-address-form="shippingAddressForm"
            :shipping-address-line="shippingAddressLine"
            :shipping-address-modal="shippingAddressModal"
            :status-label="statusLabel"
            :subscriptions="subscriptions"
        />

        <AdminOutfitPlansPanel
            v-if="activeTab === 'plans'"
            v-model:editing-plan-id="editingPlanId"
            :add-sport="addSport"
            :destroy-plan="destroyPlan"
            :edit-sport-queries="editSportQueries"
            :filtered-sports="filteredSports"
            :form-for="formFor"
            :format-money="formatMoney"
            :plans="plans"
            :remove-sport="removeSport"
            :save-plan="savePlan"
            :selected-sports="selectedSports"
            :sponsors="sponsors"
            :sport-category-label="sportCategoryLabel"
            :sport-label="sportLabel"
        />

        <AdminOutfitDeliveriesPanel
            v-if="activeTab === 'deliveries'"
            :badge-class="badgeClass"
            :close-delete-delivery-modal="closeDeleteDeliveryModal"
            :close-delivery-modal="closeDeliveryModal"
            :delete-delivery="deleteDelivery"
            :delete-delivery-modal="deleteDeliveryModal"
            :deliveries="deliveries"
            :delivery-modal="deliveryModal"
            :form-for-delivery="formForDelivery"
            :format-date="formatDate"
            :issue-status-label="issueStatusLabel"
            :issue-type-label="issueTypeLabel"
            :mark-delivery-delivered="markDeliveryDelivered"
            :mark-delivery-modal-delivered="markDeliveryModalDelivered"
            :mark-delivery-modal-shipped="markDeliveryModalShipped"
            :mark-delivery-shipped="markDeliveryShipped"
            :open-delete-delivery-modal="openDeleteDeliveryModal"
            :save-delivery="saveDelivery"
            :save-delivery-issue="saveDeliveryIssue"
            :save-delivery-modal="saveDeliveryModal"
            :shipping-address-line="shippingAddressLine"
            :status-label="statusLabel"
        />
    </div>
</template>
