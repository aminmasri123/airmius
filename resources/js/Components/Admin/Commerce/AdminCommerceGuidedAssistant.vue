<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    activeTab: {
        type: String,
        required: true,
    },
    tabs: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
    sellerApplications: {
        type: Array,
        default: () => [],
    },
    orders: {
        type: Array,
        default: () => [],
    },
    returnRequests: {
        type: Array,
        default: () => [],
    },
    websiteRequests: {
        type: Array,
        default: () => [],
    },
    providerLocations: {
        type: Array,
        default: () => [],
    },
    campaigns: {
        type: Array,
        default: () => [],
    },
    coupons: {
        type: Array,
        default: () => [],
    },
    addons: {
        type: Array,
        default: () => [],
    },
    payoutCandidates: {
        type: Array,
        default: () => [],
    },
    payouts: {
        type: Array,
        default: () => [],
    },
    taxRates: {
        type: Array,
        default: () => [],
    },
    shippingRates: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['select-tab'])

const { t } = useI18n()

const tabByKey = computed(() => Object.fromEntries(props.tabs.map((tab) => [tab.key, tab])))

const countPendingApplications = computed(() => props.sellerApplications.filter((item) => item.status === 'pending').length)
const countDraftProducts = computed(() => props.products.filter((item) => ['draft', 'pending_review'].includes(item.status)).length)
const countOpenOrders = computed(() => props.orders.filter((item) => !['completed', 'cancelled', 'refunded'].includes(item.status)).length)
const countOpenReturns = computed(() => props.returnRequests.filter((item) => !['approved', 'rejected', 'closed'].includes(item.status)).length)
const countOpenPayouts = computed(() => props.payoutCandidates.length + props.payouts.filter((item) => item.status !== 'paid').length)
const countSetupGaps = computed(() => [
    props.providerLocations.length === 0,
    props.taxRates.length === 0,
    props.shippingRates.length === 0,
].filter(Boolean).length)

const workflowCards = computed(() => [
    {
        key: 'seller_launch',
        tab: 'marketplace',
        icon: 'las la-store',
        title: t('adminCommerce.assistant.workflows.sellerLaunch.title'),
        body: t('adminCommerce.assistant.workflows.sellerLaunch.body'),
        count: countPendingApplications.value + countDraftProducts.value,
        tone: 'primary',
        steps: [
            t('adminCommerce.assistant.workflows.sellerLaunch.steps.reviewApplications'),
            t('adminCommerce.assistant.workflows.sellerLaunch.steps.checkProducts'),
            t('adminCommerce.assistant.workflows.sellerLaunch.steps.publishStock'),
        ],
    },
    {
        key: 'fulfillment',
        tab: 'orders',
        icon: 'las la-shipping-fast',
        title: t('adminCommerce.assistant.workflows.fulfillment.title'),
        body: t('adminCommerce.assistant.workflows.fulfillment.body'),
        count: countOpenOrders.value + countOpenReturns.value + props.websiteRequests.length,
        tone: 'warning',
        steps: [
            t('adminCommerce.assistant.workflows.fulfillment.steps.confirmPayment'),
            t('adminCommerce.assistant.workflows.fulfillment.steps.shipOrders'),
            t('adminCommerce.assistant.workflows.fulfillment.steps.closeReturns'),
        ],
    },
    {
        key: 'trust_setup',
        tab: countSetupGaps.value ? 'provider' : 'settings',
        icon: 'las la-shield-alt',
        title: t('adminCommerce.assistant.workflows.trustSetup.title'),
        body: t('adminCommerce.assistant.workflows.trustSetup.body'),
        count: countSetupGaps.value,
        tone: 'success',
        steps: [
            t('adminCommerce.assistant.workflows.trustSetup.steps.providerProfile'),
            t('adminCommerce.assistant.workflows.trustSetup.steps.locations'),
            t('adminCommerce.assistant.workflows.trustSetup.steps.taxShipping'),
        ],
    },
    {
        key: 'growth',
        tab: props.campaigns.length ? 'ads' : 'marketing',
        icon: 'las la-bullhorn',
        title: t('adminCommerce.assistant.workflows.growth.title'),
        body: t('adminCommerce.assistant.workflows.growth.body'),
        count: props.campaigns.length + props.coupons.length + props.addons.length,
        tone: 'info',
        steps: [
            t('adminCommerce.assistant.workflows.growth.steps.offer'),
            t('adminCommerce.assistant.workflows.growth.steps.campaign'),
            t('adminCommerce.assistant.workflows.growth.steps.measure'),
        ],
    },
    {
        key: 'finance',
        tab: 'payouts',
        icon: 'las la-wallet',
        title: t('adminCommerce.assistant.workflows.finance.title'),
        body: t('adminCommerce.assistant.workflows.finance.body'),
        count: countOpenPayouts.value,
        tone: 'neutral',
        steps: [
            t('adminCommerce.assistant.workflows.finance.steps.candidates'),
            t('adminCommerce.assistant.workflows.finance.steps.payouts'),
            t('adminCommerce.assistant.workflows.finance.steps.reports'),
        ],
    },
])

const nextWorkflow = computed(() => workflowCards.value
    .filter((item) => item.count > 0)
    .sort((a, b) => b.count - a.count)[0] || workflowCards.value[0])

const mobileQuickActions = computed(() => workflowCards.value.slice(0, 4))

const currentTabLabel = computed(() => tabByKey.value[props.activeTab]?.label || props.activeTab)

const toneClass = (tone, active = false) => {
    const activeClasses = {
        primary: 'border-air-blue bg-air-blue/10 text-primary',
        warning: 'border-warning/40 bg-warning/10 text-primary',
        success: 'border-success/40 bg-success/10 text-primary',
        info: 'border-info/40 bg-info/10 text-primary',
        neutral: 'border-border bg-muted text-primary',
    }

    if (active) return activeClasses[tone] || activeClasses.neutral

    return 'border-border bg-card text-secondary hover:border-air-blue/40 hover:bg-muted hover:text-primary'
}
</script>

<template>
    <section class="space-y-4">
        <div class="rounded-lg border border-air-blue/30 bg-air-blue/10 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-air-blue">
                        {{ $t('adminCommerce.assistant.eyebrow') }}
                    </p>
                    <h2 class="mt-1 text-lg font-bold text-primary sm:text-xl">
                        {{ $t('adminCommerce.assistant.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ $t('adminCommerce.assistant.subtitle', { tab: currentTabLabel }) }}
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                    @click="emit('select-tab', nextWorkflow.tab)"
                >
                    <i :class="[nextWorkflow.icon, 'text-lg']"></i>
                    <span>{{ $t('adminCommerce.assistant.nextAction', { title: nextWorkflow.title }) }}</span>
                    <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ nextWorkflow.count }}</span>
                </button>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-5">
            <button
                v-for="workflow in workflowCards"
                :key="workflow.key"
                type="button"
                class="flex min-h-40 flex-col justify-between rounded-lg border p-4 text-left transition"
                :class="toneClass(workflow.tone, activeTab === workflow.tab)"
                @click="emit('select-tab', workflow.tab)"
            >
                <span class="flex items-start justify-between gap-3">
                    <span>
                        <i :class="[workflow.icon, 'text-2xl text-air-blue']"></i>
                        <span class="mt-2 block text-sm font-bold text-primary">{{ workflow.title }}</span>
                    </span>
                    <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-bold text-primary">{{ workflow.count }}</span>
                </span>
                <span class="mt-3 block text-xs leading-5 text-secondary">{{ workflow.body }}</span>
                <span class="mt-3 grid gap-1">
                    <span
                        v-for="step in workflow.steps"
                        :key="`${workflow.key}-${step}`"
                        class="flex items-center gap-2 text-xs text-secondary"
                    >
                        <i class="las la-check-circle text-success"></i>
                        <span>{{ step }}</span>
                    </span>
                </span>
                <span class="mt-3 block text-xs font-bold text-air-blue">{{ $t('adminCommerce.assistant.openFlow') }}</span>
            </button>
        </div>

        <div class="md:hidden">
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="workflow in mobileQuickActions"
                    :key="`mobile-${workflow.key}`"
                    type="button"
                    class="inline-flex min-h-11 min-w-[10rem] items-center justify-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs font-bold text-primary"
                    @click="emit('select-tab', workflow.tab)"
                >
                    <i :class="[workflow.icon, 'text-base text-air-blue']"></i>
                    <span class="truncate">{{ workflow.title }}</span>
                    <span class="rounded-full bg-muted px-2 py-0.5">{{ workflow.count }}</span>
                </button>
            </div>
        </div>
    </section>
</template>
