<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    dashboard: { type: Object, default: () => ({}) },
})

const { t, locale } = useI18n({ useScope: 'global' })
const selectedDays = ref(Number(props.dashboard.period?.days || 28))
const localeCode = computed(() => ({ de: 'de-DE', en: 'en-US', fr: 'fr-FR', ar: 'ar-EG' })[locale.value] || 'de-DE')
const metricOrder = ['consented_cohort', 'activation', 'active_users', 'seven_day_retention', 'training_engagement', 'team_engagement', 'paid_customers', 'recent_churn']
const metrics = computed(() => Object.fromEntries((props.dashboard.metrics || []).map((metric) => [metric.key, metric])))
const privacy = computed(() => props.dashboard.privacy || {})

const formatNumber = (value) => value === null || value === undefined
    ? t('product_analytics.suppressed')
    : Number(value).toLocaleString(localeCode.value)

const loadWindow = () => {
    router.get(route('admin.product-analytics.index'), { days: selectedDays.value }, {
        preserveScroll: true,
        preserveState: true,
        only: ['dashboard'],
    })
}
</script>

<template>
    <Head :title="t('product_analytics.title')" />

    <div class="space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5 lg:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-air-blue">{{ t('product_analytics.eyebrow') }}</p>
                        <h1 class="mt-1 text-2xl font-black text-primary lg:text-3xl">{{ t('product_analytics.title') }}</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">{{ t('product_analytics.subtitle') }}</p>
                    </div>

                    <label class="w-full rounded-2xl border border-border bg-bg p-3 text-sm lg:w-56">
                        <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-secondary">{{ t('product_analytics.window') }}</span>
                        <select v-model="selectedDays" class="w-full rounded-xl border-border bg-inputBg text-primary" @change="loadWindow">
                            <option :value="7">{{ t('product_analytics.windows.7') }}</option>
                            <option :value="28">{{ t('product_analytics.windows.28') }}</option>
                            <option :value="90">{{ t('product_analytics.windows.90') }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4 lg:p-6">
                <article
                    v-for="key in metricOrder"
                    :key="key"
                    class="rounded-2xl border border-border bg-bg p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ t(`product_analytics.metrics.${key}.label`) }}</p>
                            <p class="mt-2 text-2xl font-black text-primary">{{ formatNumber(metrics[key]?.value) }}</p>
                        </div>
                        <span
                            v-if="metrics[key]?.rate_percent !== null && metrics[key]?.rate_percent !== undefined"
                            class="rounded-full bg-air-blue/10 px-2.5 py-1 text-xs font-black text-air-blue"
                        >
                            {{ metrics[key].rate_percent.toLocaleString(localeCode) }}%
                        </span>
                        <span
                            v-else-if="metrics[key]?.suppressed"
                            class="rounded-full bg-warning/15 px-2.5 py-1 text-xs font-black text-warning"
                        >
                            {{ t('product_analytics.private') }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-secondary">{{ t(`product_analytics.metrics.${key}.help`) }}</p>
                </article>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <article class="surface-card p-5 lg:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-success/15 text-success" aria-hidden="true">
                        <i class="las la-user-shield text-xl"></i>
                    </span>
                    <div>
                        <h2 class="text-lg font-black text-primary">{{ t('product_analytics.privacy_title') }}</h2>
                        <p class="mt-1 text-sm leading-6 text-secondary">{{ t('product_analytics.privacy_summary', { count: privacy.minimum_group_size || 5 }) }}</p>
                    </div>
                </div>

                <ul class="mt-5 grid gap-3 text-sm text-secondary sm:grid-cols-2">
                    <li v-for="key in ['consent', 'minors', 'minimum_group', 'no_sdk', 'no_identifiers', 'no_sensitive']" :key="key" class="flex items-start gap-2 rounded-xl border border-border bg-bg p-3">
                        <i class="las la-check-circle mt-0.5 text-success" aria-hidden="true"></i>
                        <span>{{ t(`product_analytics.privacy.${key}`, { count: privacy.minimum_group_size || 5 }) }}</span>
                    </li>
                </ul>
            </article>

            <article class="surface-card p-5 lg:p-6">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-air-orange">{{ t('product_analytics.status_title') }}</p>
                <h2 class="mt-2 text-xl font-black text-primary">{{ t(`product_analytics.status.${dashboard.status || 'disabled'}.title`) }}</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">{{ t(`product_analytics.status.${dashboard.status || 'disabled'}.help`, { count: privacy.minimum_group_size || 5 }) }}</p>

                <div class="mt-5 rounded-xl border border-border bg-bg p-3 text-sm text-secondary">
                    <p class="font-bold text-primary">{{ t('product_analytics.period') }}</p>
                    <p class="mt-1">{{ dashboard.period?.from }} – {{ dashboard.period?.to }}</p>
                </div>
            </article>
        </section>
    </div>
</template>
