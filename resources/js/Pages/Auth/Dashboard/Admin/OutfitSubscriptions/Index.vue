<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
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
    pending: 'border-amber-400/50 bg-amber-400/10 text-amber-200',
    pending_payment: 'border-amber-400/50 bg-amber-400/10 text-amber-200',
    paid: 'border-emerald-400/50 bg-emerald-400/10 text-emerald-200',
    active: 'border-emerald-400/50 bg-emerald-400/10 text-emerald-200',
    cancels_at_period_end: 'border-amber-400/50 bg-amber-400/10 text-amber-200',
    payment_paused: 'border-amber-400/50 bg-amber-400/10 text-amber-200',
    cancelled: 'border-red-400/50 bg-red-400/10 text-red-200',
    failed: 'border-red-400/50 bg-red-400/10 text-red-200',
    planned: 'border-sky-400/50 bg-sky-400/10 text-sky-200',
    preparing: 'border-amber-400/50 bg-amber-400/10 text-amber-200',
    shipped: 'border-blue-400/50 bg-blue-400/10 text-blue-200',
    delivered: 'border-emerald-400/50 bg-emerald-400/10 text-emerald-200',
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
    <Head title="Admin Outfit-Abos" />

    <div class="space-y-6 p-4 sm:p-6">
        <section class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-accent">Sportkleidung-Abo Modul</p>
                    <h1 class="mt-2 text-2xl font-bold text-primary">Outfit-Abo Pläne</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Verwalte monatliche Sportkleidung-Abos, Sponsor-Rabatte und Branding-Regeln. Diese Seite ist über eigene Permissions geschätzt.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Pläne</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.plans || 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Aktiv</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.activePlans || 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Sponsor</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.sponsoredPlans || 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Abos</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.subscriptions || 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Offen</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.pendingPayments || 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Bezahlt</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.paidSubscriptions || 0 }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-border bg-card p-2">
            <div class="grid gap-2 md:grid-cols-4">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="flex items-center justify-between gap-3 rounded-lg px-4 py-3 text-left transition"
                    :class="activeTab === tab.id ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-inputBg hover:text-primary'"
                    @click="activeTab = tab.id"
                >
                    <span class="inline-flex items-center gap-3">
                        <i :class="[tab.icon, 'text-xl']"></i>
                        <span class="font-semibold">{{ tab.label }}</span>
                    </span>
                    <span
                        v-if="tab.count !== null"
                        class="rounded-full px-2 py-0.5 text-xs font-bold"
                        :class="activeTab === tab.id ? 'bg-white/20 text-buttonTextPrimary' : 'bg-inputBg text-secondary'"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>
        </section>

        <section v-if="activeTab === 'visuals'" class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="h-20 w-32 shrink-0 overflow-hidden rounded-lg border border-border bg-inputBg">
                        <img
                            v-if="visuals.hero?.url"
                            :src="visuals.hero.url"
                            alt="Outfit-Abo Hero Vorschau"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center text-secondary">
                            <i class="las la-image text-2xl"></i>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-accent">Outfit-Abo Bild</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">Dashboard-Hero</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ visuals.hero?.recommended_size || '1920 x 1080 px' }} - {{ visuals.hero?.ratio || '16:9' }}
                        </p>
                        <p class="mt-1 truncate text-xs text-secondary">{{ visuals.hero?.source }}</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90"
                    @click="openVisualModal"
                >
                    <i class="las la-image text-lg"></i>
                    Bild anpassen
                </button>
            </div>
        </section>

        <Teleport to="body">
            <div v-if="visualModalOpen" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-4xl overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-border p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-accent">Outfit-Abo Bild</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">Dashboard-Hero anpassen</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                                Dieses Bild erscheint oben auf der Outfit-Abo Dashboardseite. Du kannst eine URL/Pfad eintragen oder ein neues Bild hochladen.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeVisualModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="grid max-h-[75vh] gap-6 overflow-y-auto p-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                        <div>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Bild-URL oder gespeicherter Pfad</span>
                                <input
                                    v-model="visualForm.hero_source"
                                    class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                    placeholder="/images/marketplace/airmius_outfit_abo.webp oder outfit-subscriptions/visuals/..."
                                />
                            </label>
                            <p v-if="visualForm.errors.hero_source" class="mt-1 text-sm text-error">{{ visualForm.errors.hero_source }}</p>

                            <label class="mt-4 block">
                                <span class="text-sm font-semibold text-primary">Bild hochladen</span>
                                <input
                                    ref="visualUploadInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    @change="setHeroUpload"
                                />
                            </label>
                            <p v-if="visualForm.errors.hero_upload" class="mt-1 text-sm text-error">{{ visualForm.errors.hero_upload }}</p>

                            <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                                <p class="text-xs font-semibold uppercase text-secondary">Empfohlenes Format</p>
                                <p class="mt-2 text-xl font-bold text-primary">{{ visuals.hero?.recommended_size || '1920 x 1080 px' }}</p>
                                <p class="mt-1 text-sm text-secondary">Verhaeltnis {{ visuals.hero?.ratio || '16:9' }} - {{ visuals.hero?.formats || 'WebP, JPG, PNG' }}</p>
                            </div>

                            <p class="mt-4 rounded-lg border border-accent/30 bg-accent/10 p-3 text-sm leading-6 text-secondary">
                                {{ visuals.hero?.note || 'Querformat nutzen. Wichtige Texte/Logos mittig halten, weil die Dashboard-Ansicht beschneiden kann.' }}
                            </p>
                        </div>

                        <div class="overflow-hidden rounded-lg border border-border bg-inputBg">
                    <img
                        v-if="visuals.hero?.url"
                        :src="visuals.hero.url"
                        alt="Outfit-Abo Hero Vorschau"
                        class="aspect-video w-full object-cover"
                    />
                    <div v-else class="flex aspect-video items-center justify-center text-secondary">
                        <i class="las la-image text-4xl"></i>
                    </div>
                    <div class="border-t border-border p-4">
                        <p class="text-sm font-semibold text-primary">Aktuelle Vorschau</p>
                        <p class="mt-1 break-all text-xs text-secondary">{{ visuals.hero?.source }}</p>
                    </div>
                </div>
            </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-border p-5 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeVisualModal">
                            Abbrechen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                            :disabled="visualForm.processing"
                            @click="updateVisuals"
                        >
                            Bild speichern
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <section v-if="activeTab === 'deliveries'" class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-accent">Lieferungen</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">Outfit-Abo Lieferungen verwalten</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Plane Boxen, pflege Paketdienst und Trackingnummer und markiere Lieferungen als versendet oder geliefert.
                    </p>
                </div>
                <span class="rounded-full bg-inputBg px-3 py-1 text-sm font-semibold text-secondary">
                    {{ deliveries.length }} letzte Lieferungen
                </span>
            </div>

            <div v-if="deliveries.length" class="mt-5 space-y-4">
                <article v-for="delivery in deliveries" :key="delivery.id" class="rounded-lg border border-border bg-inputBg p-4">
                    <div class="grid gap-4 xl:grid-cols-[minmax(14rem,1.2fr)_minmax(12rem,1fr)_minmax(18rem,1.4fr)_minmax(15rem,1fr)_auto] xl:items-start">
                        <div>
                            <p class="font-semibold text-primary">{{ delivery.subscription?.user?.name || 'Unbekannter Kunde' }}</p>
                            <p class="mt-1 break-all text-xs text-secondary">{{ delivery.subscription?.user?.email || '-' }}</p>
                            <p class="mt-2 text-sm font-semibold text-primary">{{ delivery.subscription?.plan?.name || 'Plan gelöscht' }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ delivery.subscription?.payment_reference || 'Keine Referenz' }}</p>
                            <p v-if="delivery.subscription?.shipping_address" class="mt-2 text-xs text-secondary">
                                {{ delivery.subscription.shipping_address.name || 'Lieferadresse' }} - {{ shippingAddressLine(delivery.subscription.shipping_address) || '-' }}
                            </p>
                            <div v-if="delivery.issue" class="mt-3 rounded-lg border border-amber-400/30 bg-amber-400/10 p-3">
                                <p class="text-xs font-semibold uppercase text-amber-200">{{ issueTypeLabel(delivery.issue.type) }}</p>
                                <p class="mt-1 text-sm font-semibold text-primary">{{ issueStatusLabel(delivery.issue.status) }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ delivery.issue.description }}</p>
                                <p v-if="delivery.issue.exchange_size" class="mt-1 text-xs text-secondary">Grüße: {{ delivery.issue.exchange_size }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="badgeClass(formForDelivery(delivery).status)">
                                {{ statusLabel(formForDelivery(delivery).status) }}
                            </span>
                            <select v-model="formForDelivery(delivery).status" class="w-full rounded-lg border-border bg-card text-sm text-primary">
                                <option value="planned">Geplant</option>
                                <option value="preparing">In Vorbereitung</option>
                                <option value="shipped">Versendet</option>
                                <option value="delivered">Geliefert</option>
                                <option value="cancelled">Storniert</option>
                            </select>
                            <input v-model="formForDelivery(delivery).delivery_month" type="date" class="w-full rounded-lg border-border bg-card text-sm text-primary">
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                            <input v-model="formForDelivery(delivery).carrier" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Paketdienst, z.B. DHL">
                            <input v-model="formForDelivery(delivery).tracking_number" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Trackingnummer">
                            <input v-model="formForDelivery(delivery).tracking_url" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Tracking-Link https://...">
                            <a v-if="delivery.tracking_url" :href="delivery.tracking_url" target="_blank" rel="noopener noreferrer" class="break-all text-xs font-semibold text-accent underline underline-offset-2">
                                Tracking öffnen
                            </a>
                            <div class="text-xs text-secondary sm:col-span-2 xl:col-span-1">
                                <p>Versendet: {{ formatDate(delivery.shipped_at) }}</p>
                                <p>Geliefert: {{ formatDate(delivery.delivered_at) }}</p>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <textarea v-model="formForDelivery(delivery).items_text" rows="3" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Artikel je Zeile, z.B. Laufshirt M"></textarea>
                            <textarea v-model="formForDelivery(delivery).notes" rows="3" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Interne Notiz"></textarea>
                            <template v-if="delivery.issue">
                                <select v-model="formForDelivery(delivery).issue_status" class="rounded-lg border-border bg-card text-sm text-primary">
                                    <option value="open">Offen</option>
                                    <option value="reviewing">In Prüfung</option>
                                    <option value="approved">Freigegeben</option>
                                    <option value="return_waiting">Rücksendung offen</option>
                                    <option value="replacement_preparing">Ersatz wird vorbereitet</option>
                                    <option value="resolved">Geloest</option>
                                    <option value="rejected">Abgeschlossen</option>
                                </select>
                                <input v-model="formForDelivery(delivery).return_tracking_number" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Retouren-Trackingnummer">
                                <input v-model="formForDelivery(delivery).return_tracking_url" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Retouren-Link https://...">
                                <textarea v-model="formForDelivery(delivery).issue_admin_note" rows="2" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Antwort / interne Support-Notiz"></textarea>
                            </template>
                        </div>

                        <div class="flex flex-wrap gap-2 xl:flex-col xl:items-end">
                            <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForDelivery(delivery).processing" @click="saveDelivery(delivery)">
                                Speichern
                            </button>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="markDeliveryShipped(delivery)">
                                Versendet
                            </button>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="markDeliveryDelivered(delivery)">
                                Geliefert
                            </button>
                            <button v-if="delivery.issue" type="button" class="rounded-lg border border-amber-400/50 px-3 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-400/10" @click="saveDeliveryIssue(delivery)">
                                Support speichern
                            </button>
                            <button type="button" class="rounded-lg border border-red-500/50 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10" @click="openDeleteDeliveryModal(delivery)">
                                Löschen
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="mt-5 rounded-lg border border-border bg-inputBg p-6 text-center text-secondary">
                {{ tx('Noch keine Outfit-Lieferungen vorhanden.', 'Noch keine Outfit-Lieferungen vorhanden.') }}
            </div>
        </section>

        <section v-if="activeTab === 'payments'" class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-accent">Zahlungen</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">Outfit-Abos verwalten</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Hier sehen Marketplace- und Abo-Verantwortliche, wer bezahlt hat und welche Zahlungen noch offen sind.
                    </p>
                </div>
                <span class="rounded-full bg-inputBg px-3 py-1 text-sm font-semibold text-secondary">
                    {{ subscriptions.length }} letzte Einträge
                </span>
            </div>

            <div v-if="subscriptions.length" class="mt-5 overflow-hidden rounded-lg border border-border">
                <div class="hidden grid-cols-[minmax(12rem,1.2fr)_minmax(10rem,1fr)_8rem_8rem_10rem_12rem_9rem] gap-3 border-b border-border bg-inputBg px-4 py-3 text-xs font-semibold uppercase text-secondary lg:grid">
                    <span>Kunde</span>
                    <span>Plan</span>
                    <span>Status</span>
                    <span>Zahlung</span>
                    <span>Lieferung</span>
                    <span>Referenz</span>
                    <span class="text-right">Aktion</span>
                </div>

                <article
                    v-for="subscription in subscriptions"
                    :key="subscription.id"
                    class="grid gap-4 border-b border-border px-4 py-4 last:border-b-0 lg:grid-cols-[minmax(12rem,1.2fr)_minmax(10rem,1fr)_8rem_8rem_10rem_12rem_9rem] lg:items-center"
                >
                    <div>
                        <p class="font-semibold text-primary">{{ subscription.user?.name || 'Unbekannter Kunde' }}</p>
                        <p class="mt-1 break-all text-xs text-secondary">{{ subscription.user?.email }}</p>
                        <p class="mt-1 text-xs text-secondary">Anfrage: {{ formatDate(subscription.created_at) }}</p>
                        <p v-if="subscription.shipping_address" class="mt-2 text-xs text-secondary">
                            {{ subscription.shipping_address.name || 'Lieferadresse' }} - {{ shippingAddressLine(subscription.shipping_address) || '-' }}
                        </p>
                    </div>

                    <div>
                        <p class="font-semibold text-primary">{{ subscription.plan?.name || 'Plan gelöscht' }}</p>
                        <p v-if="subscription.sponsor" class="mt-1 text-xs text-accent">Sponsor: {{ subscription.sponsor.name }}</p>
                        <p class="mt-1 text-sm font-semibold text-primary">{{ formatMoney(subscription.total_cents, subscription.currency) }}</p>
                    </div>

                    <div>
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="badgeClass(subscription.status)">
                            {{ statusLabel(subscription.status) }}
                        </span>
                    </div>

                    <div>
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="badgeClass(subscription.payment_status)">
                            {{ paymentStatusLabel(subscription.payment_status) }}
                        </span>
                        <p class="mt-1 text-xs text-secondary">{{ paymentProviderLabel(subscription.payment_provider) }}</p>
                        <p v-if="subscription.payment_status !== 'paid'" class="mt-1 text-xs text-secondary">
                            Erinnerung: {{ subscription.last_payment_reminder_sent_at ? formatDate(subscription.last_payment_reminder_sent_at) : 'Noch nie' }}
                        </p>
                        <p v-if="subscription.payment_status !== 'paid'" class="mt-1 text-xs text-secondary">
                            {{ subscription.payment_reminders_sent || 0 }}/3 gesendet
                        </p>
                        <p v-if="subscription.dunning_level" class="mt-1 text-xs text-amber-200">
                            Mahnstufe {{ subscription.dunning_level }}/3
                        </p>
                        <p v-if="subscription.last_dunning_sent_at" class="mt-1 text-xs text-secondary">
                            Letzte Mahnung: {{ formatDate(subscription.last_dunning_sent_at) }}
                        </p>
                    </div>

                    <div>
                        <button
                            type="button"
                            class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold transition hover:opacity-80 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="badgeClass(subscription.latest_delivery?.status)"
                            :disabled="!subscription.latest_delivery"
                            @click="openDeliveryModal(subscription)"
                        >
                            {{ subscription.latest_delivery ? statusLabel(subscription.latest_delivery.status) : 'Keine' }}
                        </button>
                        <p v-if="subscription.latest_delivery" class="mt-1 text-xs text-secondary">
                            {{ formatDate(subscription.latest_delivery.delivery_month) }}
                        </p>
                        <p v-if="subscription.latest_delivery?.tracking_number" class="mt-1 break-all text-xs text-secondary">
                            {{ subscription.latest_delivery.carrier || 'Tracking' }}: {{ subscription.latest_delivery.tracking_number }}
                        </p>
                        <a v-if="subscription.latest_delivery?.tracking_url" :href="subscription.latest_delivery.tracking_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex break-all text-xs font-semibold text-accent underline underline-offset-2">
                            Tracking öffnen
                        </a>
                        <p v-if="subscription.latest_delivery?.issue" class="mt-2 rounded-lg border border-amber-400/30 bg-amber-400/10 px-2 py-1 text-xs font-semibold text-amber-200">
                            {{ issueTypeLabel(subscription.latest_delivery.issue.type) }}: {{ issueStatusLabel(subscription.latest_delivery.issue.status) }}
                        </p>
                    </div>

                    <div class="text-sm">
                        <p class="font-semibold text-primary">{{ subscription.payment_reference || 'Keine Referenz' }}</p>
                        <p class="mt-1 text-xs text-secondary">Fällig: {{ formatDate(subscription.payment_due_at) }}</p>
                        <p v-if="subscription.payment_status !== 'paid'" class="mt-1 text-xs text-secondary">
                            Autom. Löschung: {{ formatDate(subscription.payment_expires_at) }}
                        </p>
                        <p v-if="subscription.bank_transfer?.iban" class="mt-1 break-all text-xs text-secondary">
                            IBAN: {{ subscription.bank_transfer.iban }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 lg:items-end">
                        <button
                            v-if="subscription.payment_status !== 'paid' && subscription.status !== 'cancelled'"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90"
                            @click="openPaymentModal(subscription)"
                        >
                            Bezahlt markieren
                        </button>
                        <button
                            v-if="subscription.payment_status !== 'paid'"
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!subscription.can_send_payment_reminder"
                            @click="remindPayment(subscription)"
                        >
                            Erinnern
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                            @click="openShippingAddressModal(subscription)"
                        >
                            Adresse
                        </button>
                        <button
                            v-if="subscription.payment_status === 'paid' && subscription.status === 'active'"
                            type="button"
                            class="rounded-lg border border-amber-400/50 px-3 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-400/10"
                            @click="markPaymentOpen(subscription)"
                        >
                            Zahlung offen
                        </button>
                        <button
                            v-if="subscription.status !== 'cancelled'"
                            type="button"
                            class="rounded-lg border border-red-500/50 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10"
                            @click="openCancelSubscriptionModal(subscription)"
                        >
                            Abbrechen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-red-500/50 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10"
                            @click="openDeleteSubscriptionModal(subscription)"
                        >
                            Löschen
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="mt-5 rounded-lg border border-border bg-inputBg p-6 text-center text-secondary">
                {{ tx('Noch keine Outfit-Abo-Anfragen vorhanden.', 'Noch keine Outfit-Abo-Anfragen vorhanden.') }}
            </div>
        </section>

        <Teleport to="body">
            <div v-if="deliveryModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-3xl overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-border p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-accent">Lieferstatus bearbeiten</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ deliveryModal.subscription?.plan?.name || 'Outfit-Lieferung' }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            {{ deliveryModal.subscription?.user?.name || 'Kunde' }} - {{ deliveryModal.subscription?.payment_reference || 'Keine Referenz' }}
                        </p>
                        <p v-if="deliveryModal.subscription?.shipping_address" class="mt-2 text-xs leading-5 text-secondary">
                            {{ deliveryModal.subscription.shipping_address.name || 'Lieferadresse' }} - {{ shippingAddressLine(deliveryModal.subscription.shipping_address) || '-' }}
                        </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeDeliveryModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="grid max-h-[75vh] gap-4 overflow-y-auto p-5 md:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Lieferstatus</span>
                            <select v-model="formForDelivery(deliveryModal.delivery).status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="planned">Geplant</option>
                                <option value="preparing">In Vorbereitung</option>
                                <option value="shipped">Versendet</option>
                                <option value="delivered">Geliefert</option>
                                <option value="cancelled">Storniert</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Liefermonat</span>
                            <input v-model="formForDelivery(deliveryModal.delivery).delivery_month" type="date" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Paketdienst</span>
                            <input v-model="formForDelivery(deliveryModal.delivery).carrier" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="z.B. DHL">
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Trackingnummer</span>
                            <input v-model="formForDelivery(deliveryModal.delivery).tracking_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Trackingnummer">
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Tracking-Link</span>
                            <input v-model="formForDelivery(deliveryModal.delivery).tracking_url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="https://...">
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Artikel in der Lieferung</span>
                            <textarea v-model="formForDelivery(deliveryModal.delivery).items_text" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Ein Artikel pro Zeile"></textarea>
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Notiz</span>
                            <textarea v-model="formForDelivery(deliveryModal.delivery).notes" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Interne Notiz"></textarea>
                        </label>

                        <div v-if="deliveryModal.delivery?.issue" class="rounded-lg border border-amber-400/30 bg-amber-400/10 p-4 md:col-span-2">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase text-amber-200">{{ issueTypeLabel(deliveryModal.delivery.issue.type) }}</p>
                                    <p class="mt-1 text-sm font-semibold text-primary">{{ issueStatusLabel(deliveryModal.delivery.issue.status) }}</p>
                                    <p class="mt-2 text-sm text-secondary">{{ deliveryModal.delivery.issue.description }}</p>
                                    <p v-if="deliveryModal.delivery.issue.requested_resolution" class="mt-1 text-xs text-secondary">Wunsch: {{ deliveryModal.delivery.issue.requested_resolution }}</p>
                                    <p v-if="deliveryModal.delivery.issue.exchange_size" class="mt-1 text-xs text-secondary">Grüße: {{ deliveryModal.delivery.issue.exchange_size }}</p>
                                </div>
                                <span class="rounded-full border border-amber-400/40 px-3 py-1 text-xs font-semibold text-amber-200">
                                    {{ formatDate(deliveryModal.delivery.issue.requested_at) }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Support-Status</span>
                                    <select v-model="formForDelivery(deliveryModal.delivery).issue_status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                        <option value="open">Offen</option>
                                        <option value="reviewing">In Prüfung</option>
                                        <option value="approved">Freigegeben</option>
                                        <option value="return_waiting">Rücksendung offen</option>
                                        <option value="replacement_preparing">Ersatz wird vorbereitet</option>
                                        <option value="resolved">Geloest</option>
                                        <option value="rejected">Abgeschlossen</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Retouren-Trackingnummer</span>
                                    <input v-model="formForDelivery(deliveryModal.delivery).return_tracking_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optional">
                                </label>
                                <label class="block md:col-span-2">
                                    <span class="text-sm font-semibold text-primary">Retouren-Link</span>
                                    <input v-model="formForDelivery(deliveryModal.delivery).return_tracking_url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="https://...">
                                </label>
                                <label class="block md:col-span-2">
                                    <span class="text-sm font-semibold text-primary">Antwort / Support-Notiz</span>
                                    <textarea v-model="formForDelivery(deliveryModal.delivery).issue_admin_note" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Was soll der Kunde sehen?"></textarea>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-border p-5 sm:flex-row sm:justify-between">
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="markDeliveryModalShipped">
                                Als versendet markieren
                            </button>
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="markDeliveryModalDelivered">
                                Als geliefert markieren
                            </button>
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row">
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeDeliveryModal">
                                Abbrechen
                            </button>
                            <button v-if="deliveryModal.delivery?.issue" type="button" class="rounded-lg border border-amber-400/50 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-400/10" @click="saveDeliveryIssue(deliveryModal.delivery)">
                                Support speichern
                            </button>
                            <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForDelivery(deliveryModal.delivery).processing" @click="saveDeliveryModal">
                                Speichern
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="paymentModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-accent">Zahlung bestätigen</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ paymentModal.subscription?.plan?.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                Markiere die Zahlung erst als bezahlt, wenn der Betrag wirklich eingegangen ist. Danach wird das Abo aktiviert und der Kunde benachrichtigt.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closePaymentModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="mt-4 rounded-lg border border-border bg-inputBg p-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-secondary">Kunde</span>
                            <span class="text-right font-semibold text-primary">{{ paymentModal.subscription?.user?.name }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-secondary">Betrag</span>
                            <span class="font-semibold text-primary">{{ formatMoney(paymentModal.subscription?.total_cents, paymentModal.subscription?.currency) }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-secondary">Referenz</span>
                            <span class="text-right font-semibold text-primary">{{ paymentModal.subscription?.payment_reference }}</span>
                        </div>
                    </div>

                    <label class="mt-4 block">
                        <span class="text-sm font-semibold text-primary">Interne Notiz</span>
                        <textarea
                            v-model="paymentModal.note"
                            rows="3"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="Optional, z.B. Zahlung am Kontoauszug geprüft."
                        ></textarea>
                    </label>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closePaymentModal">
                            Abbrechen
                        </button>
                        <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90" @click="markSubscriptionPaid">
                            Zahlung bestätigen
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="shippingAddressModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-2xl rounded-lg border border-border bg-card shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-border p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-accent">Lieferadresse bearbeiten</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ shippingAddressModal.subscription?.plan?.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                {{ shippingAddressModal.subscription?.user?.name || 'Kunde' }} - {{ shippingAddressModal.subscription?.payment_reference || 'Keine Referenz' }}
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeShippingAddressModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="grid max-h-[75vh] gap-4 overflow-y-auto p-5 md:grid-cols-2">
                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Name</span>
                            <input v-model="shippingAddressForm.shipping_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Vor- und Nachname">
                            <span v-if="shippingAddressForm.errors.shipping_name" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_name }}</span>
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Straße</span>
                            <input v-model="shippingAddressForm.shipping_street" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Straße">
                            <span v-if="shippingAddressForm.errors.shipping_street" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_street }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Hausnummer</span>
                            <input v-model="shippingAddressForm.shipping_house_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="12a">
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Land</span>
                            <input v-model="shippingAddressForm.shipping_country" maxlength="2" class="mt-1 w-full rounded-lg border-border bg-inputBg uppercase text-primary" placeholder="DE">
                            <span v-if="shippingAddressForm.errors.shipping_country" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_country }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">PLZ</span>
                            <input v-model="shippingAddressForm.shipping_postal_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="12345">
                            <span v-if="shippingAddressForm.errors.shipping_postal_code" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_postal_code }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Stadt</span>
                            <input v-model="shippingAddressForm.shipping_city" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Berlin">
                            <span v-if="shippingAddressForm.errors.shipping_city" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_city }}</span>
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Bundesland / Region</span>
                            <input v-model="shippingAddressForm.shipping_state" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optional">
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Lieferhinweis</span>
                            <textarea v-model="shippingAddressForm.shipping_note" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optional, z.B. bei Nachbarn abgeben"></textarea>
                        </label>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-border p-5 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeShippingAddressModal">
                            Abbrechen
                        </button>
                        <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90 disabled:opacity-50" :disabled="shippingAddressForm.processing" @click="saveShippingAddress">
                            Adresse speichern
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="cancelSubscriptionModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-red-300">Abo-Anfrage abbrechen</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ cancelSubscriptionModal.subscription?.plan?.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                Die Anfrage wird beendet und der Kunde bekommt eine In-App-Benachrichtigung.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeCancelSubscriptionModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <label class="mt-4 block">
                        <span class="text-sm font-semibold text-primary">Grund für den Kunden</span>
                        <textarea
                            v-model="cancelSubscriptionModal.reason"
                            rows="3"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="Optional, z.B. Zahlung nicht eingegangen."
                        ></textarea>
                    </label>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeCancelSubscriptionModal">
                            Zurück
                        </button>
                        <button type="button" class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500/90" @click="cancelSubscription">
                            Anfrage abbrechen
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="deleteSubscriptionModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-red-300">Outfit-Abo löschen</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ deleteSubscriptionModal.subscription?.plan?.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                            Das Abo von {{ deleteSubscriptionModal.subscription?.user?.name || 'diesem Kunden' }} wird dauerhaft entfernt. Zugehörige Lieferungen werden ebenfalls gelöscht.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeDeleteSubscriptionModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <label class="mt-4 block">
                            <span class="text-sm font-semibold text-primary">Zur Bestätigung delete eingeben</span>
                        <input
                            v-model="deleteSubscriptionModal.confirmation"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="delete"
                        />
                    </label>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeDeleteSubscriptionModal">
                            Abbrechen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500/90 disabled:opacity-50"
                            :disabled="deleteSubscriptionModal.confirmation !== 'delete'"
                            @click="deleteSubscription"
                        >
                            Endgültig löschen
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="deleteDeliveryModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-red-300">Lieferung löschen</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ deleteDeliveryModal.delivery?.subscription?.plan?.name || 'Outfit-Lieferung' }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                Diese Lieferung wird dauerhaft entfernt. Das Outfit-Abo selbst bleibt bestehen.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeDeleteDeliveryModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <label class="mt-4 block">
                        <span class="text-sm font-semibold text-primary">Zur Bestätigung delete eingeben</span>
                        <input
                            v-model="deleteDeliveryModal.confirmation"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="delete"
                        />
                    </label>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeDeleteDeliveryModal">
                            Abbrechen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500/90 disabled:opacity-50"
                            :disabled="deleteDeliveryModal.confirmation !== 'delete'"
                            @click="deleteDelivery"
                        >
                            Endgültig löschen
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <section v-if="activeTab === 'plans'" class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-primary">Pläne verwalten</h2>
                    <p class="mt-1 text-sm text-secondary">Erstelle neue Outfit-Abo-Pläne in einem fokussierten Dialog.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90"
                    @click="openCreatePlanModal"
                >
                    <i class="las la-plus text-lg"></i>
                    Neuen Plan erstellen
                </button>
            </div>
        </section>

        <Teleport to="body">
            <div v-if="createPlanModalOpen" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-5xl overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-border p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-accent">Outfit-Abo Plan</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">Neuen Plan erstellen</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                                Lege Preis, Sponsor, Branding, Sportarten und Box-Inhalt für einen neuen Outfit-Abo-Plan fest.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeCreatePlanModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <form class="max-h-[75vh] overflow-y-auto p-5" @submit.prevent="storePlan">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block xl:col-span-2">
                    <span class="text-sm font-semibold text-primary">Name</span>
                    <input v-model="newPlan.name" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Runner Box" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Preis EUR</span>
                    <input v-model="newPlan.monthly_price_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sponsor-Rabatt EUR</span>
                    <input v-model="newPlan.sponsor_discount_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block xl:col-span-2">
                    <span class="text-sm font-semibold text-primary">Beschreibung</span>
                    <textarea v-model="newPlan.description" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea>
                </label>
                <div class="grid gap-4 rounded-lg border border-border bg-inputBg p-4 xl:col-span-4">
                    <div>
                        <p class="text-sm font-bold text-primary">Personalisierter Vertrag für diesen Plan</p>
                        <p class="mt-1 text-xs leading-5 text-secondary">
                            Diese Werte werden beim Abschluss mit Kundendaten, Preis und Plan als Vertrags-Snapshot gespeichert.
                        </p>
                    </div>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Vertragstitel</span>
                        <input v-model="newPlan.contract_title" class="mt-1 w-full rounded-lg border-border bg-card text-primary" placeholder="Outfit-Abo Vertrag Runner Box" />
                    </label>
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Mindestlaufzeit Monate</span>
                            <input v-model="newPlan.minimum_term_months" type="number" min="0" max="24" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Pause ab Monat</span>
                            <input v-model="newPlan.pause_allowed_after_months" type="number" min="0" max="24" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Kündigungsfrist Tage</span>
                            <input v-model="newPlan.cancellation_notice_days" type="number" min="0" max="90" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Vertragsklauseln</span>
                        <textarea
                            v-model="newPlan.contract_terms_text"
                            rows="5"
                            class="mt-1 w-full rounded-lg border-border bg-card text-primary"
                            placeholder="Eine Klausel pro Zeile, z.B. Pause und Kündigung gelten nur für zukünftige Lieferungen."
                        ></textarea>
                    </label>
                </div>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sponsor</span>
                    <select v-model="newPlan.sponsor_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Kein Sponsor</option>
                        <option v-for="sponsor in sponsors" :key="sponsor.id" :value="sponsor.id">{{ sponsor.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Branding</span>
                    <select v-model="newPlan.branding_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="none">Kein Branding</option>
                        <option value="sponsor_logo">Sponsor-Logo</option>
                        <option value="club_logo">Vereinslogo</option>
                        <option value="custom">Individuell</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Zielgruppe</span>
                    <select v-model="newPlan.target_gender" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="unisex">Unisex</option>
                        <option value="women">Damen</option>
                        <option value="men">Herren</option>
                        <option value="kids">Kinder</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Teile pro Box</span>
                    <input v-model="newPlan.items_per_box" type="number" min="1" max="12" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Grössen</span>
                    <input v-model="newPlan.sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sportarten</span>
                    <div class="mt-1 rounded-lg border border-border bg-inputBg p-3">
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="sport in selectedSports(newPlan)"
                                :key="sport"
                                class="inline-flex items-center gap-2 rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary"
                            >
                                {{ sportLabel(sport) }}
                                <button type="button" class="text-secondary hover:text-error" @click="removeSport(newPlan, sport)">
                                    <i class="las la-times"></i>
                                </button>
                            </span>
                            <span v-if="!selectedSports(newPlan).length" class="text-sm text-secondary">Noch keine Sportart gewählt.</span>
                        </div>

                        <div class="mt-3">
                            <div class="relative">
                                <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                                <input
                                    v-model="newSportQuery"
                                    type="text"
                                    class="w-full rounded-lg border-border bg-card py-2 pl-10 pr-3 text-sm text-primary"
                                    placeholder="Sportart filtern und aus Liste wählen"
                                />
                            </div>
                            <div class="mt-2 grid max-h-56 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                                <button
                                    v-for="sport in filteredSports(newSportQuery, newPlan)"
                                    :key="sport.slug"
                                    type="button"
                                    class="rounded-lg border border-border bg-card px-3 py-2 text-left text-sm transition hover:border-borderHover hover:bg-muted"
                                    @click="addSport(newPlan, sport, newSportQuery)"
                                >
                                    <span class="block font-semibold text-primary">{{ sportLabel(sport.slug) }}</span>
                                    <span v-if="sportCategoryLabel(sport)" class="block text-xs text-secondary">{{ sportCategoryLabel(sport) }}</span>
                                </button>
                                <p v-if="!filteredSports(newSportQuery, newPlan).length" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary sm:col-span-2">
                                    Keine weitere Sportart gefunden.
                                </p>
                            </div>
                        </div>
                    </div>
                </label>
                <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                    <input v-model="newPlan.is_public" type="checkbox" class="rounded border-border bg-card" />
                    <span class="text-sm text-primary">Öffentlich</span>
                </label>
                <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                    <input v-model="newPlan.is_active" type="checkbox" class="rounded border-border bg-card" />
                    <span class="text-sm text-primary">Aktiv</span>
                </label>
            </div>
            <div class="sticky bottom-0 -mx-5 mt-6 flex flex-col-reverse gap-2 border-t border-border bg-card px-5 py-4 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeCreatePlanModal">
                    Abbrechen
                </button>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="newPlan.processing">
                    Plan erstellen
                </button>
            </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <section v-if="activeTab === 'plans'" class="grid gap-4 xl:grid-cols-2">
            <article v-for="plan in plans" :key="plan.id" class="rounded-lg border border-border bg-card p-5">
                <template v-if="editingPlanId !== plan.id">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-primary">{{ plan.name }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ plan.description }}</p>
                            <p v-if="plan.sponsor" class="mt-2 text-sm font-semibold text-accent">Sponsor: {{ plan.sponsor.name }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-inputBg" @click="editingPlanId = plan.id">Bearbeiten</button>
                            <button class="rounded-lg border border-red-500/50 px-3 py-2 text-sm text-red-300 hover:bg-red-500/10" @click="destroyPlan(plan)">Entfernen</button>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-inputBg p-3">
                            <p class="text-xs uppercase text-secondary">Preis</p>
                            <p class="font-bold text-primary">{{ formatMoney(plan.monthly_price_cents, plan.currency) }}</p>
                        </div>
                        <div class="rounded-lg bg-inputBg p-3">
                            <p class="text-xs uppercase text-secondary">Rabatt</p>
                            <p class="font-bold text-primary">{{ formatMoney(plan.sponsor_discount_cents, plan.currency) }}</p>
                        </div>
                        <div class="rounded-lg bg-inputBg p-3">
                            <p class="text-xs uppercase text-secondary">Box</p>
                            <p class="font-bold text-primary">{{ plan.items_per_box }} Teile</p>
                        </div>
                        <div class="rounded-lg bg-inputBg p-3">
                            <p class="text-xs uppercase text-secondary">Abos</p>
                            <p class="font-bold text-primary">{{ plan.subscriptions_count || 0 }}</p>
                        </div>
                    </div>
                    <div v-if="plan.sports?.length" class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="sport in plan.sports"
                            :key="sport"
                            class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary"
                        >
                            {{ sportLabel(sport) }}
                        </span>
                    </div>
                    <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                        <p class="text-xs uppercase text-secondary">Vertrag</p>
                        <p class="mt-1 font-semibold text-primary">{{ plan.contract_title || `Outfit-Abo Vertrag ${plan.name}` }}</p>
                        <p class="mt-2 text-sm text-secondary">
                            Mindestlaufzeit {{ plan.minimum_term_months ?? 3 }} Monate · Pause ab Monat {{ plan.pause_allowed_after_months ?? 3 }} · Kündigungsfrist {{ plan.cancellation_notice_days ?? 14 }} Tage
                        </p>
                        <ul v-if="plan.contract_terms?.length" class="mt-3 space-y-1 text-sm text-secondary">
                            <li v-for="term in plan.contract_terms.slice(0, 3)" :key="term">- {{ term }}</li>
                        </ul>
                    </div>
                </template>

                <form v-else class="grid gap-4 md:grid-cols-2" @submit.prevent="savePlan(plan)">
                    <label class="block md:col-span-2">
                        <span class="text-sm font-semibold text-primary">Name</span>
                        <input v-model="formFor(plan).name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-sm font-semibold text-primary">Beschreibung</span>
                        <textarea v-model="formFor(plan).description" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea>
                    </label>
                    <div class="grid gap-4 rounded-lg border border-border bg-inputBg p-4 md:col-span-2">
                        <p class="text-sm font-bold text-primary">Personalisierter Vertrag für diesen Plan</p>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Vertragstitel</span>
                            <input v-model="formFor(plan).contract_title" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                        </label>
                        <div class="grid gap-4 md:grid-cols-3">
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Mindestlaufzeit Monate</span>
                                <input v-model="formFor(plan).minimum_term_months" type="number" min="0" max="24" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Pause ab Monat</span>
                                <input v-model="formFor(plan).pause_allowed_after_months" type="number" min="0" max="24" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Kündigungsfrist Tage</span>
                                <input v-model="formFor(plan).cancellation_notice_days" type="number" min="0" max="90" class="mt-1 w-full rounded-lg border-border bg-card text-primary" />
                            </label>
                        </div>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Vertragsklauseln</span>
                            <textarea v-model="formFor(plan).contract_terms_text" rows="5" class="mt-1 w-full rounded-lg border-border bg-card text-primary"></textarea>
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Preis EUR</span>
                        <input v-model="formFor(plan).monthly_price_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Rabatt EUR</span>
                        <input v-model="formFor(plan).sponsor_discount_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Sponsor</span>
                        <select v-model="formFor(plan).sponsor_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="">Kein Sponsor</option>
                            <option v-for="sponsor in sponsors" :key="sponsor.id" :value="sponsor.id">{{ sponsor.name }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Branding</span>
                        <select v-model="formFor(plan).branding_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="none">Kein Branding</option>
                            <option value="sponsor_logo">Sponsor-Logo</option>
                            <option value="club_logo">Vereinslogo</option>
                            <option value="custom">Individuell</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Grössen</span>
                        <input v-model="formFor(plan).sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Sportarten</span>
                        <div class="mt-1 rounded-lg border border-border bg-inputBg p-3">
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="sport in selectedSports(formFor(plan))"
                                    :key="sport"
                                    class="inline-flex items-center gap-2 rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary"
                                >
                                    {{ sportLabel(sport) }}
                                    <button type="button" class="text-secondary hover:text-error" @click="removeSport(formFor(plan), sport)">
                                        <i class="las la-times"></i>
                                    </button>
                                </span>
                                <span v-if="!selectedSports(formFor(plan)).length" class="text-sm text-secondary">Noch keine Sportart gewählt.</span>
                            </div>

                            <div class="mt-3">
                                <div class="relative">
                                    <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                                    <input
                                        v-model="editSportQueries[plan.id]"
                                        type="text"
                                        class="w-full rounded-lg border-border bg-card py-2 pl-10 pr-3 text-sm text-primary"
                                        placeholder="Sportart filtern und aus Liste wählen"
                                    />
                                </div>
                                <div class="mt-2 grid max-h-56 gap-2 overflow-y-auto pr-1">
                                    <button
                                        v-for="sport in filteredSports(editSportQueries[plan.id], formFor(plan))"
                                        :key="sport.slug"
                                        type="button"
                                        class="rounded-lg border border-border bg-card px-3 py-2 text-left text-sm transition hover:border-borderHover hover:bg-muted"
                                        @click="addSport(formFor(plan), sport); editSportQueries[plan.id] = ''"
                                    >
                                        <span class="block font-semibold text-primary">{{ sportLabel(sport.slug) }}</span>
                                        <span v-if="sportCategoryLabel(sport)" class="block text-xs text-secondary">{{ sportCategoryLabel(sport) }}</span>
                                    </button>
                                    <p v-if="!filteredSports(editSportQueries[plan.id], formFor(plan)).length" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary">
                                        Keine weitere Sportart gefunden.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </label>
                    <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                        <input v-model="formFor(plan).is_public" type="checkbox" class="rounded border-border bg-card" />
                        <span class="text-sm text-primary">Öffentlich</span>
                    </label>
                    <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                        <input v-model="formFor(plan).is_active" type="checkbox" class="rounded border-border bg-card" />
                        <span class="text-sm text-primary">Aktiv</span>
                    </label>
                    <div class="flex gap-2 md:col-span-2">
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm text-primary" @click="editingPlanId = null">Abbrechen</button>
                    </div>
                </form>
            </article>
        </section>
    </div>
</template>
