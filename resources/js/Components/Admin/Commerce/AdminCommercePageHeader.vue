<script setup>
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
    campaignStatusError: {
        type: String,
        default: '',
    },
    success: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <section class="surface-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ t('adminCommerce.header.eyebrow') }}</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">{{ t('adminCommerce.header.title') }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-secondary">
                    {{ t('adminCommerce.header.subtitle') }}
                </p>
            </div>

            <Link
                :href="route('admin.subscriptions.index')"
                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
            >
                <i class="las la-credit-card text-lg"></i>
                <span>{{ t('adminCommerce.header.subscriptions') }}</span>
            </Link>
        </div>
        <div v-if="success" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {{ success }}
        </div>
        <div v-if="campaignStatusError" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            {{ campaignStatusError }}
        </div>
    </section>
</template>
